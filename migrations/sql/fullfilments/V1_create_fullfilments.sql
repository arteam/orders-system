create table fullfilments (
  id          mediumint    not null auto_increment,
  bid_id      mediumint    not null,
  product     varchar(64)  not null,
  amount      smallint     not null,
  price       decimal(9,2) not null,
  customer_id mediumint    not null,
  place_time  timestamp    not null,
  fullfill_time timestamp  not null,
  contractor_id mediumint  not null
);

