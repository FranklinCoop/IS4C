<?php
/*******************************************************************************

    Copyright 2022 Franklin Community Coop

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

class MonthlyMemberReport extends FannieReportPage 
{

    protected $title = "Fannie : FCC Monthly Member Report";
    protected $header = "FCC Monthly Member Report";
    protected $report_headers = array();
    protected $required_fields = array('smonth', 'syear');

    public $description = '[Member Status] Shows member statuses and counts';
    public $themed = true;
    public $report_set = 'Member reports';
    protected $sortable = False;

    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));

        $month = FormLib::get('smonth', date('m'));//$this->form->smonth;
        $year = FormLib::get('syear',date('Y'));

        //$startDate = new DateTime($date1);
        //$endDate = new DateTime($date2);
        $startDate = new DateTime($year.'-'.$month.'-01 00:00:00');
        $startDate->modify('-6 months');
        if ($startDate->format('Y-m-d') < '2023-10-01 00:00:00') {
            $startDate = new DateTime('2023-10-01 00:00:00');
        }
        //$startDate->modify('first day of this month');

        $endDate = new DateTime($year.'-'.$month.'-01 00:00:00');
        $endDate->modify('last day of this month');
        if ($endDate->format('Y-m-d') < '2023-10-01 00:00:00') {
            $endDate = new DateTime('2023-10-31 23:59:59');
        }

        
        
        //$startStr  = $startDate->format('Y-m-d').' 00:00:00';
        //$endStr = $endDate->format('Y-m-d').' 23:59:59';

        //echo '<br>'.$startDate->format('Y-m-d').'</br>';
        //echo '<br>'.$endDate->format('Y-m-d').'</br';
        $endLoop = $endDate;
        $endLoop->modify('+1 Day');

        $exit = 0;
        $rawdata = array();
        $data = array(array(''), array('Total Joins'), array('Total Terms'),array('Total New FFA'),array('FFA Non-renewal'),
                      array('Total FFA'),array('Total Members'),array('In Good Standing'),array('Not In Good Standing'),array('Reachable'),array('Unreachable'));
        $errorCheckCount = 0;
        while($startDate->format('Y-m-d') != $endLoop->format('Y-m-d')) {
            $startStr  = $startDate->format('Y-m-d').' 00:00:00';
            $stopDate = $startDate;
            $stopDate->modify('last day of this month');
            $useCustData = false;
            if ($stopDate->format('Y-m-d') > date('Y-m-d')){
                $stopDate = new DateTime(date('Y-m-d'));
                //$stopDate->modify('-1 day');
                $useCustData = true;

            }
            $endStr = $stopDate->format('Y-m-d').' 23:59:59';
            $data[0][] = $endStr;
            $data = $this->get_data($dbc, $startStr, $endStr, $data, $useCustData);
            $startDate->modify('first day of next month');
            $errorCheckCount ++;
            //$data = array('start date'=>$startStr,'endStr'=>$endStr, 'endLoop'=> $endLoop->format('Y-m-d'), $data);
            //$data[] = $line;
            if ($exit == 20) {
                break;
            } else {
                $exit++;
            }
        }

        //error checking
        
        $inGoodStandingCheck = array('CHK IGS');
        $reachableCheck = array('CHK reachable');
        $joinsCheck = array('CHK Joins','');
        $ffaCheck = array('CHK FFA','');
        $space = array('');
        for ($i=1; $i <= $errorCheckCount; $i++) {
            $space[] ='';
            $inGoodStandingCheck[]  = $data[6][$i] - $data[7][$i] - $data[8][$i];
            $reachableCheck[] =$data[6][$i] - $data[9][$i] - $data[10][$i];
            if($i > 1) {
                $joinsCheck[] = $data[6][$i-1] + $data[1][$i] - $data[2][$i] - $data[6][$i];
                $ffaCheck[] = $data[5][$i-1] + $data[3][$i] - $data[4][$i] - $data[5][$i];
            }
        }
        $data[] = $space;
        $data[] = $inGoodStandingCheck;
        $data[] = $reachableCheck;
        $data[] = $joinsCheck;
        $data[] = $ffaCheck;


        return $data;
    }

    private function get_data($dbc, $sdate, $edate, $data, $useCustData){        
        $query = "SELECT count(*), 'Total Joins' as lineName FROM (
            SELECT p.card_no,SUM(p.stockPurchase) as equity, min(p.tdate) as startDate  FROM core_trans.stockpurchases p
            LEFT JOIN (
                SELECT p.card_no, SUM(p.stockPurchase), MAX(tdate) as close_date, MIN(tdate) as start_date FROM core_trans.stockpurchases p 
                where p.tdate < '{$sdate}'
                group by p.card_no having SUM(p.stockPurchase) = 0) as e on p.card_no = e.card_no
            WHERE (p.tdate > e.close_date OR e.close_date is null)
            group by card_no having SUM(stockPurchase) > 0
        ) as p
        WHERE p.startDate between '{$sdate}' and '{$edate}'
        UNION
        SELECT COUNT(p.card_no), 'Total Terms' as lineName FROM 
        (SELECT card_no,SUM(stockPurchase) as equity, max(tdate) as endDate  FROM core_trans.stockpurchases
        group by card_no having SUM(stockPurchase) = 0) p
        LEFT JOIN (SELECT * FROM core_op.custdataHistory WHERE histDate = '{$sdate}') h on p.card_no = h.cardNo
        WHERE p.endDate between '{$sdate}' and '{$edate}'
        UNION
        SELECT  count(c.cardNo), 'New FFAs' as lineName
        FROM core_op.custdataHistory c
        LEFT JOIN core_op.custdataHistory h on c.cardNo = h.cardNo and h.histDate = '{$sdate}'
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND c.memType IN (6,14,15) AND (h.memType not in (6,14,15) or h.memType is null)
        UNION
        SELECT  count(c.cardNo), 'FFA Non-Renewals' as lineName
        FROM core_op.custdataHistory c
        LEFT JOIN core_op.custdataHistory h on c.cardNo = h.cardNo and h.histDate = '{$sdate}'
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND h.memType in (6,14,15) AND (c.memType not in (6,14,15))
        UNION
        SELECT  count(c.cardNo), 'Total FFA' as lineName
        FROM core_op.custdataHistory c
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND c.memType  in (6,14,15)
        UNION
        SELECT count(c.cardNo), 'Total Members' as lineName FROM (
            SELECT p.card_no, SUM(p.stockPurchase) as equity
            FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
        ) as p
        LEFT JOIN (
            select * from core_op.custdataHistory where histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND)
        ) as c on p.card_no = c.cardNo             
        WHERE c.personNum = 1
        UNION
        SELECT count(c.cardNo), 'Total Members GS' as lineName FROM (
            SELECT p.card_no, SUM(p.stockPurchase) as equity
            FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
        ) as p
        LEFT JOIN (
            select * from core_op.custdataHistory where histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND)
        ) as c on p.card_no = c.cardNo             
        WHERE c.personNum = 1 AND c.memType in (1,3,5,6,8,9,10)
        UNION
        SELECT count(c.cardNo), 'Total Members NGS' as lineName FROM (
            SELECT p.card_no, SUM(p.stockPurchase) as equity
            FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
        ) as p
        LEFT JOIN (
            select * from core_op.custdataHistory where histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND)
        ) as c on p.card_no = c.cardNo             
        WHERE c.personNum = 1 AND c.memType not in (1,3,5,6,8,9,10)
        UNION
        SELECT count(p.card_no), 'Total Members Reachable' as lineName FROM (
            SELECT p.card_no, SUM(p.stockPurchase) as equity
            FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
        ) as p
        LEFT JOIN core_op.meminfo i on p.card_no = i.card_no 
        WHERE (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))
        UNION
        SELECT count(p.card_no), 'Total Members uneachable' as lineName FROM (
            SELECT p.card_no, SUM(p.stockPurchase) as equity
            FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
        ) as p
        LEFT JOIN core_op.meminfo i on p.card_no = i.card_no 
        WHERE (i.street IN('','*','.','\n') OR i.street is NULL)";
        if($useCustData) {
            $query = "SELECT count(*), 'Total Joins' as lineName FROM (
                SELECT p.card_no,SUM(p.stockPurchase) as equity, min(p.tdate) as startDate  FROM core_trans.stockpurchases p
                LEFT JOIN (
                    SELECT p.card_no, SUM(p.stockPurchase), MAX(tdate) as close_date, MIN(tdate) as start_date FROM core_trans.stockpurchases p 
                    where p.tdate < '{$sdate}'
                    group by p.card_no having SUM(p.stockPurchase) = 0) as e on p.card_no = e.card_no
                WHERE (p.tdate > e.close_date OR e.close_date is null)
                group by card_no having SUM(stockPurchase) > 0
            ) as p
            WHERE p.startDate between '{$sdate}' and '{$edate}'
            UNION
            SELECT COUNT(p.card_no), 'Total Terms' as lineName FROM 
            (SELECT card_no,SUM(stockPurchase) as equity, max(tdate) as endDate  FROM core_trans.stockpurchases
            group by card_no having SUM(stockPurchase) = 0) p
            LEFT JOIN (SELECT * FROM core_op.custdata WHERE personNum =1) h on p.card_no = h.cardNo
            WHERE p.endDate between '{$sdate}' and '{$edate}'
            UNION
            SELECT  count(c.cardNo), 'New FFAs' as lineName
            FROM core_op.custdata c
            LEFT JOIN core_op.custdataHistory h on c.cardNo = h.cardNo and h.histDate = '{$sdate}'
            WHERE c.personNum = 1 AND c.memType in (6,14,15) AND (c.memType NOT IN (6,14,15) or h.memType is null)
            UNION
            SELECT  count(c.cardNo), 'FFA Non-Renewals' as lineName
            FROM core_op.custdata c
            LEFT JOIN core_op.custdataHistory h on c.cardNo = h.cardNo and h.histDate = '{$sdate}'
            WHERE c.personNum = 1 AND h.memType in (6,14,15) AND (c.memType not in  (6,14,15))
            UNION
            SELECT  count(c.cardNo), 'Total FFA' as lineName
            FROM core_op.custdata c
            WHERE c.personNum = 1 AND c.memType in (6,14,15)
            UNION
            SELECT count(c.cardNo), 'Total Members' as lineName FROM (
                SELECT p.card_no, SUM(p.stockPurchase) as equity
                FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
            ) as p
            LEFT JOIN (
                select * from core_op.custdata where personNum = 1
            ) as c on p.card_no = c.cardNo             
            WHERE c.personNum = 1
            UNION
            SELECT count(c.cardNo), 'Total Members GS' as lineName FROM (
                SELECT p.card_no, SUM(p.stockPurchase) as equity
                FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
            ) as p
            LEFT JOIN (
                select * from core_op.custdata where personNum = 1
            ) as c on p.card_no = c.cardNo             
            WHERE c.personNum = 1 AND c.memType in (1,3,5,6,8,9,10)
            UNION
            SELECT count(c.cardNo), 'Total Members NGS' as lineName FROM (
                SELECT p.card_no, SUM(p.stockPurchase) as equity
                FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
            ) as p
            LEFT JOIN (
                select * from core_op.custdata where personNum = 1
            ) as c on p.card_no = c.cardNo             
            WHERE c.personNum = 1 AND c.memType not in (1,3,5,6,8,9,10)
            UNION
            SELECT count(p.card_no), 'Total Members Reachable' as lineName FROM (
                SELECT p.card_no, SUM(p.stockPurchase) as equity
                FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
            ) as p
            LEFT JOIN core_op.meminfo i on p.card_no = i.card_no 
            WHERE (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))
            UNION
            SELECT count(p.card_no), 'Total Members uneachable' as lineName FROM (
                SELECT p.card_no, SUM(p.stockPurchase) as equity
                FROM core_trans.stockpurchases p WHERE p.tdate < '{$edate}' group by p.card_no  having SUM(p.stockPurchase) > 0
            ) as p
            LEFT JOIN core_op.meminfo i on p.card_no = i.card_no 
            WHERE (i.street IN('','*','.','\n') OR i.street is NULL)";
        } 

        $prep = $dbc->prepare($query);
        $results = $dbc->execute($query, array());
        $i =1;
        while ($row = $dbc->fetch_row($results)) {
            $data[$i][] = $row[0];
            $i++;
        }
        return $data;
    }

    
    
    function form_content()
    {
        ob_start();
?>
<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">

<div class="col-sm-5">
    <div class="form-group">
    <select class="form-control" id="smonth"  name="smonth">
        <?php 
    for ($i=1;$i<=12;$i++) {
    printf("<option %s value=%d>%s</option>",
        ($i == date('m') ? 'selected' : ''),
        $i,date("F",mktime(0,0,0,$i,1,2000)));
    }
    ?>
    </select>

        <input type="number" class="form-control" id="syear" name=syear
        placeholder="Year" required value="<?php echo date("Y"); ?>" />
    </div>
    <p>
        <button type=submit class="btn btn-default btn-core">Submit</button>
        <button type=reset class="btn btn-default btn-reset">Start Over</button>
    </p>
</div>
</form>
<?php
        $this->add_onload_command('$(\'#upc-field\').focus();');

        return ob_get_clean();
    }

    public function helpContent()
    {
        return '<p>
            Lists every transaction containing a particular item.
            </p>';
    }
}

/*
<div id="#myform" class="form-group form-inline">
<form onsubmit="lookupSales(); return false;">
<select id="smonth" class="form-control">
<?php 
for ($i=1;$i<=12;$i++) {
    printf("<option %s value=%d>%s</option>",
        ($i == date('m') ? 'selected' : ''),
        $i,date("F",mktime(0,0,0,$i,1,2000)));
}
?>
</select>

<input type="number" class="form-control" id="syear" 
    placeholder="Year" required value="<?php echo date("Y"); ?>" />

<button type="submit" class="btn btn-default">Lookup Sales</button>
</form>
</div>*/

FannieDispatch::conditionalExec();

