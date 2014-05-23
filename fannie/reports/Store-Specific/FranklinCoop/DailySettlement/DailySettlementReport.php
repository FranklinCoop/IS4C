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

$dDiffStart = $startDate.' 00:00:00';
$dDiffEnd = $endDate.' 23:59:59';

echo "<span style='font-weight:bold;'>Daily Settlement Report</span><br>";
echo "From $startDate to $endDate";

$dlog = select_dlog($startDate,$endDate);

$dbconn = ($FANNIE_SERVER_DBMS=='MSSQL')?'.dbo.':'.';

$total_tax = "SELECT
sum(case when upc='TAXLINEITEM' and description IN ('MaStateMealsTax','StateAndLocalMealsTax', 'MealsTax') then regPrice else 0 end) as sales_tax_total,
sum(case when upc='TAXLINEITEM' and description IN ('MASalesTax','MassSalesTax', 'SalesTax') then regPrice else 0 end) as sales_tax_total
FROM core_trans.transarchive
WHERE datetime BETWEEN ? AND ?;
";

$total_sales = '';

$total_sales = "SELECT 
sum(case when department!=0 and trans_subtype!='CP' and department NOT IN (992,990,994,995) and upc!='0000000001930' then total else 0 end) as dept_sales_total,
'ERR' as sales_tax_total,
'ERR' as meals_tax_total,
sum(case when department='992' then total else 0 end) as member_payment_total,
sum(case when department='990' then total else 0 end) as charge_payment_total,
sum(case when upc='0000000001930' then total else 0 end) as gift_total,
sum(case when department='995' then total else 0 end) as paid_in_total,
sum(case when upc='DISCOUNT' and memType in (1,2) then -unitPrice else 0 end) as member_disc2,
sum(case when upc='DISCOUNT' and memType=3 then -unitPrice else 0 end) as member_disc10,
sum(case when upc='DISCOUNT' and memType=5 then -unitPrice else 0 end) as member_disc15,
sum(case when upc='DISCOUNT' and memType=7 then -unitPrice else 0 end) as staff_disc15,
sum(case when upc='DISCOUNT' and memType=8 then -unitPrice else 0 end) as staff_disc17,
sum(case when upc='DISCOUNT' and memType=9 then -unitPrice else 0 end) as staff_disc23,
sum(case when upc='DISCOUNT' and memType=0 then -unitPrice else 0 end) as senior_disc,
sum(case when upc='DISCOUNT' and memType=6 then -unitPrice else 0 end) as food_for_all_disc,
sum(case when trans_subtype='CC' AND trans_type ='T' then -total else 0 end) as credit_total,
sum(case when trans_subtype='DC' AND trans_type ='T' then -total else 0 end) as debit_total,
sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
sum(case when trans_subtype='GD' AND trans_type ='T' then -total else 0 end) as gift_card_total,
sum(case when trans_subtype='TC' AND trans_type ='T' then -total else 0 end) as paper_gift_total,
sum(case when trans_subtype='MI' AND trans_type ='T' then -total else 0 end) as instore_charge_total,
sum(case when department='994' then -total else 0 end) as paid_out_total,
sum(case when trans_subtype='IC' AND trans_type ='T' then -total else 0 end) as store_coupon_total,
sum(case when trans_subtype='CP' OR trans_subtype='MC' AND trans_type ='T' then -total else 0 end) as mfg_coupon_total
FROM core_trans.dlog_90_view
WHERE tdate BETWEEN ? AND ?;";

$args = array($dDiffStart,$dDiffEnd);

$prep = $dbc->prepare_statement($total_sales);
$result = $dbc->exec_statement($prep,$args);
$row = $dbc->fetchArray($result);

$prep_tax = $dbc->prepare_statement($total_tax);
$result_tax = $dbc->exec_statement($prep_tax,$args);
$row_tax = $dbc->fetchArray($result_tax);

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
		<th> </th> <th>Store Totals</th>
		</tr>";

$echo_str = "";
//$echo_str = "<tr><th>".$result."</th></tr>"."<tr><th>".$result_l1."</th></tr>"."<tr><th>".$result_l2."</th></tr>";

$row_names = array("Department Sales Totals", "Sales Tax", "Meals Tax", "Member Payments", "Charge Payments",
				"Gift Cards Sold", "Paid In", "Member 2%", "Member 10%", "Member 15%", "Staff 15%",
				"Staff 17%", "Staff 23%", "Senior Discout", "Food For All Disc", "Credit Card Total", "Debit Card Total", "SNAP Total",
				"SNAP Cash Total", "Gift Card Total", "Paper Gift Total", "In Store Charge Total",
				"Paid Out Total", "Store Coupon Total", "Manufactures Coupon Total");
$row[1] = $row_tax[1];
$row[2] = $row_tax[0];

