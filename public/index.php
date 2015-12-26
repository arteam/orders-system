<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';
require 'errors.php';
require 'db.php';

$app = new \Slim\App;

/**
 * Security checks befory every request
 */
$app->add(function ($request, $response, $next) {
    if (!checkOriginHeaders($request)) {
        return forbidden($response);
    }
    $response = $next($request, $response);
    return $response;
});

/**
 * Serve index.html in the development mode
 */
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(file_get_contents("index.html"));
});

// CUSTOMERS

$app->get('/api/customer/profile', function (Request $request, Response $response) {
    try {
        $contractorSessionId = $request->getCookieParams()['cst_session_id'];
        if (!isset($contractorSessionId)) {
            return forbidden($response);
        }
        $customer = getCustomer($contractorSessionId);
        $response->getBody()->write(json_encode($customer));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($response);
    }
});

$app->post('/api/customers/register', function (Request $request, Response $response) {
    list($dbName, $user, $pass) = getDbConnectionParams('customers');
    // Random secure session id
    $sessionId = sha1(openssl_random_pseudo_bytes(32));

    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("insert into customers(session_id, amount) values (:session_id, 500.0)");
    $stmt->bindParam(":session_id", $sessionId);
    $stmt->execute();
    $stmt = null;
    $pdo = null;

    $expires = gmdate('D, d-M-Y H:i:s e', strtotime('7 days'));
    $response->getBody()->write("api/customers/profile");
    return $response->withStatus(201)
        ->withHeader('Set-Cookie', "cst_session_id=$sessionId; path=/; expires=$expires");
});

// BIDS

$app->get('/api/bids', function (Request $request, Response $response) {
    try {
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        if (!isset($contractorSessionId)) {
            return forbidden($response);
        }

        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            return forbidden($response);
        }

        $bids = getBids();
        // Escape HTML output
        array_walk_recursive($bids, "escapeValue");
        $response->getBody()->write(json_encode($bids));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($response);
    }
});

$app->get('/api/bids/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute("id");
    if (!is_numeric($id)) {
        return badRequest($response);
    }

    try {
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        if (!isset($contractorSessionId)) {
            return forbidden($response);
        }

        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            return forbidden($response);
        }

        $bid = getBidById($id);
        if (!isset($bid)) {
            return notFound($response);
        }

        escapeValue($bid['product']);
        $response->getBody()->write(json_encode($bid));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($response);
    }
});

$app->post('/api/bids/{id}/take', function (Request $request, Response $response) {
    $id = $request->getAttribute("id");
    if (!is_numeric($id)) {
        return badRequest($response);
    }

    try {
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        if (!isset($contractorSessionId)) {
            return forbidden($response);
        }

        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            return forbidden($response);
        }

        // Trying to take the order before anyone!
        // @var GuzzleHttp\Client
        list($bidsPdo, $bid) = getBidAndDeleteItWithoutCommit($id);
        if (!isset($bidsPdo)) {
            // Someone has already taken the order, what a bummer.
            return notFound($response);
        }

        // We won the bid! Let's charge money from the customer
        $customerId = $bid['customer_id'];
        $sum = $bid['price'];
        $royalty = getRoyalty($sum);
        if (!isset($royalty)) {
            // Bad royalty percent format in the config
            return internalError($response);
        }
        $sumWithRoyalty = bcadd($sum, $royalty, 2);
        if (!chargeCustomer($customerId, $sumWithRoyalty)) {
            error_log("Unable to charge a customerId=$customerId on sum=$sum");
            $bidsPdo->rollback();
            // The lousy miser customer doesn't have enough money to pay.
            return notFound($response);
        }

        // Ok, time to move the money to our account
        // The transaction can't fail provided hardware and network are reliable.
        // For the sake of simplicity we don't handle this this case.
        if (!payToContractor($contractorId, $bid['price'])) {
            $bidsPdo->rollback();
            error_log("Issue refund to customerId=$customerId on sum=$sum");
            return internalError($response);
        }

        // Write an entry in the journal that we won the bid and got our money
        // from the customer.
        if (!insertFulfillment($bid['id'], $bid['product'], $bid['amount'], $sum, $royalty, $bid['customer_id'],
            $bid['place_time'], $contractorId)
        ) {
            $bidsPdo->rollback();
            error_log("Issue refund to customerId=$customerId on sum=$sum");
            error_log("Take funds from contractorId=$customerId on sum=$sum");
            return internalError($response);
        };

        // We marked that order is fulfilled, so let's commit the bid transaction
        if (!$bidsPdo->commit()) {
            error_log("Issue refund to customerId=$customerId on sum=$sum");
            error_log("Take funds from contractorId=$customerId on sum=$sum");
            error_log("Mark fulfillment to bid=$id as invalid");
            return internalError($response);
        }

        $response->getBody()->write(json_encode(array("code" => 200, "message" => "OK")));
        return $response;
    } catch (PDOException $e) {
        return handleError($response);
    }
});


