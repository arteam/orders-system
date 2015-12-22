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
    $sessionId = base64_encode(openssl_random_pseudo_bytes(32));

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
        $bids = getBids();
        $response->getBody()->write(json_encode($bids));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        print $e;
        return handleError($response);
    }
});

$app->get('/api/bids/{id}', function (Request $request, Response $response) {
    $id = $request->getAttribute("id");

    if (!is_numeric($id)) {
        return badRequest($response);
    }

    try {
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

    $bidId = insertBid($product, $amount, $price, $customerId);

    $response->getBody()->write("api/bids/$bidId");
    return $response->withStatus(201);
});

// CONTRACTORS

$app->post('/api/contractors/register', function (Request $request, Response $response) {
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    // Generate a secure random id
    $data = openssl_random_pseudo_bytes(32);
    $sessionId = base64_encode($data);
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
 * @param $customerSessionId
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
    if (!isset($row)) {
        return null;
    }
    $customerId = $row['id'];
    $stmt = null;
    $customersPdo = null;
    return $customerId;
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
    $stmt->bindParam(':id', $id);
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