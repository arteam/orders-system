<?php
require 'AppTest.php';

class BidTest extends AppTest
{

    public function testGetBids()
    {
        $res = $this->client->request('GET', "/api/bids");

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));

        $bids = json_decode($res->getBody());
        $this->assertTrue(count($bids) > 0);
        $this->assertEquals(1, $bids[0]->{'id'});
    }

    public function testGetBidById()
    {
        $res = $this->client->request('GET', 'api/bids/1');
        $bid = json_decode($res->getBody());

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
        $this->assertEquals(1, $bid->{'id'});
        $this->assertEquals('Apples', $bid->{'product'});
    }

    public function testUnableFindBid()
    {
        $res = $this->client->request('GET', 'api/bids/0', ['http_errors' => false]);

        $this->assertEquals('404', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
    }

    public function testWrongId()
    {
        $res = $this->client->request('GET', 'api/bids/one', ['http_errors' => false]);

        $this->assertEquals('400', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
    }

    public function placeBid()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                "product" => "Coffee",
                "amount" => 22,
                "price" => 100.70
            ],
            'headers'  => [
                'Cookie' > 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ]
        ]);

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));

        $uri = $res->getBody();
        print $uri;
        $this->assertTrue($this->startsWith($uri, 'api/bids/'));
    }
}