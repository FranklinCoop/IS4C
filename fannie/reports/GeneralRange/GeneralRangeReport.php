<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../classlib2.0/FannieAPI.php');
}

class GeneralRangeReport extends FannieReportPage 
{
    public $description = '[General Range Report] lists tenders, sales, discounts, and taxes for a given 
                    range of dates.';
    public $report_set = 'Sales Reports';
    public $themed = true;

    protected $title = "Fannie : General Range Report";
    protected $header = "General Range Report";
    protected $report_cache = 'none';
    protected $grandTTL = 1;
    protected $multi_report_mode = true;
    protected $sortable = false;
    protected $no_sort_but_style = true;

    protected $report_headers = array('Desc','Qty','Amount');
    protected $required_fields = array('date1', 'date2');

    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->setDefaultDB($this->config->get('OP_DB'));
        $d1 = $this->form->date1;
        $d2 = $this->form->date2;
        $store = FormLib::get('store');
        $dates = array($d1.' 00:00:00', $d2.' 23:59:59',$store);
        $data = array();

        $reconciliation = array(
            'Tenders' => 0.0,
            'Sales' => 0.0,
            'Discounts' => 0.0,
            'Tax' => 0.0,
        );

        $dlog = DTransactionsModel::selectDlog($d1,$d2);
        $tenderQ = $dbc->prepare("
            SELECT TenderName,
                COUNT(d.total) AS num,
                SUM(d.total) as total
            FROM $dlog AS d
                INNER JOIN tenders as t ON d.trans_subtype=t.TenderCode
            WHERE d.tdate BETWEEN ? AND ?
                AND d.trans_type = 'T'
                AND d.total <> 0
                AND " . DTrans::isStoreID($store, 'd') . "
            GROUP BY t.TenderName ORDER BY TenderName");
        $tenderR = $dbc->execute($tenderQ,$dates);
        $report = array();
        while ($tenderW = $dbc->fetch_row($tenderR)) {
            $record = array($tenderW['TenderName'],$tenderW[1],
                    sprintf('%.2f',$tenderW['total']));
            $report[] = $record;
            $reconciliation['Tenders'] += $tenderW['total'];
        }
        $data[] = $report;

        $salesQ = '';
        switch (FormLib::get('sales-by')) {
            case 'Department':
                $salesQ = '
                    SELECT t.dept_name AS category,
                        SUM(d.quantity) AS qty,
                        SUM(d.total) AS total
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN departments AS t ON d.department=t.dept_no
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\'
                        AND d.tdate BETWEEN ? AND ?
                        AND ' . DTrans::isStoreID($store, 'd') . '
                    GROUP BY t.dept_name
                    ORDER BY t.dept_name'; 
                break;
            case 'Sales Code':
                $salesQ = '
                    SELECT t.salesCode AS category,
                        SUM(d.quantity) AS qty,
                        SUM(d.total) AS total
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN departments AS t ON d.department=t.dept_no
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\'
                        AND d.tdate BETWEEN ? AND ?
                        AND ' . DTrans::isStoreID($store, 'd') . '
                    GROUP BY t.salesCode
                    ORDER BY t.salesCode'; 
                break;
            case 'Super Department':
            default:
                $salesQ = '
                    SELECT m.super_name AS category,
                        SUM(d.quantity) AS qty,
                        SUM(d.total) AS total
                    FROM ' . $dlog . ' AS d
                        LEFT JOIN MasterSuperDepts AS m ON d.department=m.dept_ID
                    WHERE d.department <> 0
                        AND d.trans_type <> \'T\'
                        AND d.tdate BETWEEN ? AND ?
                        AND ' . DTrans::isStoreID($store, 'd') . '
                    GROUP BY m.super_name
                    ORDER BY m.super_name';
                break;
        }
        $salesP = $dbc->prepare($salesQ);
        $salesR = $dbc->execute($salesP,$dates);
        $report = array();
        while ($salesW = $dbc->fetch_row($salesR)) {
            $record = array($salesW['category'],
                    sprintf('%.2f',$salesW['qty']),
                    sprintf('%.2f',$salesW['total']));
            $report[] = $record;
            $reconciliation['Sales'] += $salesW['total'];
        }
        $data[] = $report;
        // member type hack added for weird istuation at fcc.
        $report = array();
        if (\FannieConfig::factory()->get('FANNIE_COOP_ID') == 'FranklinCoop') {
            $discounts = $this->calculateDiscounts($dbc,$dlog,$dates);
            for ($key=0;$key<sizeof($discounts);$key++) {
                $report[] = $discounts[$key];
                $reconciliation['Discounts'] += $discounts[$key][2];
            }
        } else {
            $discQ = $dbc->prepare("SELECT m.memDesc, SUM(d.total) AS Discount,count(*)
                FROM $dlog d 
                    INNER JOIN memtype m ON d.memType = m.memtype
                WHERE d.tdate BETWEEN ? AND ?
                    AND " . DTrans::isStoreID($store, 'd') . "
                   AND d.upc = 'DISCOUNT'{$shrinkageUsers}
                AND total <> 0
                GROUP BY m.memDesc ORDER BY m.memDesc");
            $discR = $dbc->execute($discQ,$dates);
            //$report = array();
            while($discW = $dbc->fetch_row($discR)){
                $record = array($discW['memDesc'],$discW[2],sprintf('%.2f', $discW[1]));
                $report[] = $record;
                $reconciliation['Discounts'] += $discW['Discount'];
            }
        }
        $data[] = $report;

        $report = array();
        $trans = DTransactionsModel::selectDTrans($d1);
        $lineItemQ = $dbc->prepare("
            SELECT description,
                SUM(regPrice) AS ttl
            FROM $trans AS d
            WHERE datetime BETWEEN ? AND ?
                AND d.upc='TAXLINEITEM'
                AND " . DTrans::isNotTesting('d') . "
                AND " . DTrans::isStoreID($store, 'd') . "
            GROUP BY d.description
        ");
        $lineItemR = $dbc->execute($lineItemQ, $dates);
        while ($lineItemW = $dbc->fetch_row($lineItemR)) {
            $record = array($lineItemW['description'] . ' (est. owed)', sprintf('%.2f', $lineItemW['ttl']));
            $report[] = $record;
        }

        $taxSumQ = $dbc->prepare("
            SELECT SUM(total) AS tax_collected
            FROM $dlog as d 
            WHERE d.tdate BETWEEN ? AND ?
                AND (d.upc = 'tax')
                AND " . DTrans::isStoreID($store, 'd') . "
            GROUP BY d.upc");
        $taxR = $dbc->execute($taxSumQ,$dates);
        while ($taxW = $dbc->fetch_row($taxR)) {
            $record = array('Total Tax Collected',round($taxW['tax_collected'],2));
            $report[] = $record;
            $reconciliation['Tax'] = $taxW['tax_collected'];
        }
        $data[] = $report;

        $report = array();
        foreach ($reconciliation as $type => $amt) {
            $report[] = array(
                $type,
                sprintf('%.2f', $amt),
            );
        }
        $data[] = $report;

        $ret = preg_match_all("/[0-9]+/",$this->config->get('EQUITY_DEPARTMENTS'),$depts);
        if ($ret != 0) {
            /* equity departments exist */
            $depts = array_pop($depts);
            $dlist = "(";
            foreach ($depts as $d) {
                $dates[] = $d; // add query param
                $dlist .= '?,';
            }
            $dlist = substr($dlist,0,strlen($dlist)-1).")";

            $equityQ = $dbc->prepare("
                SELECT d.card_no,
                    t.dept_name, 
                    SUM(total) AS total 
                FROM $dlog as d
                    LEFT JOIN departments as t ON d.department = t.dept_no
                WHERE d.tdate BETWEEN ? AND ?
                    AND d.department IN $dlist
                GROUP BY d.card_no, t.dept_name ORDER BY d.card_no, t.dept_name");
            $equityR = $dbc->execute($equityQ,$dates);
            $report = array();
            while ($equityW = $dbc->fetch_row($equityR)) {
                $record = array($equityW['card_no'],$equityW['dept_name'],
                        sprintf('%.2f',$equityW['total']));
                $report[] = $record;
            }
            $data[] = $report;
        }
        
        return $data;
    }

    function calculate_footers($data)
    {
        switch($this->multi_counter){
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
            $this->report_headers = array('Tax', 'Amount');
            $sumTax = 0.0;
            for ($i=0; $i<count($data)-1; $i++) {
                $sumTax += $data[$i][1];
            }
            return array('Total Sales Tax', sprintf('%.2f', $sumTax));
            break;
        case 5:
            $this->report_headers = array('Reconcile Totals', 'Amount');
            $ttl = 0.0;
            foreach ($data as $row) {
                $ttl += $row[1];
            }
            return array('Net', sprintf('%.2f', $ttl));
            break;
        case 6:
            $this->report_headers = array('Mem#','Equity Type', 'Amount');
            $sumSales = 0.0;
            foreach ($data as $row) {
                $sumSales += $row[2];
            }
            return array(null,null,$sumSales);
            break;
        }
        $sumQty = 0.0;
        $sumSales = 0.0;
        foreach($data as $row){
            $sumQty += $row[1];
            $sumSales += $row[2];
        }
        return array(null,$sumQty,$sumSales);
    }

    function form_content()
    {
        $start = date('Y-m-d',strtotime('yesterday'));
        $store = FormLib::storePicker();
        ob_start();
        ?>
        <form method=get>
        <div class="col-sm-5">
            <div class="form-group">
                <label>List Sales By</label>
                <select name="sales-by" class="form-control">
                    <option>Super Department</option>
                    <option>Department</option>
                    <option>Sales Code</option>
                </select>
            </div>
            <div class="form-group">
                <label>Store</label>
                <?php echo $store['html'] ?>
            </div>
            <div class="form-group">
                <label>Excel <input type=checkbox name=excel /></label>
            </div>
            <p>
            <button type=submit name=submit value="Submit"
                class="btn btn-default">Submit</button>
            </p>
        </div>
        <?php echo FormLib::standardDateFields(); ?>
        </form>
        <?php

        return ob_get_clean();
    }

    private function calculateDiscounts($dbc,$dlog,$args=array()){
        $discQ =$dbc->prepare("SELECT 
        sum(case 
            when t.upc='DISCOUNT'  and t.percentDiscount >= m.discount and m.staff = 0 and m.ssi = 0 and m.discount >1 then t.unitPrice* (m.discount/t.percentDiscount)
            else 0 end) as working_disc,
        sum(case 
            when t.upc='DISCOUNT'  and t.percentDiscount >= m.discount and m.staff = 0 and m.ssi = 0 and m.discount >1 then 1
            else 0 end) as working_ct,
        sum(case
            when t.upc='DISCOUNT' and m.staff = 1 then t.unitPrice*(m.discount/t.percentDiscount)
            else 0 end) as staff_disc,
        sum(case when t.upc='DISCOUNT' and m.staff = 1 then 1 else 0 end) as staff_ct,
        sum(case
            when t.upc='DISCOUNT' and m.ssi = 1 then t.unitPrice* (m.discount/t.percentDiscount)
            else 0 end) as food_for_all_disc,
        sum(case when t.upc='DISCOUNT' and m.ssi = 1 then 1 else 0 end) as food_for_all_ct,
        sum(case
            when t.upc='DISCOUNT' and t.percentDiscount-m.discount != 0  then t.unitPrice*((t.percentDiscount-m.discount)/t.percentDiscount)
            else 0 end) as seinorDisc,
        sum(case
            when t.upc='DISCOUNT' and t.percentDiscount-m.discount != 0  then 1 else 0 end) as seinorCT,
        sum(case when t.upc='DISCOUNT' then t.unitPrice else 0 end) as total_disc
            FROM $dlog t
            LEFT JOIN core_op.memtype m on t.memType = m.memtype
            WHERE t.`tdate` BETWEEN ? AND ? AND t.store_id=?");
        $discR = $dbc->execute($discQ, $args);
        
        $return = array();
        $row = $dbc->fetch_row($discR);
        
        $names = array("Working Member","Staff","Food for All","Senior Discount");

        $report = array();
        //correct rounding errors
        $newKey = 0;
        $info = 0;
        $discSum = 0; //sum of the calculated values
        $total = 0; //clean total to compare with callulated sum to check for diff errors.
        for($key=0;$key<=8;$key++) {
            if ($key != 8 && $key %2 === 0) {
                $info = number_format($row[$key], 2, '.', '');
                $discSum += $info;
            } elseif($key %2 != 0){
                $countTotal += $row[$key];
                $return[$newKey] = array($names[$newKey],$row[$key],$info);
                $newKey ++;
            } elseif ($key==8) {
                $total = number_format($row[$key], 2, '.', '');
                $diff = $total-$discSum;
                if ($diff != 0) { 
                    $correction = $return[3][2] + $diff;
                    $return[3][2] = number_format($correction, 2, '.', '');
                    $discSum += $diff;
                }
            }
        }
        //final error check returns values for troubleshooting.
        if ($discSum - $total !=0) {
            $return = array('Math ERR',$discSum,$return[4],'Math ERR','Math ERR');
        }

        return $return;
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
            </p>';
    }
}

FannieDispatch::conditionalExec();

