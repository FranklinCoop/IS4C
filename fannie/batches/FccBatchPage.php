<?php
/*******************************************************************************

    Copyright 2011 Whole Foods Co-op

    This file is part of Fannie.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/
include('../config.php');
include($FANNIE_ROOT.'classlib2.0/FanniePage.php');
include($FANNIE_ROOT.'classlib2.0/data/FannieDB.php');

class FccBatchPage extends FanniePage {
	protected $title = 'Fannie - Fcc';
	protected $header = 'Sales Batches';

	function body_content(){
		$data = get_batches();
		$ret = "<table border =1>
				<tr> 
				<th>Batch Number</th>
				<th>Batch Name</th>
				<th>Batch Run</th>
				</tr>";
		while($fetchW = $dbc->fetch_array($data) {
			$ret .="<tr>";\
			$ret .="<td>" . $fetchW[0] . "</td>";
			$ret .="<td>" . $fetchW[1] . "</td>";
			$ret .="<td>" . $fetchW[2] . "</td>";
			$ret .="</tr>"
		}
		$ret "</table>""
		return $ret;
	}

	function get_batches() {
		global $FANNIE_OP_DB;
		$dbc = FannieDB::get($FANNIE_OP_DB);

		$query = "SELECT * FROM fcc_batch_headers";
		$args = array();

		$prep = $dbc->prepare_statement($query);
		$result = $dbc->exec_statement($query,$args);

		return $result;
	}
}

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)){
	$obj = new FccBatchPage();
	$obj->draw_page();
}
?>
