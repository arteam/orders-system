create database customers;
create database bids;
create database fulfillments;
create database contractors;

create user '<OS_USER>'@'localhost' identified by '<OS_PASSWORD>';
grant select,insert,update,delete on customers.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on bids.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on fulfillments.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on contractors.* to '<OS_USER>'@'localhost';

flush privileges;

