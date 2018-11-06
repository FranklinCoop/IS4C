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

class BudgetSalesReport extends FannieReportPage 
{
    public $description = '[Buget Sales] Shows year over year sales by department..'; 
    public $report_set = 'Sales Reports';

    protected $title = "Fannie : Budget Sales Report";
    protected $header = "Buget Sales Report Report";
    protected $report_cache = 'none';
    
    protected $sortable = false;
    protected $no_sort_but_style = true;
    protected $new_tablesorter = true;
    protected $multi_report_mode = true;

    protected $report_headers = array('Dept Name','Budget','This Year','Last Year', '% Change', '% Budget');
    protected $required_fields = array('date1','date2');
    protected $deptGroups = array('Default','Bakery','PFD', 'Grocery','Bulk','Beer', 'Perishable','Perishable','Perishable','Perishable', 
    	'Produce', 'Wellness', 'Wellness', 'Wellness', 'Wellness');
    protected $showCharts = true;

    public $totalsChart = array();
    public $deptCharts = array();

	function report_description_content() {
		return(array('<p>Budget vs sales report</p>'));
	}

	public function preprocess()
    {
        parent::preprocess();
        // custom: needs graphing JS/CSS
        if ($this->content_function == 'report_content' && $this->report_format == 'html') {
            $this->addScript('../../../../src/javascript/Chart.min.js');
            $this->addScript('../../../../src/javascript/CoreChart.js');
            $this->addScript('budgetSales.js?=20181105');
        }

        return true;
    }

	function fetch_report_data()
    {
		global $FANNIE_OP_DB, $FANNIE_ARCHIVE_DB, $FANNIE_EQUITY_DEPARTMENTS,
			$FANNIE_COOP_ID;
        $dbc = FannieDB::get($FANNIE_OP_DB);
		$d1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$d2 = FormLib::get_form_value('date2',date('Y-m-d'));
		$superDepts = FormLib::get_form_value('buyer','');
		$store = FormLib::get('store', 0);

		$startThisYear = DateTime::createFromFormat('Y-m-d' ,$d1);
   		$startThisYear->modify('-4 weeks');
		$startDay = $startThisYear->format('l');
		$startLastYear = DateTime::createFromFormat('Y-m-d' ,$d1);
		$startLastYear->modify('-52 weeks');
		//$startLastYear->modify('last ' . $startDay);
		$startLastYear->modify('-4 weeks');
		$endThisYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endThisYear->modify('+4 weeks');
		$endDay = $endThisYear->format('l');
		$endLastYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endLastYear->modify('+4 weeks');
		$endLastYear->modify('-52 weeks');
		//$endLastYear->modify('last ' . $endDay);

		echo '<script>console.log(" Start:'.$startThisYear->format('W D : Y-m-d').' - END: '
				.$endThisYear->format('W D : Y-m-d').'");</script>';
		echo '<script>console.log(" Start:'.$startLastYear->format('W D : Y-m-d').' - END: '
				.$endLastYear->format('W D : Y-m-d').'");</script>';

		$dlog = DTransactionsModel::selectDTrans($d1,$d2);

		$report = array();
		
		$report[] = $this->getStoreTotals($dbc,$store,$d1,$d2);

		//$this->totalsChart = $this->getStoreTotals($dbc,$store,$d1,$d2);

		$report = array_merge($report, $this->getDepartmentTotals($dbc, $store, $startThisYear, $endThisYear,$startLastYear,$endLastYear));

		for ($i=0; $i < count($report); $i++) { 
			$table = $report[$i];
			$chart = array();
			$charts = array();
			$labels = array();
			$data = array();
			$salesBudget = array();
			$cySales = array();
			$pySales = array();
			for ($j=0; $j < count($table); $j++) { 
				$row = $table[$j];
				if ($j== floor((sizeof($table)/2))) {
					$row[] = (!array_key_exists(2, $row) || $row[2] ==0) ? 0 : sprintf('%.2f',(1 - $row[3]/$row[2])*100).'%' ;
				    $row[] = (!array_key_exists(2, $row) || $row[2] ==0) ? 0 : sprintf('%.2f',(1 - $row[1]/$row[2])*100).'%' ;
				    $data[] = $row;
				}

				//$row[] = sprintf('%.2f',(1 - $row[3]/$row[2])*100).'%';
				//$row[] = sprintf('%.2f',(1 - $row[1]/$row[2])*100).'%';
				//$table[$j] = $row;
				// format for the chart here because the javascript seems slow.
				
				$labels[] = $row[0];
				$salesBudget[] = $row[1];
				$cySales[] = ($row[2]==0) ? null : $row[2];
				$pySales[] = $row[3];


			}
			$chart[] = $labels;
			$chart[] = $salesBudget;
			$chart[] = $cySales;
			$chart[] = $pySales;

			//$this->deptCharts[] = $chart;
			if($i==0){
				$this->totalsChart = $chart;
			} else {
				$this->deptCharts[] = $chart;
			}
			$report[$i] = $data;
		}


		return $report;
	}

