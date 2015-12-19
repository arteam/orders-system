create table fulfillments (
  id            int           not null auto_increment,
  bid_id        int           not null,
  product       varchar(64)   not null,
  amount        smallint      not null,
  price         decimal(9, 2) not null,
  customer_id   int           not null,
  place_time    timestamp     not null,
  fullfill_time timestamp     not null,
  contractor_id int           not null,
  primary key (id)
);

