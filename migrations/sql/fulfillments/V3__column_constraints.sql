alter table fulfillments add constraint check (amount>0);
alter table fulfillments add constraint check (price>0);
alter table fulfillments add constraint check (royalty>0);
alter table fulfillments add constraint check (length(product) >0);