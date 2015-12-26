alter table bids add constraint check (amount>0);
alter table bids add constraint check (price>0);
alter table bids add constraint check (length(product) >0);