<?php
/*
Table: memchargebalance

Columns:
	CardNo int
	availBal currency
	balance currency

Depends on:
	custdata (Table)

Use:
View showing member charge balance. Authoritative,
up-to-the-second data is on the server but a local
lookup is faster if slightly stale data is acceptable.
*/
$CREATE['op.memchargebalance'] = "
	CREATE view memchargebalance as
		SELECT 
		c.CardNo AS CardNo,
<<<<<<< HEAD
		c.memDiscountLimit - c.Balance AS availBal,	
=======
		c.ChargeLimit - c.Balance AS availBal,	
>>>>>>> 1ad6218ec85a7208e5b7f12427af955dba79b5c3
		c.Balance as balance
		FROM custdata AS c WHERE personNum = 1
";

?>