$app->post('/api/bids/place', function (Request $request, Response $response) {
    $customerSessionId = $request->getCookieParams()["cst_session_id"];
    if (!isset($customerSessionId)) {
        return forbidden($response);
    }

    $customer = getCustomer($customerSessionId);
    if (!isset($customer)) {
        return forbidden($response);
    }

    $bid = json_decode($request->getBody());
    list($product, $amount, $price) = parseBid($bid);
    if (!isset($product)) {
        return badRequest($response);
    }

    $customerId = $customer['id'];
    if ($price > $customer['amount']) {
        error_log("Customer $customerId doesn't have enough funds to place the bid with price $price");
        return badRequest($response);
    }

    try {
        $bidId = insertBid($product, $amount, $price, $customerId);
        $response->getBody()->write("api/bids/$bidId");
        return $response->withStatus(201);
    } catch (PDOException $e) {
        return handleError($response);
    }

});

// CONTRACTORS

$app->post('/api/contractors/register', function (Request $request, Response $response) {
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    // Generate a secure random id
    $sessionId = sha1(openssl_random_pseudo_bytes(32));
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("insert into contractors(session_id, amount) values (:session_id, 0.0)");
    $stmt->bindParam(":session_id", $sessionId);
    $stmt->execute();
    $stmt = null;
    $pdo = null;

    $expires = gmdate('D, d-M-Y H:i:s e', strtotime('7 days'));
    $response->getBody()->write("api/contractors/profile");
    return $response->withStatus(201)
        ->withHeader('Set-Cookie', "cnt_session_id=$sessionId; path=/; expires=$expires");
});

$app->get('/api/contractors/profile', function (Request $request, Response $response) {
    try {
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        if (!isset($contractorSessionId)) {
            return forbidden($response);
        }

        $contractor = getContractor($contractorSessionId);
        if (!isset($contractor)) {
            return forbidden($response);
        }

        $response->getBody()->write(json_encode($contractor));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($response);
    }
});

// LOGOUT

$app->post('/api/logout', function (Request $request, Response $response) {
    return $response->withHeader('Set-Cookie', 'cnt_session_id=""; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT')
        ->withAddedHeader('Set-Cookie', 'cst_session_id=""; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT');
});

$app->run();


/**
 * Parse and validate the provided JSON bid
 *
 * @param $bid
 * @return array|null
 */
function parseBid($bid)
{
    if (!isset($bid) || !isset($bid->{'product'}) ||
        !isset($bid->{'amount'}) || !isset($bid->{'price'})
    ) {
        return null;
    }

    $product = trim(filter_var($bid->{'product'}, FILTER_SANITIZE_STRING));
    if (empty($product) || strlen($product) > 32) {
        return null;
    }

    $amount = filter_var($bid->{'amount'}, FILTER_VALIDATE_INT);
    if ($amount == false || $amount <= 0 || $amount > 1000000) {
        return null;
    }
    $price = filter_var($bid->{'price'}, FILTER_VALIDATE_FLOAT);
    if ($price == false || $price <= 0 || $price > 1000000) {
        return null;
    }
    return array($product, $amount, $price);
}

/**
 * Calculate system's royalty
 * @param $sum
 * @return null|string
 */
function getRoyalty($sum)
{
    $config = parse_ini_file("/etc/orders-system/conf.ini", false);
    $royaltyPercent = $config['royalty'];
    if (!filter_var($royaltyPercent, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^0.[0-9]+$/']])) {
        return null;
    }
    return bcmul($sum, $royaltyPercent, 2);
}

/**
 * Check that a request comes from the same origin that the server
 * @param Request $request
 * @return bool
 */
function checkOriginHeaders(Request $request)
{

    function isFromOriginHost(Request $request, $originHost, $header)
    {
        $value = $request->getHeader($header);
        if (isset($value) && count($value) > 0) {
            $url = parse_url($value[0]);
            if (!$url || $url['host'] != $originHost) {
                return false;
            }
        }
        return true;
    }

    $config = parse_ini_file("/etc/orders-system/conf.ini", false);
    $originHost = $config['originHost'];
    return isFromOriginHost($request, $originHost, 'Origin') &&
    isFromOriginHost($request, $config['originHost'], 'Referer');
}

/**
 * Escape HTML symbols in the specified string
 * @param $value
 */
function escapeValue(&$value)
{
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}