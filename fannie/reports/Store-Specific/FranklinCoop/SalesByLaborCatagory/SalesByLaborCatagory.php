<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

include(dirname(__FILE__) . '/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}

class SalesByLaborCatagory extends FannieReportPage 
{
    public $description = '[Sales By Labor] FCC specfic sales totaled by labor catagory..'; 
    public $report_set = 'Sales Reports';

    protected $title = "Fannie : Sales By Labor Catagory Report";
    protected $header = "Sales By Labor Catagory Report";
    protected $report_cache = 'none';
    
    protected $sortable = false;
    protected $no_sort_but_style = true;
    protected $new_tablesorter = true;
    protected $multi_report_mode = false;

    protected $report_headers = array('Name','Total Sales');
    protected $required_fields = array('date1','date2');
  	


	function report_description_content() {
		return(array('<p>Sales by Labor Catagory Report</p>'));
	}

	function fetch_report_data()
    {
		global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
			$FANNIE_COOP_ID;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        //setup dates
		$d1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$d2 = FormLib::get_form_value('date2',date('Y-m-d'));
		$startDate = DateTime::createFromFormat('Y-m-d' ,$d1);
		$endDate = DateTime::createFromFormat('Y-m-d' ,$d2);
		//find the correct transaction log to use for the time frame.
		$dlog = DTransactionsModel::selectDLog($startDate->format('Y-m-d'),$endDate->format('Y-m-d'));

		$args = array($startDate->format('Y-m-d').' 00:00:00', $endDate->format('Y-m-d').' 23:59:59');
        $salesQ = $dbc->prepare("SELECT s.superID, l.store_id, sum(l.total) AS total FROM {$dlog} l
			JOIN core_op.superdepts s ON l.department = s.dept_ID
			WHERE tdate BETWEEN ? AND ?
			GROUP BY s.superID, l.store_ID");

        $salesR = $dbc->execute($salesQ, $args);

        /*******
        	We need to orginaze the report into these labor catqgories that unforntatly
        	are not stored in the database anywhere right now.

			Bakery Total      | Sales of GFM and MCC: superID =1 and store_id = 1 and 2
			Perishables Total | sales of GFM Perishables: superID = 6,7,8 or 9 and store_id = 1
			Bulk total        | Sales GFM Bulk: superID = ? and store_id = 1
			Dry Goods Total   | sales GFM Dry Goods: superID =3 and store_id = 1
			PFD Total         | sales GFM and MCC PFD: superID =2 and store_id = 1 and 2
			Wellness Total    | Sales Wellness: superID = 11,12,13 and store_id = 1
			GFM Total         | Sales GFM: store_id = 1
			MCC Total         | Sales MCC: store_id = 2

			The rows returned by the Query are formated:
			superID | store_id | total
        ********/

		// preformating the array
		$report = array(
			'bakery'      => array('Bakery Total',0),
			'perishables' => array('Perishables Total',0),
			'bulk'        => array('Bulk Total',0),
			'dry'         => array('Dry Goods Total',0),
			'pfd'         => array('PDF Total',0),
			'wellness'    => array('Wellness Total',0),
			'gfm'         => array('GFM Total',0),
			'mcc'         => array('MCC Total',0)
		);
        while($salesR = $dbc->fetchRow($budgetR)){
        	switch ($report['superID']) {
        		case 1: //Bakery
        			$report['bakery'][1] += $row['total'];
        			break;
        		case 2: //PFD
        			$report['pfd'][1] += $row['total'];
        			break;
        		case 3: //Dry Goods
        			if($row['store_ID'] == 1) {
        				$report['dry'][1] += $row['total'];
        			}
        			break;
        		case 4: //Bulk
        			if($row['store_ID'] == 1) {
        				$report['bulk'][1] += $row['total'];
        			}
        			break;
        		case 6: //Perishables
        		case 7:
        		case 8:
        		case 9:
        			if($row['store_ID'] == 1) {
        				$report['perishables'][1] += $row['total'];
        			}
        			break;
        		case 11://Wellness
        		case 12:
        		case 13:
        			if($row['store_ID'] == 1) {
        				$report['wellness'][1] += $row['total'];
        			}
        			break;
        		default:
        			if($row['store_ID'] == 1) {
        				$report['gfm'][1] += $row['total'];
        			} elseif($row['store_ID'] == 2) {
        				$report['mcc'][1] += $row['total'];
        			}
        			break;
        }

        }
        //$data[] = $report; // deal with the last value.

		return $report;
	}


	function calculate_footers($data)
    {
		return array();
	}


	function form_content()
    {

        ob_start();
        ?>
        <form action=SalesByLaborCatagory.php method=get>
        <div class="form-group">
            <?php echo FormLib::standardDateFields(); ?>
        </div>
        <div class="form-group">
            <label>Excel <input type=checkbox name=excel /></label>
        </div>

        <p>
        <button type=submit name=submit value="Submit"
            class="btn btn-default">Submit</button>
        </p>
        </form>
        <?php
        return ob_get_clean();
	}

}

FannieDispatch::conditionalExec(false);

?>
