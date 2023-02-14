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

class DiscountsReport extends FannieReportPage {

    public $description = '[Discounts Reports] lists member percentage discounts by member type for a
        a given date range.';
    public $report_set = 'Membership';

    protected $report_headers = array('Type', 'Total');
    protected $title = "Fannie : Discounts Report";
    protected $header = "Discount Report";
    protected $required_fields = array('date1', 'date2');

    public function calculate_footers($data)
    {
        $sum = 0;
        foreach($data as $row) {
            $sum += $row[1];
        }
        //rounding error check

        return array('Total', sprintf('%.2f', $sum));
    }

    public function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));

        $d1 = FormLib::get('date1', date('Y-m-d'));
        $d2 = FormLib::get('date2', date('Y-m-d'));
        $store = FormLib::get('store');

        $dlog = DTransactionsModel::selectDlog($d1,$d2);
        $store_id = DTrans::isStoreID($store, 'd');

        if (\FannieConfig::factory()->get('FANNIE_COOP_ID') == 'FranklinCoop') {
            $startDate = $d1.' 00:00:00';
            $endDate = $d2.' 23:59:59 ';
            $store_id_fill = str_replace('?',$store,$store_id);
            $sqlQ = "SELECT 'Working Member' as memDesc,
            sum(case when d.upc='DISCOUNT'  and d.percentDiscount >= m.discount and m.staff = 0 and m.ssi = 0 and m.discount >1
                then -d.unitPrice* (m.discount/d.percentDiscount) else 0 end) as total
            FROM core_trans.dlog_90_view d
            LEFT JOIN core_op.memtype m on d.memType = m.memtype
            WHERE d.`tdate` BETWEEN '{$startDate}' AND '{$endDate}' AND {$store_id_fill}
            UNION ALL
            SELECT 'Staff' as memDesc,
            sum(case when d.upc='DISCOUNT' and m.staff = 1 then -d.unitPrice*(m.discount/d.percentDiscount) else 0 end) as total
            FROM core_trans.dlog_90_view d
            LEFT JOIN core_op.memtype m on d.memType = m.memtype
            WHERE d.`tdate` BETWEEN '{$startDate}' AND '{$endDate}'	AND {$store_id_fill}
            UNION ALL
            SELECT 'Food for All' as memDesc, 
                sum(case when d.upc='DISCOUNT' and m.ssi = 1 then -d.unitPrice* (m.discount/d.percentDiscount) else 0 end) as total
            FROM core_trans.dlog_90_view d
            LEFT JOIN core_op.memtype m on d.memType = m.memtype
            WHERE d.`tdate` BETWEEN '{$startDate}' AND '{$endDate}' AND {$store_id_fill}
            UNION ALL
            SELECT 'Senior' as memDesc,
                sum(case when d.upc='DISCOUNT' and d.percentDiscount >0  then -d.unitPrice*((d.percentDiscount-m.discount)/d.percentDiscount) else 0 end) as total
            FROM core_trans.dlog_90_view d
            LEFT JOIN core_op.memtype m on d.memType = m.memtype
            WHERE d.`tdate` BETWEEN ? AND ? AND {$store_id}
            UNION ALL
            SELECT 'Total' as memDesc,
            sum(case when d.upc='DISCOUNT' then -d.unitPrice else 0 end) as total
            FROM core_trans.dlog_90_view d
            LEFT JOIN core_op.memtype m on d.memType = m.memtype
            WHERE d.`tdate` BETWEEN '{$startDate}' AND '{$endDate}' AND {$store_id_fill}";
        } else {
            $sqlQ = "SELECT m.memDesc,
                    SUM(total) AS total 
            FROM $dlog AS d
            LEFT JOIN memtype AS m ON d.memType=m.memtype
            WHERE d.upc='DISCOUNT'
            AND tdate BETWEEN ? AND ? AND $store_id
            GROUP BY m.memDesc
            ORDER BY m.memDesc";
        }


        $query = $dbc->prepare($sqlQ);
        $result = $dbc->execute($query, array($d1.' 00:00:00', $d2.' 23:59:59',$store));

        $data = array();
        
        if (\FannieConfig::factory()->get('FANNIE_COOP_ID') == 'FranklinCoop') {
            $total = 0;
            $sumTotal =0;
            while ($row = $dbc->fetchRow($result)) {
                if($row['memDesc'] === 'Total') {
                    $total = $row['total'];
                } else {
                    $data[] = $this->rowToRecord($row);
                    $sumTotal += number_format($row['total'], 2, '.', '');
                }
            }
            //check for rounding errors in total
            $diff = $total - $sumTotal;
            if ($diff !=0) { //this will remove the extra cent from the senior discount.
                $data[3][1] += $diff;
                $data[3][1] = sprintf('%.2f', $data[3][1]);
            }
        } else {
            while ($row = $dbc->fetchRow($result)) {
                $data[] = $this->rowToRecord($row);
            }
        }

        return $data;
    }

    private function rowToRecord($row)
    {
        return array(
            $row['memDesc'],
            sprintf('%.2f', $row['total']),
        );
    }

    public function form_content()
    {
        $lastMonday = "";
        $lastSunday = "";
        $store = FormLib::storePicker();
        $ts = mktime(0,0,0,date("n"),date("j")-1,date("Y"));
        while($lastMonday == "" || $lastSunday == "") {
            if (date("w",$ts) == 1 && $lastSunday != "") {
                $lastMonday = date("Y-m-d",$ts);
            } elseif(date("w",$ts) == 0) {
                $lastSunday = date("Y-m-d",$ts);
            }
            $ts = mktime(0,0,0,date("n",$ts),date("j",$ts)-1,date("Y",$ts));    
        }

        ob_start();
        ?>
        <form action=DiscountsReport.php method=get>
            <div class="col-sm-4">
                <div class="form-group">
                    <label>Date Start</label>
                    <input type=text id=date1 name=date1 class="form-control date-field" />
                </div>
                <div class="form-group">
                    <label>Date End</label>
                    <input type=text id=date2 name=date2 class="form-control date-field" />
                </div>
                <div class="form-group">
                    <label>Store</label>
                    <?php echo $store['html'] ?>
                </div>
                <p>
                    <label>Excel <input type=checkbox name=excel value="xls" /></label>
                </p>
                <p>
                    <button type=submit name=submit class="btn btn-default btn-core">Submit</button>
                    <button type=reset name=reset class="btn btn-default btn-reset">Start Over</button>
                </p>
            </div>
            <div class="col-sm-4">
                <?php echo FormLib::date_range_picker(); ?>
            </div>
        </form>
        <?php

        return ob_get_clean();
    }

    public function helpContent()
    {
        return '<p>
            List member discounts for a given range. These discounts are 
            transaction-wide, percentage discounts associated with a
            member (or customer) account.
            </p>';
    }

    public function unitTest($phpunit)
    {
        $data = array('memDesc'=>'test', 'total'=>1);
        $phpunit->assertInternalType('array', $this->rowToRecord($data));
        $phpunit->assertInternalType('array', $this->calculate_footers($this->dekey_array(array($data))));
    }

}

FannieDispatch::conditionalExec();

