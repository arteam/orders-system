<?php

class AppTest extends PHPUnit_Framework_TestCase
{
    private $pidArray = array();

    /**
     * @var GuzzleHttp\Client
     */
    private $client;

    public function setUp()
    {
        $dirname = dirname(__DIR__);
        exec("cd $dirname/public && php -S localhost:8000 > /dev/null 2>&1 & echo $!", $this->pidArray);

        // Wait while the process starts up
        while (!$this->isProcessRunning($this->pidArray[0])) {
        };

        $this->client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8000/']);
    }

    function isProcessRunning($pid)
    {
        try {
            $output = shell_exec("ps -p  $pid");
            if (count(preg_split("/\n/", $output)) > 2) {
                return true;
            }
        } catch (Exception $e) {
        }

        return false;
    }

    protected function tearDown()
    {
        $pidArr = $this->pidArray;
        if (count($pidArr) > 0) {
            exec("kill $pidArr[0]");
        }
    }

    public function testBidsAPI()
    {

        $res = $this->client->request('GET', "/api/bids");

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));

    }
}