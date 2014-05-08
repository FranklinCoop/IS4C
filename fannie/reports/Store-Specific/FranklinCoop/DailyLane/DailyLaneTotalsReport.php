<?php
/*******************************************************************************

    Copyright 2013 Franklin Community Co-op

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


$dDiffStart = $startDate.' 00:00:00';
$dDiffEnd = $endDate.' 23:59:59';

echo "<span style='font-weight:bold;'>Daily Settlement Report</span><br>";
echo "From $startDate to $endDate";

$dlog = select_dlog($startDate,$endDate);

$dbconn = ($FANNIE_SERVER_DBMS=='MSSQL')?'.dbo.':'.';

$total_sales = '';
$lane1_sales = '';
$lane2_sales = '';

$total_sales = "SELECT 
sum(case when trans_subtype='CA' then -total else 0 end) + 500 as cash_total,
sum(case when trans_subtype='CK' and total<0 then 1 else 0 end) as check_number,
sum(case when trans_subtype='CK' then -total else 0 end) as check_total,
sum(case when upc='0000000001930' then -total else 0 end) as gift_sold_number,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when department='995' then -total else 0 end) as paid_in_total,
sum(case when trans_subtype='CC' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' then -total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GD' then -total else 0 end) as gift_total,
sum(case when trans_subtype in ('CC','EF','EC','DC','GD','TC') then -total else 0 end) as card_media_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then -total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' then -total else 0 end) as store_charge_total,
sum(case when department='994' then -total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' then -total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' OR trans_subtype='MC' then -total else 0 end) as mfg_coupon_total
FROM core_trans.dlog_90_view
WHERE tdate BETWEEN ? AND ?;";

$lane1_sales = "SELECT
sum(case when trans_subtype='CA' then -total else 0 end) + 250 as cash_total,
sum(case when trans_subtype='CK' and total<0 then 1 else 0 end) as check_number,
sum(case when trans_subtype='CK' then -total else 0 end) as check_total,
sum(case when upc='0000000001930' then -total else 0 end) as gift_sold_number,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when department='995' then -total else 0 end) as paid_in_total,
sum(case when trans_subtype='CC' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' then -total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GD' then -total else 0 end) as gift_total,
sum(case when trans_subtype in ('CC','EF','EC','DC','GD','TC') then -total else 0 end) as card_media_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then -total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' then -total else 0 end) as store_charge_total,
sum(case when department='994' then -total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' then -total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' OR trans_subtype='MC' then -total else 0 end) as mfg_coupon_total
FROM core_trans.dlog_90_view
WHERE register_no='1' and tdate BETWEEN ? AND ?;";

$lane2_sales = "SELECT
sum(case when trans_subtype='CA' then -total else 0 end) + 250 as cash_total,
sum(case when trans_subtype='CK' and total<0 then 1 else 0 end) as check_number,
sum(case when trans_subtype='CK' then -total else 0 end) as check_total,
sum(case when upc='0000000001930' then -total else 0 end) as gift_sold_number,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when department='995' then -total else 0 end) as paid_in_total,
sum(case when trans_subtype='CC' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' then -total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GD' then -total else 0 end) as gift_total,
sum(case when trans_subtype in ('CC','EF','EC','DC','GD','TC') then -total else 0 end) as card_media_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then -total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' then -total else 0 end) as store_charge_total,
sum(case when department='994' then -total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' then -total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' OR trans_subtype='MC' then -total else 0 end) as mfg_coupon_total
FROM core_trans.dlog_90_view
WHERE register_no='2' and tdate BETWEEN ? AND ?;";

$lane3_sales = "SELECT
sum(case when trans_subtype='CA' then -total else 0 end) + 250 as cash_total,
sum(case when trans_subtype='CK' and total<0 then 1 else 0 end) as check_number,
sum(case when trans_subtype='CK' then -total else 0 end) as check_total,
sum(case when upc='0000000001930' then -total else 0 end) as gift_sold_number,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when department='995' then -total else 0 end) as paid_in_total,
sum(case when trans_subtype='CC' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' then -total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GD' then -total else 0 end) as gift_total,
sum(case when trans_subtype in ('CC','EF','EC','DC','GD','TC') then -total else 0 end) as card_media_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then -total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' then -total else 0 end) as store_charge_total,
sum(case when department='994' then -total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' then -total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' OR trans_subtype='MC' then -total else 0 end) as mfg_coupon_total
FROM core_trans.dlog_90_view
WHERE register_no='3' and tdate BETWEEN ? AND ?;";

$args = array($dDiffStart,$dDiffEnd);

$prep = $dbc->prepare_statement($total_sales);
$result = $dbc->exec_statement($prep,$args);
$row = $dbc->fetchArray($result);

$prep = $dbc->prepare_statement($lane1_sales);
$result_l1 = $dbc->exec_statement($prep,$args);
$row1 = $dbc->fetchArray($result_l1);

$prep = $dbc->prepare_statement($lane2_sales);
$result_l2 = $dbc->exec_statement($prep,$args);
$row2 = $dbc->fetchArray($result_l2);

$prep = $dbc->prepare_statement($lane3_sales);
$result_l3 = $dbc->exec_statement($prep,$args);
$row3 = $dbc->fetchArray($result_l3);

echo "<table cellspacing=0 cellpadding=4 border=1>";

if (isset($_REQUEST['excel'])){
	  header('Content-Type: application/ms-excel');
	  header('Content-Disposition: attachment; filename="DailyLaneTotals.xls"');
}
else {
	if(isset($_REQUEST['weekday'])){
		 $weekday = $_REQUEST['weekday'];
	   echo "<br><a href=DailyLaneTotalsReport.php?endDate=$endDate&startDate=$startDate>Click here to dump to Excel File</a>";
	}else{
	   echo "<br><a href=DailyLaneTotalsReport.php?endDate=$endDate&startDate=$startDate&excel=yes>Click here to dump to Excel File</a>";
	}
	echo " <a href='javascript:history.back();'>Back</a>";
}

//echo "<table cellspacing=0 cellpadding=4 border=1>";
echo "<tr>
		<th> <th>Lane 1 Totals</th> <th>Lane 2 Totals</th><th>Lane 3 Totals</th><th>Store Totals</th>
		</tr>";

$echo_str = "";
//$echo_str = "<tr><th>".$result."</th></tr>"."<tr><th>".$result_l1."</th></tr>"."<tr><th>".$result_l2."</th></tr>";

$row_names = array("Cash Total", "Checks (# of)", "Checks (amount)", "GIFT CARD Sold", "MEMBER PAYMENT",
				"CHARGE PAYMENT TOTAL", "R/A: Other", "CREDIT", "DEBIT", "SNAP: Food", "SNAP: Cash",
				"GIFT CARD Redeemed", "Total CARD MEDIA", "Paper Gift Redeemed","STORE CHARGE", "PAIDOUT",
				"STORE COUPON", "VENDOR COUPONS");

if($result) {
	for($i = 0; $i < count($row_names); $i++) {
		$echo_str .= "<tr><th>".$row_names[$i]."</th><th>".$row1[$i]."</th><th>".$row2[$i]."</th><th>".$row3[$i]."</th><th>".$row[$i]."</th></tr>";
	}
}

echo $echo_str;


echo "</table>";
	
?>
