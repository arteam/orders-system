<?php
require 'AppTest.php';

class CustomerTest extends AppTest
{
    public function testCreateContractor()
    {
        $res = $this->client->request('POST', '/api/customers/register');
        $uri = $res->getBody()->getContents();

        $this->assertEquals('201', $res->getStatusCode());
        $cookie = $res->getHeader('Set-Cookie');
        $this->assertTrue($this->startsWith($cookie[0], 'cst_session_id'));
        $this->assertTrue($this->startsWith($uri, 'api/customers/profile'));
    }

    private function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }
}