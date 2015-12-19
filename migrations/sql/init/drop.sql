grant usage on *.* to '<OS_USER>'@'localhost';
drop user '<OS_USER>'@'localhost';

drop database if exists customers;
drop database if exists bids;
drop database if exists fulfillments;
drop database if exists contractors;
