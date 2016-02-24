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

class FCCSettlementReport extends FannieReportPage 
{
    public $description = '[Daily Settlement Report] Lane Totals for a day.'; 
    public $report_set = 'Sales Reports';

    protected $title = "Fannie : Daily Settlement";
    protected $header = "Settlement Report";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = True;
    protected $sortable = False;

    protected $report_headers = array('','Totals');
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

		$prep = $dbc->prepare($total_sales);
		$result = $dbc->execute($prep,$dates);
		$row = $dbc->fetch_row($result);

		$prep_tax = $dbc->prepare($total_tax);
		$result_tax = $dbc->execute($prep_tax,$dates);
		$row_tax = $dbc->fetch_row($result_tax);

		$record = array();

		$row_names = array("Department Sales Totals", "Sales Tax", "Meals Tax", "Member Payments", "Charge Payments",
				"Gift Cards Sold", "Paid In", "Member 2%", "Member 10%", "Member 15%", "Staff 15%",
				"Staff 17%", "Staff 23%", "Senior Discout", "Food For All Disc", "Credit Card Total", "Debit Card Total", "SNAP Total",
				"SNAP Cash Total", "Gift Card Total", "Paper Gift Total", "In Store Charge Total",
				"Paid Out Total", "Store Coupon Total", "Manufactures Coupon Total");
		$row[1] = $row_tax[1];
		$row[2] = $row_tax[0];

		for($i = 0; $i <count($row_names); $i++) {
			$record = array($row_names[$i], $row[$i]);
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
        <form action=FCCSettlementReport.php method=get>
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
