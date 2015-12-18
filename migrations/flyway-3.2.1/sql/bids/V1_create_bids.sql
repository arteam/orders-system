create table bids (
  id          mediumint    not null auto_increment,
  product     varchar(64)  not null,
  amount      smallint     not null,
  price       decimal(9,2) not null,
  customer_id mediumint    not null,
  place_time  timestamp    not null
);

