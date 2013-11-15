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

echo "<span style='font-weight:bold;'>Hourly Sales Report</span><br>";
echo "From $startDate to $endDate";
echo "<br />Super Department: ";
if($buyer == -1){
	echo "All";
} else {
	$sdQ = "SELECT super_name FROM superDeptNames WHERE superID = ?";
	$sdP = $dbc->prepare_statement($sdQ);
	$sdR = $dbc->exec_statement($sdP,array($buyer));
	$superDept = "";
	while($row = $dbc->fetch_row($sdR)){
		$superDept = $row['super_name'];
		echo $superDept;
		break;
	}
}

$dlog = select_dlog($startDate,$endDate);

$dbconn = ($FANNIE_SERVER_DBMS=='MSSQL')?'.dbo.':'.';

$total_sales = '';
$lane1_sales = '';
$lane2_sales = '';

$total_sales = "SELECT sum(total) as sales_total,
sum(case when description='MassSalesTax' then regPrice else 0 end) as sales_tax_total,
sum(case when description='StateAndLocalMealsTax' then regPrice else null end) as meals_tax_total,
sum(case when department='992' then total else null end) as member_payment_total,
sum(case when trans_subtype='CA' then total else null end) as cash_total,
sum(case when trans_subtype='CK' then total else null end) as check_total,
sum(case when trans_subtype='DC' then total else null end) as debit_total,
sum(case when trans_subtype='EF' then total else null end) as snap_total,
sum(case when trans_subtype='CC' then total else null end) as credit_total,
sum(case when trans_subtype='CP' then total else null end) as mfg_coupon_total,
sum(case when trans_subtype='IC' then total else null end) as store_coupon_total,
sum(case when trans_subtype='TC' then total else null end) as gift_card_total,
sum(case when upc='1930' then quantity else null end) as gift_sold_number
FROM core_trans.transarchive
WHERE (trans_type ='T' OR trans_type='C' OR department='992') and datetime BETWEEN ? AND ?;";

$lane1_sales = "SELECT sum(total) as sales_total,
sum(case when description='MassSalesTax' then regPrice else 0 end) as sales_tax_total,
sum(case when description='StateAndLocalMealsTax' then regPrice else null end) as meals_tax_total,
sum(case when department='992' then total else null end) as member_payment_total,
sum(case when trans_subtype='CA' then total else null end) as cash_total,
sum(case when trans_subtype='CK' then total else null end) as check_total,
sum(case when trans_subtype='DC' then total else null end) as debit_total,
sum(case when trans_subtype='EF' then total else null end) as snap_total,
sum(case when trans_subtype='CC' then total else null end) as credit_total,
sum(case when trans_subtype='CP' then total else null end) as mfg_coupon_total,
sum(case when trans_subtype='IC' then total else null end) as store_coupon_total,
sum(case when trans_subtype='TC' then total else null end) as gift_card_total,
sum(case when upc='1930' then quantity else null end) as gift_sold_number
FROM core_trans.transarchive
WHERE (trans_type ='T' OR trans_type='C' OR department='992') and register_no='1' and datetime BETWEEN ? AND ?;";

$lane2_sales = "SELECT sum(total) as sales_total,
sum(case when description='MassSalesTax' then regPrice else 0 end) as sales_tax_total,
sum(case when description='StateAndLocalMealsTax' then regPrice else null end) as meals_tax_total,
sum(case when department='992' then total else null end) as member_payment_total,
sum(case when trans_subtype='CA' then total else null end) as cash_total,
sum(case when trans_subtype='CK' then total else null end) as check_total,
sum(case when trans_subtype='DC' then total else null end) as debit_total,
sum(case when trans_subtype='EF' then total else null end) as snap_total,
sum(case when trans_subtype='CC' then total else null end) as credit_total,
sum(case when trans_subtype='CP' then total else null end) as mfg_coupon_total,
sum(case when trans_subtype='IC' then total else null end) as store_coupon_total,
sum(case when trans_subtype='TC' then total else null end) as gift_card_total,
sum(case when upc='1930' then quantity else null end) as gift_sold_number
FROM core_trans.transarchive
WHERE (trans_type ='T' OR trans_type='C' OR department='992') and register_no='2' and datetime BETWEEN ? AND ?;";

$args = array($dDiffStart,$dDiffEnd);

$prep = $dbc->prepare_statement($total_sales);
$result = $dbc->exec_statement($prep,$args);

$prep = $dbc->prepare_statement($lane1_sales);
$result_l1 = $dbc->exec_statement($prep,$args);
$prep = $dbc->prepare_statement($lane2_sales);
$result_l2 = $dbc->exec_statement($prep,$args);


echo "<table cellspacing=0 cellpadding=4 border=1>";

if (isset($_REQUEST['excel'])){
	  header('Content-Type: application/ms-excel');
	  header('Content-Disposition: attachment; filename="hourlySales.xls"');
}
else {
	if(isset($_REQUEST['weekday'])){
		 $weekday = $_REQUEST['weekday'];
	   echo "<br><a href=hourlySalesAuth.php?endDate=$endDate&startDate=$startDate&buyer=$buyer&weekday=$weekday&excel=yes>Click here to dump to Excel File</a>";
	}else{
	   echo "<br><a href=hourlySalesAuth.php?endDate=$endDate&startDate=$startDate&buyer=$buyer&excel=yes>Click here to dump to Excel File</a>";
	}
	echo " <a href='javascript:history.back();'>Back</a>";
}

if (isset($_REQUEST['excel'])){
	  header('Content-Type: application/ms-excel');
	  header('Content-Disposition: attachment; filename="hourlySales.xls"');
}
else {
	if(isset($_REQUEST['weekday'])){
		 $weekday = $_REQUEST['weekday'];
	   echo "<br><a href=hourlySalesAuth.php?endDate=$endDate&startDate=$startDate&buyer=$buyer&weekday=$weekday&excel=yes>Click here to dump to Excel File</a>";
	}else{
	   echo "<br><a href=hourlySalesAuth.php?endDate=$endDate&startDate=$startDate&buyer=$buyer&excel=yes>Click here to dump to Excel File</a>";
	}
	echo " <a href='javascript:history.back();'>Back</a>";
}

//echo "<table cellspacing=0 cellpadding=4 border=1>";
echo "<tr>
		<th> </th> <th>Store Totals</th> <th>Lane 1 Totals</th> <th>Lane 2 Totals</th>
		</tr>";

$echo_str = "";
$echo_str = "<tr><th>".$result."</th></tr>"."<tr><th>".$result_l1."</th></tr>"."<tr><th>".$result_l2."</th></tr>";

/*
$row = mysql_fetch_array($result, MYSQL_BOTH);
$row1 = mysql_fetch_array($result_l1, MYSQL_BOTH);
$row2 = mysql_fetch_array($result_l2, MYSQL_BOTH);
$echo_str .= "<tr><th>Net Sales</th><th>".$row["sales_total"]."</th><th>".$row1["sales_total"]."</th><th>".$row2["sales_total"]."</th></tr>";
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
$echo_str .= "<tr><th>Gift Card Total</th><th>".$row["gift_card_total"]."</th><th>".$row1["gift_card_total"]."</th><th>".$row2["sales_total"]."</th></tr>";
$echo_str .= "<tr><th>Gift Sold Total</th><th>".$row["gift_sold_number"]."</th><th>".$row1["gift_sold_number"]."</th><th>".$row2["sales_total"]."</th></tr>";
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
