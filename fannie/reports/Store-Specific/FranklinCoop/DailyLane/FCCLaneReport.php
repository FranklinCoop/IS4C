<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

class FCCLaneReport extends FannieReportPage 
{
    public $description = '[Daily Lane Totals] Lane Totals for a day.'; 
    public $report_set = 'Sales Reports';

    protected $title = "Fannie : Daily Lane";
    protected $header = "Lane Totals Report";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = True;
    protected $sortable = False;

    protected $report_headers = array('','Lane 1','Lane 2', 'Lane 3', 'Lane Totals');
    protected $required_fields = array('date1');

	function report_description_content() {
		return(array('<p></p>'));
	}

	function fetch_report_data()
    {
		global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
			$FANNIE_COOP_ID;
        $dbc = FannieDB::get($FANNIE_OP_DB);
		$d1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$dates = array($d1.' 00:00:00',$d1.' 23:59:59');
		$data = array();

		if ( isset($FANNIE_COOP_ID) && $FANNIE_COOP_ID == 'WEFC_Toronto' )
			$shrinkageUsers = " AND d.card_no not between 99900 and 99998";
		else
			$shrinkageUsers = "";

		$dlog = DTransactionsModel::selectDlog($d1);

		$total_sales = $dbc->prepare("SELECT 
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
            WHERE tdate BETWEEN ? AND ?;");
        $totalSalesR = $dbc->execute($total_sales,$dates);
        $totalSalesW = $dbc->fetch_row($totalSalesR);

	    $lane1Sales = $dbc->prepare("SELECT
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
			WHERE register_no='1' and tdate BETWEEN ? AND ?;");
        $lane1SalesR = $dbc->execute($lane1Sales,$dates);
        $lane1SalesW = $dbc->fetch_row($lane1SalesR); 

        $lane2Sales = $dbc->prepare("SELECT
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
			WHERE register_no='2' and tdate BETWEEN ? AND ?;");
        $lane2SalesR = $dbc->execute($lane2Sales,$dates);
        $lane2SalesW = $dbc->fetch_row($lane2SalesR);

        $lane3Sales = $dbc->prepare("SELECT
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
			WHERE register_no='3' and tdate BETWEEN ? AND ?;");
        $lane3SalesR = $dbc->execute($lane3Sales,$dates);
        $lane3SalesW = $dbc->fetch_row($lane3SalesR);
        $report = array();  

        $row_names = array("Cash Total", "Checks (# of)", "Checks (amount)", "GIFT CARD Sold", "MEMBER PAYMENT",
				"CHARGE PAYMENT TOTAL", "R/A: Other", "CREDIT", "DEBIT", "SNAP: Food", "SNAP: Cash",
				"GIFT CARD Redeemed", "Total CARD MEDIA", "Paper Gift Redeemed","STORE CHARGE", "PAIDOUT",
				"STORE COUPON", "VENDOR COUPONS");

		for($i = 0; $i < count($row_names); $i++) {
			$record = array($row_names[$i], $lane1SalesW[$i], $lane2SalesW[$i], $lane3SalesW[$i], $totalSalesW[$i]);
			$report[] = $record;
		}
		$data[] = $report;
		
		return $data;
	}

	function calculate_footers($data)
    {
		/*switch($this->multi_counter){
		case 1:
			$this->report_headers[0] = 'Tenders';
			break;
		case 2:
			$this->report_headers[0] = 'Sales';
			break;
		case 3:
			$this->report_headers[0] = 'Discounts';
			break;
		case 4:
			$this->report_headers[0] = 'Tax';
			break;
		case 5:
			$this->report_headers = array('Type','Trans','Items','Avg. Items','Amount','Avg. Amount');
			return array();
			break;
		case 6:
			$this->report_headers = array('Mem#','Equity Type', 'Amount');
			break;
		}
		$sumQty = 0.0;
		$sumSales = 0.0;
		foreach($data as $row){
			$sumQty += $row[1];
			$sumSales += $row[2];
		}
		return array(null,$sumQty,$sumSales);*/
		return array();
	}

	function form_content()
    {
        ob_start();
        ?>
        <form action=FCCLaneReport.php method=get>
        <div class="form-group">
            <label>
                Date
                (<a href="../GeneralRange/">Range of Dates</a>)
            </label>
            <input type=text id=date1 name=date1 
                class="form-control date-field" required />
        </div>
        <div class="form-group">
            <label>List Sales By</label>
            <select name="sales-by" class="form-control">
                <option>Super Department</option>
                <option>Department</option>
                <option>Sales Code</option>
            </select>
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
