<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';
require 'errors.php';
require 'db.php';
require 'MonologProvider.php';

$container = new \Slim\Container;
$container->register(new MonologProvider());
$app = new \Slim\App($container);

/**
 * Security checks before every request
 */
$app->add(function (Request $request, Response $response, $next) {
    if (!checkOriginHeaders($request)) {
        logger($this)->addError('Wrong origin', getPath($request));
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
        $cookieParams = $request->getCookieParams();
        if (!array_key_exists('cst_session_id', $cookieParams)) {
            logger($this)->addWarning('No customer session id', getPath($request));
            return forbidden($response);
        }
        $contractorSessionId = $cookieParams['cst_session_id'];
        $customer = getCustomer($contractorSessionId);
        if (!isset($customer)) {
            logger($this)->addWarning('No customer found by session id', array(
                    'cst_session_id' => $contractorSessionId,
                    'uri' => $request->getUri()->getPath())
            );
            return forbidden($response);
        }
        $response->getBody()->write(json_encode($customer));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($e, $response);
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
        if (!array_key_exists('cnt_session_id', $request->getCookieParams())) {
            logger($this)->addWarning('No contractor session id', getPath($request));
            return forbidden($response);
        }
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        $contractor = getContractor($contractorSessionId);
        if (!isset($contractor)) {
            logger($this)->addWarning('No contractor found by session id', array(
                    'cnt_session_id' => $contractorSessionId,
                    'uri' => $request->getUri()->getPath())
            );
            return forbidden($response);
        }

        $response->getBody()->write(json_encode($contractor));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($e, $response);
    }
});

// BIDS

$app->get('/api/bids', function (Request $request, Response $response) {
    try {
        if (!array_key_exists('cnt_session_id', $request->getCookieParams())) {
            logger($this)->addWarning('No contractor session id', getPath($request));
            return forbidden($response);
        }

        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            logger($this)->addWarning('No contractor found by session id', array(
                    'cnt_session_id' => $contractorSessionId,
                    'uri' => $request->getUri()->getPath())
            );
            return forbidden($response);
        }

        $bids = getBids();
        // Escape HTML output
        array_walk_recursive($bids, "escapeValue");
        $response->getBody()->write(json_encode($bids));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($e, $response);
    }
});

$app->get('/api/bids/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute("id");
    if (!is_numeric($id)) {
        logger($this)->addWarning('Wrong bid id', getPath($request));
        return badRequest($response);
    }

    try {
        if (!array_key_exists('cnt_session_id', $request->getCookieParams())) {
            logger($this)->addWarning('No contractor session id', getPath($request));
            return forbidden($response);
        }
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            logger($this)->addWarning('No contractor found by session id', array(
                    'cnt_session_id' => $contractorSessionId,
                    'uri' => $request->getUri()->getPath())
            );
            return forbidden($response);
        }

        $bid = getBidById($id);
        if (!isset($bid)) {
            logger($this)->addWarning('No bid found by id', array(
                    'id' => $id,
                    'uri' => $request->getUri()->getPath())
            );
            return notFound($response);
        }

        escapeValue($bid['product']);
        $response->getBody()->write(json_encode($bid));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($e, $response);
    }
});

