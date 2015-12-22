create database customers default character set utf8 default collate utf8_general_ci;
create database bids default character set utf8 default collate utf8_general_ci;
create database fulfillments default character set utf8 default collate utf8_general_ci;
create database contractors default character set utf8 default collate utf8_general_ci;

create user '<OS_USER>'@'localhost' identified by '<OS_PASSWORD>';
grant select,insert,update,delete on customers.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on bids.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on fulfillments.* to '<OS_USER>'@'localhost';
grant select,insert,update,delete on contractors.* to '<OS_USER>'@'localhost';

flush privileges;

