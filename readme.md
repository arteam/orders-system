# An order completion system

This is a small system that allows to place and fullfill orders for 
customers and contractors. A customer places a bid on a specific 
product with the correspoding sum. The system publishes the bid to
the registered contractors. Interested contractors see it. The quickest
one takes the bid. The system marks the bid as completed, charges a 
roaylty from the sum, and transfers funds from the customer to 
the contractor.

# Architecture

The system consists from 4 MySQL databases, a PHP backend that provides a REST API, and a JS client.

# Databases

The database are named _customers_, _contractors_, _bids_ and _fulfillments_. Each database has a table with the same name as the database. See the _migrations/sql_ directory for it's database schemas.  

# API

The server provides the following REST API

* _GET  /api/customer/profile_


Gets the the profile of a registered customer. Requires the `cst_session_id` cookie being set. Responses with:
```json
{
  "id":"5",
  "amount":"500.00"
}  

```
* _POST /api/customers/register_

Registers a new customer and sets the `cst_session_id` cookie. Returns the 201 code in case of success. 

* _GET  /api/contractors/profile_

Gets the the profile of a registered contractor. Requires the `cnt_session_id` cookie being set. Responses with:
```json
{
  "id":"5",
  "amount":"500.00"
}  
```

* _POST /api/contractors/register_

Registers a new contractor and sets the `cnt_session_id` cookie. Returns the 201 code in case of success. 

* _GET  /api/bids_

Returns a list of current available bids for a registered contractor.
uires the `cnt_session_id` cookie being set. Responses with:
```json
[{
	"id": "3",
	"product": "Pancakes",
	"amount": "8",
	"price": "178.34",
	"customer_id": "6",
	"place_time": "2015-12-27 18:52:36"
}, {
	"id": "2",
	"product": "Oranges",
	"amount": "76",
	"price": "178.66",
	"customer_id": "3",
	"place_time": "2015-12-26 22:08:31"
}]

```
* _GET  /api/bid/{id}_

Returns a bid by the specified id for a registered contractor.
Requires the `cnt_session_id` cookie being set. Responses with:
````json
{
	"id": "2",
	"product": "Oranges",
	"amount": "76",
	"price": "178.66",
	"customer_id": "3",
	"place_time": "2015-12-26 22:08:31"
}
````
* _POST /api/bid{id}/take_

Takes a bid with the specified id by a registered contractor.
Requires the `cnt_session_id` cookie being set. Returns the 404 error
code if the bid has already been taken. Return the 409 error code if 
the bid's customer doesn't have enough funds.

* _POST /api/bid/{id}/place_

Places a new bid by a registered customer. Requires the `cst_session_id` cookie being set. If the customer doesn't have enough funds then 
responses with the 409 error code. Request format is:
```json
{
  "product" : "Coffee",
  "amount" : 10,
  "price" : 50.5
}
```

* _POST /api/logout_

Logs out the current contractor, customer or both.

# Server provision

See _provision/provision.sh_

# Development setup

- Clone the repo

```bash
git clone git@github.com:arteam/orders-system.git
```

- Change to root

`su`

- Create databases and migrate schemas

```
export OS_USER='some user'
export OS_PASSWORD='some pass'

cd migrations/
./create_db
./migrate
```

- Create app config

```bash
mkdir /etc/orders-system/
vim /etc/orders-system/conf.ini
```

```
[bids]
dbname=bids
user=
pass=

[customers]
dbname=customers
user=
pass=

[contractors]
dbname=contractors
user=
pass=

[fulfillments]
dbname=fulfillments
user=
pass=

originHost=localhost
royalty=0.15
logFile=/var/log/orders-system.log
```

`chown 600 /etc/orders-system/conf.ini`

- Exit from root

`exit`

- Install app dependencies

```
cd ../
composer install
bower install
```

- Setup the web server

```
cd public/
php -S localhost:8000
```