$app->post('/api/bids/{id}/take', function (Request $request, Response $response) {
    $id = $request->getAttribute("id");
    if (!is_numeric($id)) {
        logger($this)->addWarning('Wrong bid id', getPath($request));
        return badRequest($response);
    }

    try {
        if (!array_key_exists('cnt_session_id', $request->getCookieParams())) {
            logger($this)->addWarning('No contractor session id', getPath($request));
            return forbidden($response);
        }
        $contractorSessionId = $request->getCookieParams()['cnt_session_id'];
        $contractorId = getContractorId($contractorSessionId);
        if (!isset($contractorId)) {
            logger($this)->addWarning('No contractor found by session id', array(
                    'cnt_session_id' => $contractorSessionId,
                    'uri' => $request->getUri()->getPath())
            );
            return forbidden($response);
        }

        // Trying to take the order before anyone!
        list($bidsPdo, $bid) = getBidAndDeleteItWithoutCommit($id);
        if (!isset($bidsPdo)) {
            logger($this)->addWarning('Bid has already been taken', array(
                    'id' => $id,
                    'uri' => $request->getUri()->getPath())
            );
            // Someone has already taken the order, what a bummer.
            return notFound($response);
        }

        // We won the bid! Let's charge money from the customer
        $customerId = $bid['customer_id'];
        $sum = $bid['price'];
        $royalty = getRoyalty($sum);
        if (!isset($royalty)) {
            logger($this)->addError('Wrong royalty in the configuration');
            // Bad royalty percent format in the config
            return internalError($response);
        }
        $sumWithRoyalty = bcadd($sum, $royalty, 2);
        if (!chargeCustomer($customerId, $sumWithRoyalty)) {
            logger($this)->addError("Unable to charge a customer", array(
                'customerId' => $customerId,
                'sum' => $sum
            ));
            $bidsPdo->rollback();
            // The lousy miser customer doesn't have enough money to pay.
            return conflict($response);
        }

        // Ok, time to move the money to our account
        // The transaction can't fail provided hardware and network are reliable.
        if (!payToContractor($contractorId, $bid['price'])) {
            $bidsPdo->rollback();
            logger($this)->addError("Unable to pay to contractor. Issue refund", array(
                'contractorId' => $contractorId,
                'customerId' => $customerId,
                'sum' => $sum
            ));
            return internalError($response);
        }

        // Write an entry in the journal that we won the bid and got our money
        // from the customer.
        if (!insertFulfillment($bid['id'], $bid['product'], $bid['amount'], $sum, $royalty, $bid['customer_id'],
            $bid['place_time'], $contractorId)
        ) {
            $bidsPdo->rollback();
            logger($this)->addError("Unable to insert a fulfillment. Issue refund", array(
                'customerId' => $customerId,
                'sum' => $sum
            ));
            logger($this)->addError("Unable to insert a fulfillment. Take funds", array(
                'contractorId' => $contractorId,
                'sum' => $sum
            ));
            return internalError($response);
        };

        // We marked that order is fulfilled, so let's commit the bid transaction
        if (!$bidsPdo->commit()) {
            logger($this)->addError("Unable to commit an order transaction", array(
                'bidId' => $id
            ));
            logger($this)->addError("Unable to commit an order transaction. Issue refund", array(
                'customerId' => $customerId,
                'sum' => $sum
            ));
            logger($this)->addError("Unable to commit an order transaction. Take funds", array(
                'contractorId' => $contractorId,
                'sum' => $sum
            ));
            logger($this)->addError("Mark a fulfillment as invalid", array(
                'bidId' => $id
            ));
            return internalError($response);
        }

        $response->getBody()->write(json_encode(array("code" => 200, "message" => "OK")));
        return $response;
    } catch (PDOException $e) {
        return handleError($e, $response);
    }
});


$app->post('/api/bids/place', function (Request $request, Response $response) {
    if (!array_key_exists('cst_session_id', $request->getCookieParams())) {
        logger($this)->addWarning('No contractor session id', getPath($request));
        return forbidden($response);
    }
    $customerSessionId = $request->getCookieParams()["cst_session_id"];
    $customer = getCustomer($customerSessionId);
    if (!isset($customer)) {
        logger($this)->addWarning('No contractor found by session id', array(
                'cst_session_id' => $customerSessionId,
                'uri' => $request->getUri()->getPath())
        );
        return forbidden($response);
    }

    $bid = json_decode($request->getBody());
    list($product, $amount, $price) = parseBid($bid);
    if (!isset($product)) {
        logger($this)->addWarning('Wrong bid', getPath($request));
        return badRequest($response);
    }

    $customerId = $customer['id'];
    if ($price > $customer['amount']) {
        logger($this)->addWarning("Customer doesn't have enough funds to place the bid with price", array(
            'customer_id' => $customerId,
            'price' => $price,
        ));
        return conflict($response);
    }

    try {
        $bidId = insertBid($product, $amount, $price, $customerId);
        $response->getBody()->write("api/bids/$bidId");
        return $response->withStatus(201);
    } catch (PDOException $e) {
        return handleError($e, $response);
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

/**
 * Get the current logger
 * @param $this
 * @return Monolog\Logger
 */
function logger($app)
{
    return $app->get('logger');
}

/**
 * Get URI path for logging
 * @param Request $request
 * @return array
 */
function getPath(Request $request)
{
    return array('uri' => $request->getUri()->getPath());
}

/**
 *  Handle an unexpected error
 * @param Exception $e
 * @param Response $response
 * @return \Psr\Http\Message\MessageInterface
 */
function handleError(Exception $e, Response $response)
{
    logger($this)->addError('Unexpected error', array(
        'error' => $e->getTraceAsString()
    ));
    return internalError($response);
}