if($result) {
	for($i = 0; $i <count($row_names); $i++) {
		$echo_str .= "<tr><th>".$row_names[$i]."</th><th>".$row[$i]."</th></tr>";
	}
}
/*
$echo_str .= "<tr><th>Net Sales</th><th>".$row[0]."</th><th>".$row1[0]."</th><th>".$row2[0]."</th></tr>";
$echo_str .= "<tr><th>Sales Tax</th><th>".$row["sales_tax_total"]."</th><th>".$row1["sales_tax_total"]."</th><th>".$row2["sales_tax_total"]."</th></tr>";
$echo_str .= "<tr><th>Meals Tax</th><th>".$row["meals_tax_total"]."</th><th>".$row1["meals_tax_total"]."</th><th>".$row2["meals_tax_total"]."</th></tr>";
$echo_str .= "<tr><th>Equity Payments</th><th>".$row["member_payment_total"]."</th><th>".$row1["member_payment_total"]."</th><th>".$row2["member_payment_total"]."</th></tr>";
$echo_str .= "<tr><th>Cash Total</th><th>".$row["cash_total"]."</th><th>".$row1["cash_total"]."</th><th>".$row2["cash_total"]."</th></tr>";
$echo_str .= "<tr><th>Check Total</th><th>".$row["check_total"]."</th><th>".$row1["check_total"]."</th><th>".$row2["check_total"]."</th></tr>";
$echo_str .= "<tr><th>Debit Total</th><th>".$row["debit_total"]."</th><th>".$row1["debit_total"]."</th><th>".$row2["debit_total"]."</th></tr>";
$echo_str .= "<tr><th>SNAP Total</th><th>".$row["snap_total"]."</th><th>".$row1["snap_total"]."</th><th>".$row2["snap_total"]."</th></tr>";
$echo_str .= "<tr><th>Credit Total</th><th>".$row["credit_total"]."</th><th>".$row1["credit_total"]."</th><th>".$row2["credit_total"]."</th></tr>";
$echo_str .= "<tr><th>Mfg Coupon Total</th><th>".$row["mfg_coupon_total"]."</th><th>".$row1["mfg_coupon_total"]."</th><th>".$row2["mfg_coupon_total"]."</th></tr>";
$echo_str .= "<tr><th>Str Coupon Total</th><th>".$row["store_coupon_total"]."</th><th>".$row1["store_coupon_total"]."</th><th>".$row2["store_coupon_total"]."</th></tr>";
$echo_str .= "<tr><th>Gift Card Total</th><th>".$row["gift_card_total"]."</th><th>".$row1["gift_card_total"]."</th><th>".$row2["gift_card_total"]."</th></tr>";
$echo_str .= "<tr><th>Gift Sold Total</th><th>".$row["gift_sold_number"]."</th><th>".$row1["gift_sold_number"]."</th><th>".$row2["gift_sold_number"]."</th></tr>";
*/
echo $echo_str;


echo "</table>";


//echo $hourlySalesQ;

/*
$sum = 0;
$prep = $dbc->prepare_statement($hourlySalesQ);
$result = $dbc->exec_statement($prep,$args);
echo "<table cellspacing=0 cellpadding=4 border=1>";
$minhour = 24;
$maxhour = 0;
$acc = array();
$sums = array();
if (!isset($_REQUEST['weekday'])){
	while($row=$dbc->fetch_row($result)){
		$hour = (int)$row[3];
		$date = $row[1]."/".$row[2]."/".$row[0];
		if (!isset($acc[$date])) $acc[$date] = array();
		if ($hour < $minhour) $minhour = $hour;
		if ($hour > $maxhour) $maxhour = $hour;
		$acc[$date][$hour] = $row[4];
		if (!isset($sums[$hour])) $sums[$hour] = 0;
		$sums[$hour] += $row[4];
	}
}
else {
	$days = array('','Sun','Mon','Tue','Wed','Thu','Fri','Sat');
	while($row = $dbc->fetch_row($result)){
		$hour = (int)$row[1];
		$date = $days[$row[0]];
		if (!isset($acc[$date])) $acc[$date] = array();
		if (!isset($sums[$hour])) $sums[$date] = 0;	// Correct?
		if ($hour < $minhour) $minhour = $hour;
		if ($hour > $maxhour) $maxhour = $hour;
		$acc[$date][$hour] = $row[2];
		if (!isset($sums[$hour])) $sums[$hour]=0;
		$sums[$hour] += $row[2];
	}
}
echo "<tr><th>".(isset($_REQUEST['weekday'])?'Day':'Date')."</th>";
foreach($acc as $date=>$data){
	echo "<th>";
	echo $date;
	echo "</th>";
}
echo "<td style='text-align:right; font-weight:bold;'>Totals</td></tr>";

for($i=$minhour;$i<=$maxhour;$i++){
	echo "<tr>";
	echo "<td>";
	if ($i < 12) echo $i."AM";
	elseif($i==12) echo $i."PM";
	else echo ($i-12)."PM";
	echo "</td>";
	foreach($acc as $date=>$data){
		if (isset($data[$i])){
			if (isset($_REQUEST['excel']))
				printf("<td>%.2f</td>",$data[$i]);
			else
				echo "<td style='text-align:right;'>" . number_format($data[$i],2);
			if (!isset($sums[$i])) $sums[$i] = 0;
			if (!isset($sums[$date])) $sums[$date]=0;
			$sums[$date] += $data[$i];
		}
		else
			echo "<td>&nbsp;</td>";
	}
	if (isset($_REQUEST['excel']))
		printf("<td>%.2f</td>",$sums[$i]);
	else {
		$item = (isset($sums[$i])) ? number_format($sums[$i],2) : ' &nbsp; ';
		echo "<td style='text-align:right;'>" . $item . "</td>";
	}
	echo "</tr>";
}
$sum=0;
echo "<tr><td>Totals</td>";
foreach($acc as $date=>$data){
	if (isset($_REQUEST['excel']))
		printf("<td>%.2f</td>",$sums[$date]);
	else
		echo "<td style='text-align:right;'>" . number_format($sums[$date],2);
	$sum += $sums[$date];
}
// Grand total, in the table.
if (isset($_REQUEST['excel']))
	printf("<td>%.2f</td></tr>",$sum);
else
	echo "<td style='text-align:right;'>" . number_format($sum,2) . '</td></tr>';
// Cell originally set to empty.  Why?
//echo "<td>&nbsp;</td></tr>";

echo "</table>";

// Grand total, below the table.
if (isset($_REQUEST['excel']))
	echo "<p />Total: $sum";
else
	echo "<p />Total: " . number_format($sum,2);
	*/
?>
