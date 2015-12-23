<?php
use Psr\Http\Message\MessageInterface as MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';

$app = new \Slim\App;
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write(file_get_contents("index.html"));
});

// CUSTOMERS

$app->get('/api/customers', function (Request $request, Response $response) {
    list($dbName, $user, $pass) = getDbConnectionParams("customers");

    try {
        $pdo = buildPDO($dbName, $user, $pass);
        $stmt = $pdo->query("select id, amount from customers");
        $customers = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        $stmt = null;
        $pdo = null;
        $response->getBody()->write($customers);
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
    $stmt = $pdo->prepare("insert into customers(session_id, amount) values (:session_id, 0.0)");
    $stmt->bindParam(":session_id", $sessionId);
    $stmt->execute();
    $stmt = null;
    $pdo = null;

    $response->getBody()->write("api/customers/profile");
    return $response->withStatus(201)
        ->withHeader('Set-Cookie', "cst_session_id=$sessionId; Path=/");
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
        if (!chargeCustomer($customerId, $sum)) {
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
        if (!insertFulfillment($bid['bid_id'], $bid['product'], $bid['amount'], $bid['price'], $bid['customer_id'],
            $bid['place_time'], $contractorId)
        ) {
            $bidsPdo->rollback();
            error_log("Issue refund to customerId=$customerId on sum=$sum");
            error_log("Take funds from contractorId=$customerId on sum=$sum");
            return internalError($response);
        };

        if (!$bidsPdo->commit()) {
            return notFound($response);
        }

    } catch (PDOException $e) {
        return handleError($response);
    }
});


$app->post('/api/bids/place', function (Request $request, Response $response) {
    $customerSessionId = $request->getCookieParams()["cst_session_id"];
    if (!isset($customerSessionId)) {
        return forbidden($response);
    }

    $customerId = getCustomerId($customerSessionId);
    if (!isset($customerId)) {
        return forbidden($response);
    }

    $bid = json_decode($request->getBody());
    list($product, $amount, $price) = parseBid($bid);
    if (!isset($product)) {
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

    $response->getBody()->write("api/contractors/profile");
    return $response->withStatus(201)
        ->withHeader('Set-Cookie', "cnt_session_id=$sessionId; Path=/");
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
 * Get a customer id by the provided session
 *
 * @param $customerSessionId |null
 * @return null
 */
function getCustomerId($customerSessionId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('customers');
    $customersPdo = buildPDO($dbName, $user, $pass);
    $stmt = $customersPdo->prepare("select id from customers where session_id=:session_id");
    $stmt->bindParam(':session_id', $customerSessionId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }
    $customerId = $row['id'];
    $stmt = null;
    $customersPdo = null;
    return $customerId;
}

/**
 * Charge some funds from the customer account
 *
 * @param $customerId
 * @param $charge
 * @return bool
 */
function chargeCustomer($customerId, $sum)
{
    list($dbName, $user, $pass) = getDbConnectionParams('customers');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("update customers set amount = amount - :sum
                           where id = :id
                           and amount >= :sum");
    $stmt->bindColumn(":id", $customerId);
    $stmt->bindColumn(":sum", $sum);
    $stmt->execute();
    $updatedRows = $stmt->rowCount();

    $stmt = null;
    $pdo = null;
    if ($updatedRows == 0) {
        return false;
    }
    return true;
}

/**
 * Get a contractor id by the provided session id
 *
 * @param $contractorSessionId
 * @return null
 */
function getContractorId($contractorSessionId)
{
    error_log($contractorSessionId);
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("select id from contractors where session_id=:session_id");
    $stmt->bindParam(':session_id', $contractorSessionId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log($row);
    if ($row === false) {
        return null;
    }
    $contractorId = $row['id'];
    $stmt = null;
    $pdo = null;
    return $contractorId;
}

/**
 * Charge some funds from the customer account
 *
 * @param $contractorId
 * @param $sum
 * @return boolean
 */
function payToContractor($contractorId, $sum)
{
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("update contractors set amount = amount + :sum
                           where id = :id");
    try {
        $stmt->bindColumn(":id", $contractorId, PDO::PARAM_INT);
        $stmt->bindColumn(":sum", $sum);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Unable to pay to a contractor $contractorId. Error: " . $e);
        return false;
    } finally {
        $stmt = null;
        $pdo = null;
    }
}

/**
 * Get a list of bids
 * @return array
 */
function getBids()
{
    list($dbName, $user, $pass) = getDbConnectionParams("bids");

    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->query("select id, product, amount, price, customer_id, place_time from bids");
    $bids = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = null;
    $pdo = null;
    return $bids;
}

/**
 * @param $id
 * @return mixed|null
 */
function getBidById($id)
{
    list($dbName, $user, $pass) = getDbConnectionParams('bids');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare('select id, product, amount, price, customer_id, place_time from bids
                               where id=:id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $bid = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($bid === false) {
        return null;
    }

    $stmt = null;
    $pdo = null;
    return $bid;
}

/**
 * Insert a new bid to the DB and return its id
 *
 * @param $product
 * @param $amount
 * @param $price
 * @param $customerId
 * @return string
 */
function insertBid($product, $amount, $price, $customerId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('bids');
    $bidsPdo = buildPDO($dbName, $user, $pass);
    $bidsStmt = $bidsPdo->prepare("insert into bids(product, amount, price, customer_id, place_time) values
                      (:product, :amount, :price, :customer_id, now())");
    $bidsStmt->bindParam(":product", $product);
    $bidsStmt->bindParam(":amount", $amount);
    $bidsStmt->bindParam(":price", $price);
    $bidsStmt->bindParam(":customer_id", $customerId);
    $bidsStmt->execute();

    $bidId = $bidsPdo->lastInsertId();

    $bidsStmt = null;
    $bidsPdo = null;
    return $bidId;
}

/**
 * Get a bid with the provided id and delete it without commit.
 * Return the current PDO context and the bid or null, if the bid is not
 * exist or has already been deleted.
 *
 * @param $bidId
 * @return array|null
 */
function getBidAndDeleteItWithoutCommit($bidId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('bids');
    $pdo = buildPDO($dbName, $user, $pass);
    $pdo->beginTransaction();

    // Read the bid row
    $stmt = $pdo->prepare('select id, product, amount, price, customer_id, place_time from bids
                           where id=:id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $bid = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = null;
    if ($bid === false) {
        // The bid is not exist
        $pdo->rollBack();
        $pdo = null;
        return null;
    }

    // Acquire a write lock on the row, while the transaction is not committed
    $stmt = $pdo->prepare("delete from bids where id=:id");
    $stmt->bindParam(':id', $bidId, PDO::PARAM_INT);
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    $stmt = null;
    if ($rowCount > 0) {
        return array($pdo, $bid);
    } else {
        // The bid has already been deleted
        $pdo->rollBack();
        $pdo = null;
        return null;
    }
}

/**
 * Insert a new fulfillment
 *
 * @param $bidId
 * @param $product
 * @param $amount
 * @param $price
 * @param $customerId
 * @param $placeTime
 * @param $contractorId
 * @return  boolean
 */
function insertFulfillment($bidId, $product, $amount, $price, $customerId, $placeTime, $contractorId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('fulfillments');
    $pdo = buildPDO($dbName, $user, $pass);

    try {
        $stmt = $pdo->prepare("insert into fulfillments(bid_id, product, amount, price, customer_id, place_time,
                   fullfill_time, contractor_id) values
                   (:bid_id, :product, :amount, :price, :customer_id, :place_time, now(), :contractor_id)");
        $stmt->bindColumn(":bid_id", $bidId, PDO::PARAM_INT);
        $stmt->bindColumn(":product", $product);
        $stmt->bindColumn(":amount", $amount, PDO::PARAM_INT);
        $stmt->bindColumn(":price", $price);
        $stmt->bindColumn(":customer_id", $customerId);
        $stmt->bindColumn(":place_time", $placeTime);
        $stmt->bindColumn(":contractor_id", $contractorId);

        return $stmt->execute();
    } catch (Exception $e) {
        error_log("Unable to insert a fulfillment with bidId=$bidId from customer=$customerId
        to contractor=$contractorId", e);
        return false;
    } finally {
        $stmt = null;
        $pdo = null;
    }
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function forbidden(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 403, "message" => "Forbidden")));
    return $response
        ->withStatus(403)
        ->withHeader('Content-Type', 'application/json');
}


/**
 * @param Response $response
 * @return MessageInterface
 */
function badRequest(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 400, "message" => "Bad request")));
    return $response
        ->withStatus(400)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function notFound(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 404, "message" => "Not Found")));
    return $response
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * @param Response $response
 * @return MessageInterface
 */
function internalError(Response $response)
{
    $response->getBody()->write(json_encode(array("code" => 500, "message" => "Internal error")));
    return $response
        ->withStatus(500)
        ->withHeader('Content-Type', 'application/json');
}

/**
 * Parse the given section in the configuration file
 *
 * @param $section
 * @return array
 */
function getDbConnectionParams($section)
{
    $config = parse_ini_file("/etc/orders-system/conf.ini", true);
    $customersDbConf = $config[$section];
    $dbName = $customersDbConf["dbname"];
    $user = $customersDbConf["user"];
    $pass = $customersDbConf["pass"];
    return array($dbName, $user, $pass);
}


/**
 * @param $dbName
 * @param $user
 * @param $pass
 * @return PDO
 */
function buildPDO($dbName, $user, $pass)
{
    $pdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);  // Use native prepare statements
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false); // Convert numeric values to strings
    return $pdo;
}

/**
 * Handle an unexpected error
 *
 * @param Response $response
 * @return MessageInterface
 */
function handleError(Response $response)
{
    $error_message = array("code" => 500, "message" => "Internal Server error");
    $response->getBody()->write(json_encode($error_message));
    return $response->withStatus(500, "Internal Server error")->withHeader('Content-Type', 'application/json');
}