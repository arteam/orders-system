<?php

class AppTest extends PHPUnit_Framework_TestCase
{
    private static $pidArray = array();

    /**
     * @var GuzzleHttp\Client
     */
    protected $client;

    public static function setUpBeforeClass()
    {
        print "\nStarting a web server...";
        $dirname = dirname(__DIR__);
        exec("cd $dirname/public && php -S localhost:8000 > /dev/null 2>&1 & echo $!", AppTest::$pidArray);

        // Wait while the process starts up
        while (!AppTest::isProcessRunning(AppTest::$pidArray[0])) {
        };
        print "\nStarted\n";
    }

    public static function tearDownAfterClass()
    {
        print "\nStopping the web server...";
        $pidArr = AppTest::$pidArray;
        if (count($pidArr) > 0) {
            exec("kill $pidArr[0]");
            print "\nStopped";
        }
    }


    public function setUp()
    {
        $this->client = new GuzzleHttp\Client(['base_uri' => 'http://127.0.0.1:8000/', 'connect_timeout' => 1]);
    }

    private static function isProcessRunning($pid)
    {
        try {
            $output = shell_exec("ps -p  $pid");
            if (count(preg_split("/\n/", $output)) > 2) {
                time_nanosleep(0, 500000);
                return true;
            }
        } catch (Exception $e) {
        }
        return false;
    }

}