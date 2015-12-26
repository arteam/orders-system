<?php

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
 * Get a customer by the provided session
 *
 * @param $customerSessionId |null
 * @return null
 */
function getCustomer($customerSessionId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('customers');
    $customersPdo = buildPDO($dbName, $user, $pass);
    $stmt = $customersPdo->prepare("select id, amount from customers where session_id=:session_id");
    $stmt->bindParam(':session_id', $customerSessionId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }
    $stmt = null;
    $customersPdo = null;
    return $row;
}

/**
 * Add a new customer with the provide session
 * @param $customerSessionId
 */
function addCustomer($sessionId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('customers');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("insert into customers(session_id, amount) values (:session_id, 500.0)");
    $stmt->bindParam(":session_id", $sessionId);
    $stmt->execute();
    $stmt = null;
    $pdo = null;
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
    $stmt = $pdo->prepare("update customers set amount = (amount - :sum1)
                           where id = :id
                           and amount >= :sum2");
    $stmt->bindParam(":id", $customerId, PDO::PARAM_INT);
    $stmt->bindParam(":sum1", $sum);
    $stmt->bindParam(":sum2", $sum);
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
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("select id from contractors where session_id=:session_id");
    $stmt->bindParam(':session_id', $contractorSessionId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }
    $contractorId = $row['id'];
    $stmt = null;
    $pdo = null;
    return $contractorId;
}

/**
 * Get a contractor by the provided session id
 * @param $contractorSessionId
 * @return mixed|null
 */
function getContractor($contractorSessionId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("select id, amount from contractors where session_id=:session_id");
    $stmt->bindParam(':session_id', $contractorSessionId);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }
    $stmt = null;
    $pdo = null;
    return $row;
}

/**
 * Add a new contractor by the provided session
 * @param $contractorSessionId
 */
function addContractor($sessionId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('contractors');
    $pdo = buildPDO($dbName, $user, $pass);
    $stmt = $pdo->prepare("insert into contractors(session_id, amount) values (:session_id, 0.0)");
    $stmt->bindParam(":session_id", $sessionId);
    $stmt->execute();
    $stmt = null;
    $pdo = null;
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
    $stmt = $pdo->prepare("update contractors set amount = (amount + :sum)
                           where id = :id");
    try {
        $stmt->bindParam(":id", $contractorId, PDO::PARAM_INT);
        $stmt->bindParam(":sum", $sum);
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
    $stmt = $pdo->query("select id, product, amount, price, customer_id, place_time from bids
                         order by id desc
                         limit 10");
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
    $stmt->bindParam(':id', $bidId, PDO::PARAM_INT);
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
 * @param $royalty
 * @param $price
 * @param $customerId
 * @param $placeTime
 * @param $contractorId
 * @return  boolean
 */
function insertFulfillment($bidId, $product, $amount, $price, $royalty, $customerId, $placeTime, $contractorId)
{
    list($dbName, $user, $pass) = getDbConnectionParams('fulfillments');
    $pdo = buildPDO($dbName, $user, $pass);

    try {
        $stmt = $pdo->prepare("insert into fulfillments(bid_id, product, amount, price, royalty, customer_id, place_time,
                   fullfill_time, contractor_id) values
                   (:bid_id, :product, :amount, :price, :royalty, :customer_id, :place_time, now(), :contractor_id)");
        $stmt->bindParam(":bid_id", $bidId, PDO::PARAM_INT);
        $stmt->bindParam(":product", $product);
        $stmt->bindParam(":amount", $amount, PDO::PARAM_INT);
        $stmt->bindParam(":price", $price);
        $stmt->bindParam(":royalty", $royalty);
        $stmt->bindParam(":customer_id", $customerId);
        $stmt->bindParam(":place_time", $placeTime);
        $stmt->bindParam(":contractor_id", $contractorId);

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
    $pdo->setAttribute(PDO::ATTR_PERSISTENT, true); // Use persisent connections
    return $pdo;
}
