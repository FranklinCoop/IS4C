<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
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

class AttributeSaleReport extends FannieReportPage 
{
    public $description = '[Attribute Sales Repots] Shows sales and % of sales based on product attributes. e.g. organic, gulten free.'; 
    public $report_set = 'Sales Reports';
    public $themed = true;
    protected $new_tablesorter = true;

    protected $title = "Fannie : Attribute Sale Report";
    protected $header = "Attribute Sales Report";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = true;
    protected $sortable = false;
    protected $no_sort_but_style = true;

    protected $report_headers = array('Department','Total','Local Total', 'Organic Total','Non GMO Total', 'Gluten Free Total', 'Traitor Brands Total');
    protected $required_fields = array('date1','date2');

    public function preprocess()
    {
        parent::preprocess();

        return true;
    }

    function report_description_content() {
        return(array('<p></p>'));
    }


    function fetch_report_data()
    {
        global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
            $FANNIE_COOP_ID;
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $date1 = $this->form->date1;
        $date2 = $this->form->date2;
        $dates = array();
        $dates[] = $date1 . ' 00:00:00';
        $dates[] = $date2 . ' 23:59:59';
        $store = FormLib::get('store');
        $dates[] = $store;
        $data = array();

        $dlog = DTransactionsModel::selectDlog($date1,$date2);
        $salesQ = $dbc->prepare("SELECT 
            n.super_name,
            sum(d.total) as Dept_Total,
            sum(case when (1<<(f.bit_number-1)) & p.numflag AND f.description=\"Local\" then d.total else 0 end) as Local_Total,
            sum(case when (1<<(f.bit_number-1)) & p.numflag AND f.description=\"Organic\"  then d.total else 0 end) as Organic_Total,
            sum(case when (1<<(f.bit_number-1)) & p.numflag AND f.description=\"Non-GMO\" then d.total else 0 end) as NonGMO_Total,
            sum(case when (1<<(f.bit_number-1)) & p.numflag AND f.description=\"Gluten Free\" then d.total else 0 end) as Gluten_Free_Total,
            sum(case when (1<<(f.bit_number-1)) & p.numflag AND f.description=\"Traitor Brands\" then d.total else 0 end) as Traitor_Brand_Total
            FROM $dlog as d
            LEFT JOIN {$FANNIE_OP_DB}.superdepts s on s.dept_ID = d.department
            LEFT JOIN {$FANNIE_OP_DB}.superDeptNames n on n.superID = s.superID
            LEFT JOIN {$FANNIE_OP_DB}.products p on p.upc = d.upc,
            {$FANNIE_OP_DB}.prodFlags f
            WHERE d.tdate BETWEEN ? AND ?
                AND " . DTrans::isStoreID($store, 'd') . "
                AND d.trans_type = 'I'
            GROUP BY n.super_name ORDER BY n.super_name");
        $salesR = $dbc->execute($salesQ,$dates);
        $report = array();
        $storeTotals = array();
        while($salesW = $dbc->fetch_row($salesR)){
            $record = array($salesW['super_name'],$salesW['Local_Total'],$salesW['Organic_Total'],$salesW['NonGMO_Total'],$salesW['Gluten_Free_Total'],$salesW['Traitor_Brand_Total']);
            $storeTotals['Total'] += $salesW['Local_Total'];
            $report[] = $record;
        }
        $data[] = $report;

        return $data;
    }

    function calculate_footers($data)
    {
        return array();
    }

    function form_content()
    {
        $store = FormLib::storePicker();
        return <<<HTML
        <form action=AttributeSaleReport.php method=get>
        <div class="form-group">
            <label>
                Start Date
            </label>
            <input type=text id=date1 name=date1 
                class="form-control date-field" />
        </div>
        <div class="form-group">
            <label>
                End Date
            </label>
            <input type=text id=date2 name=date2 
                class="form-control date-field" />
        </div>
        <div class="form-group">
            <label>Store</label>
            {$store['html']}
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
HTML;
    }

    public function helpContent()
    {
        return '<p>
            This report lists the four major categories of transaction
            information for a given day: tenders, sales, discounts, and
            taxes.
            </p>
            <p>
            Tenders are payments given by customers such as cash or
            credit cards. Sales are items sold to customers. Discounts
            are percentage discounts associated with an entire
            transaction instead of individual items. Taxes are sales
            tax collected.
            </p>
            <p>
            Tenders should equal sales minus discounts plus taxes.
            </p>
            <p>
            Equity and transaction statistics are provided as generally
            useful information.
            </p>';
    }
}

FannieDispatch::conditionalExec(false);

