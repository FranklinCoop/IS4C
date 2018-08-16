<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include('../../../../config.php');
include($FANNIE_ROOT.'src/mysql_connect.php');
include($FANNIE_ROOT.'auth/login.php');
include($FANNIE_ROOT.'src/select_dlog.php');

$startDate = $_REQUEST['startDate'];
$endDate = $_REQUEST['endDate'];
$buyer = $_REQUEST['buyer'];


$dDiffStart = $startDate.' 00:00:00';
$dDiffEnd = $endDate.' 23:59:59';

echo "<span style='font-weight:bold;'>Daily Settlement Report</span><br>";
echo "From $startDate to $endDate";

$dlog = select_dlog($startDate,$endDate);

$dbconn = ($FANNIE_SERVER_DBMS=='MSSQL')?'.dbo.':'.';

$total_sales = '';

$total_sales = "SELECT 
sum(case when department!=0 then total else 0 end) as dept_sales_total,
sum(case when description='MassSalesTax' AND trans_type='C' then regPrice else 0 end) as sales_tax_total,
sum(case when description='StateAndLocalMealsTax' AND trans_type='C' then regPrice else 0 end) as meals_tax_total,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when upc='1930' then total else 0 end) as gift_total,
sum(case when department='995' then total else 0 end) as paid_in_total,
sum(case when description='2% Discount' then unitPrice else 0 end) as member_disc2,
sum(case when description='10% Discount' then unitPrice else 0 end) as member_disc10,
sum(case when (description='15% Discount' and staff=0) then unitPrice else 0 end) as member_disc15,
sum(case when (description='15% Discount' and staff=1) then unitPrice else 0 end) as staff_disc15,
sum(case when (description='17% Discount' and staff=1) then unitPrice else 0 end) as staff_disc17,
sum(case when (description='23% Discount' and staff=1) then unitPrice else 0 end) as staff_disc23,
sum(case when trans_subtype='CC' AND trans_type ='T' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' AND trans_type ='T' then total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GC' AND trans_type ='T' then total else 0 end) as gift_card_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' AND trans_type ='T' then total else 0 end) as instore_charge_total,
sum(case when department='994' then total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' AND trans_type ='T' then total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' AND trans_type ='T' then total else 0 end) as mfg_coupon_total
FROM core_trans.transarchive
WHERE datetime BETWEEN ? AND ? AND store_no=2;";

$row_names = ["Department Sales Totals", "Sales Tax", "Meals Tax", "Member Payments", "Charge Payments",
				"Gift Cards Sold", "Paid In", "Member 2%", "Member 10%", "Member 15%", "Staff 15",
				"Staff 17%", "Staff 23%", "Credit Card Total", "Debit Card Total", "SNAP Total",
				"SNAP Cash Total", "Gift Card Total", "Paper Gift Total", "In Store Charge Total",
				"Paid Out Total", "Store Coupon Total", "Manufactures Coupon Total"];

$args = array($dDiffStart,$dDiffEnd);

$prep = $dbc->prepare_statement($total_sales);
$result = $dbc->exec_statement($prep,$args);
$row = $dbc->fetchArray($result);

echo "<table cellspacing=0 cellpadding=4 border=1>";

if (isset($_REQUEST['excel'])){
	  header('Content-Type: application/ms-excel');
	  header('Content-Disposition: attachment; filename="DailySettlement.xls"');
}
else {
	if(isset($_REQUEST['weekday'])){
		 $weekday = $_REQUEST['weekday'];
	   echo "<br><a href=DailySettlementReport.php?endDate=$endDate&startDate=$startDate>Click here to dump to Excel File</a>";
	}else{
	   echo "<br><a href=DailySettlementReport.php?endDate=$endDate&startDate=$startDate&excel=yes>Click here to dump to Excel File</a>";
	}
	echo " <a href='javascript:history.back();'>Back</a>";
}

//echo "<table cellspacing=0 cellpadding=4 border=1>";
echo "<tr>
		<th> </th> <th>Store Totals</th> <th>Lane 1 Totals</th> <th>Lane 2 Totals</th>
		</tr>";

$echo_str = "";
//$echo_str = "<tr><th>".$result."</th></tr>"."<tr><th>".$result_l1."</th></tr>"."<tr><th>".$result_l2."</th></tr>";

if($result) {
	for($i = 0; $i <count($row); $i++) {
		$echo_str .= "<tr><th>".$row_names[$i]."</th><th>".$row[$i]."</th></tr>";
	}
}

echo $echo_str;


echo "</table>";

?>
