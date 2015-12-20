<?php
require 'AppTest.php';

class ContractorTest extends AppTest
{
    public function testCreateContractor()
    {
        $res = $this->client->request('POST', '/api/contractors/register');
        $uri = $res->getBody()->getContents();
        print $uri;

        $this->assertEquals('201', $res->getStatusCode());
        $cookie = $res->getHeader('Set-Cookie');
        $this->assertTrue($this->startsWith($cookie[0], 'cnt_session_id'));
        $this->assertTrue($this->startsWith($uri, 'api/contractors/'));
    }

    private function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }
}