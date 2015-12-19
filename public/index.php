<?php
use Psr\Http\Message\MessageInterface as MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require '../vendor/autoload.php';

$app = new \Slim\App;
$app->get('/api/customers', function (Request $request, Response $response) {
    list($dbName, $user, $pass) = getDbConnectionParams("customers");

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$dbName", $user, $pass);
        $stmt = $pdo->prepare("select id, amount from customers");
        $stmt->execute();
        $customers = json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        $stmt = null;
        $pdo = null;
        $response->getBody()->write($customers);
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        return handleError($response);
    }
});
$app->run();

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