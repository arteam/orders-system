# An order completion system

This is a small system that allows to place and fullfill orders for 
customers and contractors.

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
```

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