<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../vendor/autoload.php';

$app = new \Slim\App;
$app->get('/api/customers', function (Request $request, Response $response) {
    $config = parse_ini_file("/etc/orders-system/conf.ini", true);
    $customersDbConf = $config["customers"];
    $dbName = $customersDbConf["dbname"];
    $user = $customersDbConf["user"];
    $pass = $customersDbConf["pass"];

    $customers = array();
    try {
        $dbh = new PDO("mysql:host=localhost;dbname=$dbName", $user, $pass);
        foreach ($dbh->query('select id, amount from customers') as $row) {
            array_push($customers, array("id" => $row["id"], "amount" => $row["amount"]));
        }
        $dbh = null;
        $response->getBody()->write(json_encode($customers));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (PDOException $e) {
        $error_message = array("code" => 500, "message" => "Internal Server error");
        $response->getBody()->write(json_encode($error_message));
        return $response->withStatus(500, "Internal Server error")->withHeader('Content-Type', 'application/json');
    }
});
$app->run();