	private function departmentTotals($dbc,$store,$date1,$date2, $deptarments='All') {
		$startThisYear = DateTime::createFromFormat('Y-m-d' ,$date1);
   		$startThisYear->modify('-4 weeks');
		$startDay = $startThisYear->format('l');
		$startLastYear = DateTime::createFromFormat('Y-m-d' ,$date1);
		$startLastYear->modify('-52 weeks');
		$startLastYear->modify('next ' . $startDay);
		$startLastYear->modify('-4 weeks');
		$endThisYear = DateTime::createFromFormat('Y-m-d' ,$date2);
		$endThisYear->modify('+5 weeks');
		$endDay = $endThisYear->format('l');
		$endLastYear = DateTime::createFromFormat('Y-m-d' ,$date2);
		$endLastYear->modify('+5 weeks');
		$endLastYear->modify('-52 weeks');
		$endLastYear->modify('next ' . $endDay);

		$report = array();
		$deptarments = array(2,3,4,6,7,8,9,10,11,12,13,14);
		foreach ($deptarments as $key => $department) {
			$budgetQ = $dbc->prepare("SELECT WEEK(b.budgetDate), SUM(b.budget),m.superDeptNo
				FROM gfm_approach.daily_dept_sales_budget b
				JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
				WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
				GROUP BY m.superDeptNo,WEEK(b.budgetDate) ORDER BY m.superDeptNo");
        	$budgetR = $dbc->execute($budgetQ,$args);

        	$args = array($startLastYear->format('Y-m-d'), $endLastYear->format('Y-m-d'),$store);
        	$lastYearQ = $dbc->prepare('SELECT WEEK(s.`date`) ,SUM(s.creditAmt) as deptSales, m.superDeptNo 
        		FROM gfm_approach.daily_sales_sage s
				JOIN gfm_approach.sage_to_core_acct_maps m on s.accountID = m.sageAcctNo
				where `date` between ? AND ? AND m.storeNo = ? group by m.superDeptNo, WEEK(s.`date`) order by m.superDeptNo');
        	$lastYearR = $dbc->execute($lastYearQ,$args);

        	$args = array($startThisYear->format('Y-m-d').' 00:00:00', $endThisYear->format('Y-m-d').' 23:59:59', $store);
        	$salesQ = $dbc->prepare("SELECT WEEK(t.tdate), sum(t.total), s.superID
				FROM core_trans.dlog_90_view t
				JOIN core_op.superdepts s on t.department = s.dept_ID
				WHERE t.`tdate` BETWEEN ? AND ? AND t.store_id = ?
				AND t.trans_type IN ('D', 'I') AND s.superID < 14
				group by s.superID,WEEK(t.tdate) order by s.superID");
        	$salesR = $dbc->execute($salesQ, $args);

        	$table = $this->createBlankTable($startThisYear,$endThisYear);
        	$i = 0;
        	while ($budgetW = $dbc->fetchRow($budgetW)) {
        		$row = $table[$i];
        		for ($j=$i; $j<sizeof($table )-$i;$j++){
        			if ($row[0] == $budgetW[0]) {
        				$row[] = $budget[1];
        				$i++;
        				continue;
        			} else {
        				$row = $table[$j];
        			}
        		}

        	}

        	$report[] = $table;
		}
		
		return $report;

		/*
		$budgetQ .= "SELECT WEEK(b.budgetDate,1), SUM(b.budget) 
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			JOIN core_op.superdeptnames s on s.superID = m.superDeptNo
			WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ?
			GROUP BY WEEK(b.budgetDate, 1)";
		$lastYearQ .="";
		$salesQ .="";

		if ($department == 'All') {
			$budgetQ .= "";
			$lastYearQ .="";
			$salesQ .="";
		} else {
			switch ($department) {
				case '6':
				case '7':
				case '8':
				case '9': // perishable.
					$budgetQ .= " AND s.superID IN (6,7,8,9)";
					$lastYearQ .=" AND s.superID IN (6,7,8,9)";
					$salesQ .=" AND s.superID IN (6,7,8,9)";
				case '11':
				case '12':
				case '13': //wellness
					$budgetQ .= " AND s.superID IN (11,12,13)";
					$lastYearQ .=" AND m.superDeptNo IN (11,12,13)";
					$salesQ .=" AND s.superID IN (11,12,13)";
					break;
				default:
					$budgetQ .= " AND s.superID = ".$department;
					$lastYearQ .=" AND m.superDeptNo = ".$department;
					$salesQ .=" AND s.superID = ".$department;
					break;
			}
		}
*/
	}

	private function createBlankTable($start, $end){
		$table = array();
		while ($start->diff($end)->d != 0) {
			$row = array($start->format('M'));
			$table[] = $row;
			$start->modify('+7 days');
		}
		return $table;
	}

	private function getDepartmentTotals($dbc, $store, $startThisYear, $endThisYear,$startLastYear,$endLastYear) {
		$report = array();
		$data = array();
		$args = array($startThisYear->format('Y-m-d'), $endThisYear->format('Y-m-d'),$store);
		$budgetQ = $dbc->prepare("SELECT WEEK(b.budgetDate), SUM(b.budget),m.superDeptNo
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
			GROUP BY m.superDeptNo,WEEK(b.budgetDate) ORDER BY m.superDeptNo");
        $budgetR = $dbc->execute($budgetQ,$args);

        $args = array($startLastYear->format('Y-m-d'), $endLastYear->format('Y-m-d'),$store);
        $lastYearQ = $dbc->prepare('SELECT WEEK(s.`date`) ,SUM(s.creditAmt) as deptSales, m.superDeptNo 
        	FROM gfm_approach.daily_sales_sage s
			JOIN gfm_approach.sage_to_core_acct_maps m on s.accountID = m.sageAcctNo
			where `date` between ? AND ? AND m.storeNo = ? group by m.superDeptNo, WEEK(s.`date`) order by m.superDeptNo');
        $lastYearR = $dbc->execute($lastYearQ,$args);

        $args = array($startThisYear->format('Y-m-d').' 00:00:00', $endThisYear->format('Y-m-d').' 23:59:59', $store);
        $salesQ = $dbc->prepare("SELECT WEEK(t.tdate), sum(t.total), s.superID
			FROM core_trans.dlog_90_view t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			AND WEEK(t.tdate) != WEEK(NOW())
			group by s.superID,WEEK(t.tdate) order by s.superID");
        $salesR = $dbc->execute($salesQ, $args);

        $i=0;
        $nextDept = 2; // starts at 2 assuming the first dept is 1
        //$report = array();
        while($budgetW = $dbc->fetchRow($budgetR)){
        	//echo '<script>console.log("'.$nextDept.' : '.$budgetW[2].'");</script>';
        	//Checking what department we are in by dept_no
        	if($budgetW[2] == $nextDept){	
        		$data[] = $report;
        		$report = array();
        		$nextDept = $budgetW[2] + 1;
        		if($store == 1 && $nextDept == 5) {$nextDept = 6;};
        		//$data[] = $report;
        		//$report = $data[$nextDept];
        		//$report = array();
        	}
        	$record = array();
       
        	// calculate the date start from the numaric date
			$graphDate = new DateTime();
			$graphDate->setISODate(date('Y'),$budgetW[0]+1);
			$graphDate->modify('last Sunday');
			$record[] = $graphDate->format('m-d');
        	//$record[] = sprintf('%.2f',$budgetW[2]);
        	$record[] = sprintf('%.2f',$budgetW[1]);
        	//$record[] = sprintf('%.2f',$lastYearW[2]);
        	$report[] = $record;

        }
        $data[] = $report; // deal with the last value.



        $nextDept = 2;
        $currentDept = 1;
        $report = $data[0]; // start with the dept 1 report.
        $i=0;
        while($salesW = $dbc->fetchRow($salesR)){
        	//echo '<script>console.log(" SALES:'.$nextDept.' : '.$salesW[2].'");</script>';
        	if($salesW[2] == $nextDept){	
        		if ($i < sizeof($report)) {
        			while ($i < sizeof($report)){
        				$report[$i] = array_merge($report[$i],array(0)); ;
        				$i++;
        			}	
        		}
        		$data[$currentDept - 1] = $report;
        		$report = $data[$currentDept];
        		//$report = $data[$nextDept - 1];
        		$nextDept = $salesW[2] + 1;
        		if($store == 1 && $nextDept == 5) {$nextDept = 6;}; // this is a cludge to take out beer for greenfield.
        		$currentDept = ($store == 1 && $nextDept > 4) ? $nextDept - 2 : $nextDept -1;
        		$i = 0;
        	}
        	$record = array();
        	$record[] = sprintf('%.2f',$salesW[1]);
        	if(array_key_exists($i,$report)){ $report[$i] = array_merge($report[$i],$record); }
        	$i++;
        }
        if ($i < sizeof($report)) {
      		while ($i < sizeof($report)){
      			$report[$i] = array_merge($report[$i],array(0)); ;
       			$i++;
       		}	
       	}
        $data[$currentDept-1] = $report; // loop doesn't assign the last set.
        
        $nextDept = 2;
        $currentDept = 1;
        $report = $data[0]; // start with the dept 1 report.
        $i=0;
        while($lastYearW = $dbc->fetchRow($lastYearR)){
        	if($lastYearW[2] == $nextDept){	
        		$data[$currentDept - 1] = $report;
        		$report = $data[$currentDept];
        		//$report = $data[$nextDept - 1];
        		$nextDept = $lastYearW[2] + 1;
        		if($store == 1 && $nextDept == 5) {$nextDept = 6;}; // this is a cludge to take out beer for greenfield.
        		$currentDept = ($store == 1 && $nextDept > 4) ? $nextDept - 2 : $nextDept -1;
        		$i = 0;
        	}
        	$record = array();
        	$record[] = sprintf('%.2f',$lastYearW[1]);
        	if(array_key_exists($i,$report)){ $report[$i] = array_merge($report[$i],$record); }
        	$i++;
        }
        $data[$currentDept-1] = $report; // loop doesn't assign the last set.

        /*
        
			$record = array();
			//$record[] = $row[0];
			$record[] = (array_key_exists(1, $row)) ? $row[1] :  0;
			//$record[] = $row[1];
			if(array_key_exists($i,$data)){ $data[$i] = array_merge($data[$i],$record); }
		
        */

        return $data;
	}

	function getFiscalYearBalnce($date,$dbc, $store) {

		$endDate = new DateTime($date->format('Y-m-d'));
		if($date->format('n') >= 10) {
       		$date->modify('first day of october');
   		} else {
       		$date->modify('first day of october last year');
   		}
   		$args = array($date->format('Y-m-d'), $endDate->format('Y-m-d'),$store);
   		$salesTotalQ = $dbc->prepare("SELECT s.superID, sum(t.total) 
			FROM core_trans.transarchive t
			JOIN core_op.superdepts s on t.department = s .dept_ID
			WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I')
			group by s.superID ORDER BY s.superID");
   		$salesTotalR = $dbc->execute($salesTotalQ,$args);

		$budgetTotalQ = $dbc->prepare("SELECT  MAX(s.superID) as deptID, SUM(b.budget) as budget
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			JOIN core_op.superdeptnames s on s.superID = m.superDeptNo
			WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ? GROUP BY b.sageAcctNo");
		$budgetTotalR = $dbc->execute($budgetTotalQ, $args);

		$salesTotal = array();
		while($row = $dbc->fetchRow($salesTotalR)) {
			$salesTotal[] = $row;
		}
		$return = array();
		$key = 0;
		while ($row = $dbc->fetchRow($budgetTotalR)) {
			$record = array();
			$record[] = $row[0]; 
			$record[] = $salesTotal[$key][1] - $row[1];
			$return[] = $record;
			$key++;
		}

		return $return;
   	}

   	private function getStoreTotals($dbc,$store,$date1,$date2) {
   		$startThisYear = DateTime::createFromFormat('Y-m-d' ,$date1);
   		$startThisYear->modify('-4 weeks');
		$startDay = $startThisYear->format('l');
		$startLastYear = DateTime::createFromFormat('Y-m-d' ,$date1);
		$startLastYear->modify('-52 weeks');
		$startLastYear->modify('next ' . $startDay);
		$startLastYear->modify('-4 weeks');
		$endThisYear = DateTime::createFromFormat('Y-m-d' ,$date2);
		$endThisYear->modify('+4 weeks');
		$endDay = $endThisYear->format('l');
		$endLastYear = DateTime::createFromFormat('Y-m-d' ,$date2);
		$endLastYear->modify('+4 weeks');
		$endLastYear->modify('-52 weeks');
		$endLastYear->modify('next ' . $endDay);
		

		$data = array();
		$args = array($startThisYear->format('Y-m-d'), $endThisYear->format('Y-m-d'),$store);
		$budgetQ = $dbc->prepare("SELECT WEEK(b.budgetDate), SUM(b.budget) 
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			JOIN core_op.superDeptNames s on s.superID = m.superDeptNo
			WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ?
			GROUP BY WEEK(b.budgetDate)");
		$budgetR = $dbc->execute($budgetQ, $args);
		while($row = $dbc->fetchRow($budgetR)) {
			$record = array();
			$graphDate = new DateTime();
			$graphDate->setISODate(date('Y'),$row[0]+1);
			$graphDate->modify('last Sunday');
			$record[] = $graphDate->format('m-d');
			$record[] = $row[1];
			$data[] = $record;
		}

		$startThisYear->setTime(00,00,00);
		$endThisYear->setTime(23,59,58);
		$args = array($startThisYear->format('Y-m-d H:i:s'), $endThisYear->format('Y-m-d H:i:s'),$store);
		$salesQ = $dbc->prepare("SELECT WEEK(CAST(t.`tdate` AS DATE)), sum(t.total) 
			FROM core_trans.dlog_90_view t
			JOIN core_op.superdepts s on t.department = s .dept_ID
			WHERE t.`tdate` BETWEEN ? AND ? AND t.store_id = ?
			AND WEEK(t.tdate) != WEEK(NOW())
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			GROUP BY WEEK(CAST(t.`tdate` AS DATE))");
		$salesR = $dbc->execute($salesQ, $args);

		$i = 0;
		while($row = $dbc->fetchRow($salesR)) {
			$record = array();
			//$record[] = $row[0];
			$record[] = (array_key_exists(1, $row)) ? $row[1] : 'wtf' ;
			if(array_key_exists($i,$data)){$data[$i] = array_merge($data[$i],$record);}
			$i++;
		}

		$args = array($startLastYear->format('Y-m-d'), $endLastYear->format('Y-m-d'),$store);
		$salesLastQ = $dbc->prepare("SELECT WEEK(s.`date`) ,SUM(s.creditAmt) as deptSales FROM gfm_approach.daily_sales_sage s
			JOIN gfm_approach.sage_to_core_acct_maps m on s.accountID = m.sageAcctNo
			WHERE `date` BETWEEN ? AND ? AND m.storeNo = ?
			GROUP BY WEEK(s.`date`)");
		$salesTotalR = $dbc->execute($salesLastQ,$args);

		// add the zeros for the future weeks
		while ($i < $dbc->numRows($salesTotalR)) {
				$record = array();
				$record[] = 0;
				if(array_key_exists($i,$data)){$data[$i] = array_merge($data[$i],$record);}
			    $i++;
		}

		$i = 0;
		while($row = $dbc->fetchRow($salesTotalR)) {
			$record = array();
			//$record[] = $row[0];
			$record[] = (array_key_exists(1, $row)) ? $row[1] :  0;
			//$record[] = $row[1];
			if(array_key_exists($i,$data)){ $data[$i] = array_merge($data[$i],$record); }
			$i++;
		}

		return $data;
   	}

	function calculate_footers($data)
    {
		$store = FormLib::get('store', 0);
		switch($this->multi_counter){
        case 1:
            $this->report_headers[0] = 'Totals';
            break;
        case 2:
            $this->report_headers[0] = 'Bakery';
            break;
        case 3:
            $this->report_headers[0] = 'PFD';
            break;
        case 4:
        	$this->report_headers[0] = 'Grocery: Dry Goods';
            break;
        case 5:
        	$this->report_headers[0] = 'Grocery: Bulk';
            break;
        case 6:
            $this->report_headers[0] = ($store == 1) ? 'Perishable: Cheese' : 'Grocery: Beer' ;
            break;
        case 7:
            $this->report_headers[0] = ($store == 1) ? 'Perishable: Dairy' : 'Perishable: Cheese' ;
            break;
        case 8:
            $this->report_headers[0] = ($store == 1) ? 'Perishable: Frozen' : 'Perishable: Dairy' ;
            break;
        case 9:
            $this->report_headers[0] = ($store == 1) ? 'Perishable: Meat' : 'Perishable: Frozen' ;
            break;
        case 10:
            $this->report_headers[0] = ($store == 1) ? 'Produce' : 'Perishable: Meat' ;
            break;
        case 11:
            $this->report_headers[0] = ($store == 1) ? 'Wellness: Body Care' : 'Produce' ;
            break;
        case 12:
            $this->report_headers[0] = ($store == 1) ? 'Wellness: Gen Merch' : 'Wellness: Body Care' ;
            break;
        case 13:
            $this->report_headers[0] = ($store == 1) ? 'Wellness: Supplements' : 'Wellness: Gen Merch' ;
            break;
        case 14:
        	$this->report_headers[0] = 'Wellness: Gen Merch';
        	break;
        }
		return array();
	}

	private function superOpts($super='super') {
		$dbc = FannieDB::getReadOnly(FannieConfig::config('OP_DB'));
        $def = $dbc->tableDefinition('superDeptNames');
        $superQ = 'SELECT superID, super_name FROM superDeptNames';
        if (isset($def['deleted'])) {
            $superQ .= ' WHERE deleted=0 ';
        }
        $superR = $dbc->query($superQ);
        $super_opts = '';
        while ($w = $dbc->fetchRow($superR)) {
            $super_opts .= sprintf('<option value="%d">%s</option>',
                $w['superID'], $w['super_name']) . "\n";
        }
        $ret = '
    					<label class="col-sm-4 control-label">Super Department</label>
        				<select name="'.$super.'" id="super-id" class="form-control"">
            			<option value="">Select super department</option>
            			'.$super_opts.'
            			<option value="-2">All Retail</option><option value="-1">All</option>
        				</select>
    			';

        return $ret;
	}

    public function report_content() {
        $default = parent::report_content();

        if ($this->report_format == 'html') {
            $default .= '
            <script type="text/javascript">
    			var totalsChart = '. json_encode($this->totalsChart) .';
    			var deptCharts = '.json_encode($this->deptCharts) .';
			</script>';

            $this->addOnloadCommand('budgetSales.totals('.(count($this->report_headers)-2).');');
            $this->addOnloadCommand('budgetSales.chartAll('.(count($this->report_headers)-2).')');
        }

        return $default;
    }



	function form_content()
    {
        ob_start();
        ?>
        <form action=BudgetSalesReport.php method=get>
        <div class="form-group">
           <?php echo $this->superOpts('buyer'); ?>
        </div>
        <div>
        	<Label>Store</Label>
        	<?php $ret=FormLib::storePicker();echo $ret['html']; ?>
        </div>
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
