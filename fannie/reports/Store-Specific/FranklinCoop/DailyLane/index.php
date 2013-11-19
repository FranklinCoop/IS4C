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
$header = "Daily Lane Totals Report";
$page_title = "Fannie : Daily Lane Totals";
include($FANNIE_ROOT.'src/header.html');
include($FANNIE_ROOT.'src/mysql_connect.php');
$options = "<option value=-1 selected>All</option>";
$prep = $dbc->prepare_statement("SELECT superID,super_name FROM superDeptNames 
		WHERE superID > 0");
$res = $dbc->exec_statement($prep);
while($row = $dbc->fetch_row($res))
	$options .= sprintf("<option value=%d>%s</option>",$row[0],$row[1]);
?>
<script src="../../../../src/CalendarControl.js"
        type="text/javascript"></script>

<form name='addBatch' action = 'DailyLaneTotalsReport.php' method='POST'>
<table><tr><td>Start Date</td><td>End Date</td></tr>
<tr>
     <td><input name="startDate" onfocus="this.value='';showCalendarControl(this);" type="text"></td>
     <td><input name="endDate" onfocus="this.value='';showCalendarControl(this);" type="text"></td>
</tr><tr>
     <td><input type =submit name=submit value ="Get Report"></td></tr>
</table>
<!-- <a href=DailyLaneTotalsReport.php>Per-department sales by hour</a> -->

</body>
</html>
<?php
include($FANNIE_ROOT.'src/footer.html');
?>
