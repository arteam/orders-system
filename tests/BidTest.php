<?php
require 'AppTest.php';

class BidTest extends AppTest
{

    public function testGetBids()
    {
        $session_id = urlencode('3xnQd4CNGRBhTE48DqHHKZ+AZ7JDuGLu');
        $res = $this->client->request('GET', '/api/bids', [
            'headers' => [
                'Cookie' => "cnt_session_id=$session_id"
            ]
        ]);

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));

        $bids = json_decode($res->getBody());
        $this->assertTrue(count($bids) > 0);
        $this->assertEquals(1, $bids[0]->{'id'});
    }

    public function testGetBidById()
    {
        $session_id = urlencode('3xnQd4CNGRBhTE48DqHHKZ+AZ7JDuGLu');
        $res = $this->client->request('GET', 'api/bids/1', [
            'headers' => [
                'Cookie' => "cnt_session_id=$session_id"
            ]
        ]);
        $bid = json_decode($res->getBody());

        $this->assertEquals('200', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
        $this->assertEquals(1, $bid->{'id'});
        $this->assertEquals('Oranges', $bid->{'product'});
    }

    public function testUnableFindBid()
    {
        $session_id = urlencode('3xnQd4CNGRBhTE48DqHHKZ+AZ7JDuGLu');
        $res = $this->client->request('GET', 'api/bids/0', [
            'headers' => [
                'Cookie' => "cnt_session_id=$session_id"
            ], 'http_errors' => false]);

        $this->assertEquals('404', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
    }

    public function testWrongId()
    {
        $session_id = urlencode('3xnQd4CNGRBhTE48DqHHKZ+AZ7JDuGLu');
        $res = $this->client->request('GET', 'api/bids/one', ['headers' => [
            'Cookie' => "cnt_session_id=$session_id"
        ], 'http_errors' => false]);

        $this->assertEquals('400', $res->getStatusCode());
        $this->assertEquals('application/json', $res->getHeaderLine('content-type'));
    }

    public function testSuccessfullyPlaceBid()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => 22,
                'price' => 100.70
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ]
        ]);

        $this->assertEquals('201', $res->getStatusCode());
        $uri = $res->getBody();
        $this->assertTrue($this->startsWith($uri, 'api/bids/'));
    }

    public function testPlaceBidWithTags()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => ' <p>Coffee</p> & Beans ',
                'amount' => 15,
                'price' => 120.0
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ]
        ]);

        $this->assertEquals('201', $res->getStatusCode());
        $uri = $res->getBody();
        $this->assertTrue($this->startsWith($uri, 'api/bids/'));
    }

    public function testPlaceBidWithEmptyProduct()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => ' ',
                'amount' => -1,
                'price' => 50.5
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }

    public function testPlaceBidWithoutSession()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => 22,
                'price' => 100.70
            ], 'http_errors' => false,
        ]);

        $this->assertEquals('403', $res->getStatusCode());
    }

    public function testPlaceBidWithInvalidSession()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => 22,
                'price' => 100.70
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=ax44z'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('403', $res->getStatusCode());
    }

    public function testPlaceBidWithWrongAmount()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => -1,
                'price' => 50.5
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }

    public function testPlaceBidWithoutAmount()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'price' => 50.5
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }

    public function testPlaceBidWithWrongPrice()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => 10,
                'price' => "Five"
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }

    public function testPlaceBidWithoutPrice()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [
                'product' => 'Coffee',
                'amount' => 22
            ],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }

    public function testNotJsonInput()
    {
        $res = $this->client->request('POST', 'api/bids/place', [
            'json' => [],
            'headers' => [
                'Cookie' => 'cst_session_id=3vsN7oENgh7BdZYzwr/GkqqJt6ZWb7WD'
            ],
            'http_errors' => false,
        ]);

        $this->assertEquals('400', $res->getStatusCode());
    }
}