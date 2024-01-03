<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op

    This file is part of IT CORE.

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

	parts of this file was adapted from http://sourceforge.net/projects/mysql2sqlite/

*********************************************************************************/
include(dirname(__FILE__).'/../../../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class MonthlyMemberTestPage extends FanniePage {

	protected $header = 'Monthly Member Report Logic testing Page';
	protected $title = 'Member Press Test';
    private $mpURL ='';
    private $mpKey = '';
    

    public function __construct(){
        parent::__construct();
        $conf = FannieConfig::factory();
        $settings = $conf->get('PLUGIN_SETTINGS');
        $this->mpURL = $settings['mpUrl'];
        $this->mpKey = $settings['mpAPIKey'];
    }

    public function body_content()
    {

        //map the exisiting website members logically.
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));


        $startDate = new DateTime('2023-10-01 00:00:00');
        $endDate = new DateTime('2024-01-31 23:59:59');

        $startDate->modify('first day of this month');
        $endDate->modify('last day of this month');
        //$startStr  = $startDate->format('Y-m-d').' 00:00:00';
        //$endStr = $endDate->format('Y-m-d').' 23:59:59';
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
            if ($stopDate->format('Y-m-d') > date('Y-m-d')){
                $stopDate = new DateTime(date('Y-m-d'));
                $stopDate->modify('-1 day');
            }
            $endStr = $stopDate->format('Y-m-d').' 23:59:59';
            $data[0][] = $endStr;
            $data = $this->get_data($dbc, $startStr, $endStr, $data);
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



        echo '<table class="table table-bordered table-striped table-condensed">';
        foreach ($data as $dat) {
            
            if ($tableHeaders === '') { 
                echo '<tr>';
                echo $this->loopArrayKeys($dat);
                echo '</tr>';
                $tableHeaders = 'done';
            }
            echo '<tr>';
            echo $this->loopArray($dat);
            echo '</tr>';
            
        }
        echo '</table>';

    }

    private function get_data($dbc, $sdate, $edate, $data){        
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
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND c.memType = 6 AND (h.memType != c.memType or h.memType is null)
        UNION
        SELECT  count(c.cardNo), 'FFA Non-Renewals' as lineName
        FROM core_op.custdataHistory c
        LEFT JOIN core_op.custdataHistory h on c.cardNo = h.cardNo and h.histDate = '{$sdate}'
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND h.memType = 6 AND (h.memType != c.memType)
        UNION
        SELECT  count(c.cardNo), 'Total FFA' as lineName
        FROM core_op.custdataHistory c
        WHERE c.histDate = DATE_ADD('{$edate}', INTERVAL 1 SECOND) AND c.memType = 6
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

        $prep = $dbc->prepare($query);
        $results = $dbc->execute($query, array());
        $i =1;
        while ($row = $dbc->fetch_row($results)) {
            $data[$i][] = $row[0];
            $i++;
        }
        return $data;
    }

    private function loopNestedArray($arr) {
        $headersDone = false;
        $ret = '<td>Nested Table</td> <td><table>';
        foreach($arr as $key => $value) {
            if ($headersDone) {
                $ret.= '<tr>'.$this->loopArrayKeys($value).'</tr><tr>';
            }
            $ret .= '<td>';
            $ret .= $value;
            $ret .= '</td>';
        }
        $ret .= '</tr></table>';
        return $ret;
    }

    private function loopArray($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<td>';
            //if (is_array($value)) {
            //    $ret .= $this->loopNestedArray($value);
            //} else {
                $ret .= $value;
            //}
            $ret .= '</td>';
        }
        return $ret;
    }
    private function loopArrayKeys($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<th>';
            $ret .= $key;
            $ret .= '</th>';
        }
        return $ret;
    }
    private function getMPMemberships () {

    }
}

FannieDispatch::conditionalExec(false);