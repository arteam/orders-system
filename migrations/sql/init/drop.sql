-- TODO Get the user from environment variables.
set @os_user = '';

set @query = concat('drop user "',@os_user,'"@"localhost" ');
prepare stmt from @query; execute stmt; deallocate prepare stmt;

drop database customers;
drop database bids;
drop database fullfilments;
drop database contractors;

