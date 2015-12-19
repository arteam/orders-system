<?php

require '../../vendor/autoload.php';

class AppTest extends PHPUnit_Framework_TestCase
{
    public function testBasicAPI()
    {
        $client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8000/']);
        $res = $client->request('GET', "/api/user/greg");

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
        $this->assertEquals('{"message":"Hello, greg"}', $res->getBody());
    }
}