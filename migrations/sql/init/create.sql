-- TODO Get credentials from environment variables.
set @os_user = ;
set @os_password = ;

create database customers;
create database bids;
create database fulfillments;
create database contractors;

-- This is gross. Thanks, MySQL!
set @query = concat('create user "',@os_user,'"@"localhost" identified by "',@os_password,'" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

set @query = concat('grant select,insert,update,delete on customers.* to "',@os_user,'"@"localhost" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

set @query = concat('grant select,insert,update,delete on bids.* to "',@os_user,'"@"localhost" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

set @query = concat('grant select,insert,update,delete on fulfillments.* to "',@os_user,'"@"localhost" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

set @query = concat('grant select,insert,update,delete on contractors.* to "',@os_user,'"@"localhost" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

flush privileges;

