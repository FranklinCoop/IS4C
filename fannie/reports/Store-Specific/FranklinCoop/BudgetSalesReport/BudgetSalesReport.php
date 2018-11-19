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

    protected $report_headers = array('Dept Name','Sales','Budget','% Budget', 'Last Year Sales', '% Change', 'Year Balance');
    protected $required_fields = array('date1','date2');
  	
    protected $deptNames = array(0 => 'Store Totals', 1 => 'Bakery', 2=> 'PFD', 3=>'Grocery', 4=>'Bulk', 5=>'Cheese', 6=>'Dairy', 7=>'Frozen',
    	8=>'Meat',9=>'Produce', 10=>'Body Care', 11 =>'Genral Merch', 12=>'Supplements');
    protected $tableNames = array(0=>'Totals', 1=> '', 2=>'',3=>'',4=>'',5=>'',6=>'Perishable',7=>'',8=>'Wellness');
    protected $deptCanvases = array('Bakery','PFD', 'Grocery', 'Bulk', 'Perishable', 'Perishable', 'Perishable', 'Perishable','Produce', 'Wellness','Wellness','Wellness');
    protected $canvasPos = array(2,3, 4, 5, 6, 6, 6,
    	6,7, 8,8,8);
    protected $showCharts = true;

    public $totalsChart = array();
    public $deptCharts = array();
    public $custChart = array();
    public $basketChart = array();
    public $customerCount = 0;

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
            $this->addScript('budgetSales.js');
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
		$superDepts = (!$superDepts) ? 'All' : $superDepts;

		$startThisYear = DateTime::createFromFormat('Y-m-d' ,$d1);
   		$startThisYear->modify('-4 weeks');
		$startDay = $startThisYear->format('l');
		$startLastYear = DateTime::createFromFormat('Y-m-d' ,$d1);
		$startLastYear->modify('-52 weeks');
		//$startLastYear->modify('next ' . $startDay);
		$startLastYear->modify('-4 weeks');
		$endThisYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endThisYear->modify('+3 weeks');
		$endDay = $endThisYear->format('l');
		$endLastYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endLastYear->modify('+3 weeks');
		$endLastYear->modify('-52 weeks');
		//$endLastYear->modify('next ' . $endDay);

		//echo '<script>console.log("Super: '.$superDepts.'");</script>';
		//echo '<script>console.log(" Start:'.$startLastYear->format('W D : Y-m-d').' - END: '
		//		.$endLastYear->format('W D : Y-m-d').'");</script>';

		$dlog = DTransactionsModel::selectDLog($startThisYear->format('Y-m-d'),$endThisYear->format('Y-m-d'));
		$report = array();
		
		//$report[] = $this->getStoreTotals($dbc,$store,$d1,$d2);

		//$this->totalsChart = $this->getStoreTotals($dbc,$store,$d1,$d2);
		$report = $this->getDepartmentTotals($dbc, $store, $startThisYear, $endThisYear,$startLastYear,$endLastYear, $dlog);
		$yearTotals = $this->getFiscalYearBalnce(DateTime::createFromFormat('Y-m-d' ,$d2),$dbc,$store, $dlog);
		$this->getCustomerCount($dbc,$store,$d1,$d2, $dlog);

		//$report = array_merge($report, $this->departmentTotals($dbc,$store,$d1,$d2, $superDepts));
		//$report = array_merge($report, $this->getDepartmentTotals($dbc, $store, $startThisYear, $endThisYear,$startLastYear,$endLastYear));

		$return = array();
		$tableNo = 0;
		$totalBudget = 0;
		$totalThisYear = 0;
		$totalLastYear = 0;
		$tpyLine = array();
		$tcyLine = array();
		$tbdLine = array();
		$tLabels = array();
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
				// format for the chart here because the javascript seems slow.
				$row = $table[$j];
				$labels[] = $row[0];
				$salesBudget[] = $row[1];
				$cySales[] = ($row[2]==0) ? null : $row[2];
				$pySales[] = $row[3];
				if($i==0) {
					$tLabels[] = $row[0];
					$tbdLine[] += $row[1];
					$tcyLine[] = ($row[2]==0) ? null : $row[2];
					$tpyLine[] += $row[3];
				} else {
					$tbdLine[$j] += $row[1];
					$tcyLine[$j] = ($row[2]==0) ? null : $tcyLine[$j]+$row[2];
					$tpyLine[$j] += $row[3];
				}
				$newRow = array();
				if ($j== floor((sizeof($table)/2))) {
					//sum for the total table;
					$totalBudget += $row[1];
					$totalThisYear += $row[2];
					$totalLastYear += $row[3];

					//$newRow = array();
					//$row[0] = $this->deptNames[$i+1];
					$row[] = (!array_key_exists(1, $row) || $row[1] ==0) ? 0 : sprintf('%.2f%%',($row[2]/$row[1])*100) ;
				    $row[] = (!array_key_exists(2, $row) || $row[2] ==0) ? 0 : sprintf('%.2f%%',(1 - $row[3]/$row[2])*100) ;
				    $newRow[] = $this->deptNames[$i+1];
				    $newRow[] = '$'.number_format($row[2],2);
				    $newRow[] = '$'.number_format($row[1],2);
				    $newRow[] = $row[4];
				    $newRow[] = '$'.number_format($row[3],2);
				    $newRow[] = $row[5];
				    $newRow[] = '$'.number_format($yearTotals[$i+1],2);
				    $data[] = $newRow;

				}

				//$row[] = sprintf('%.2f',(1 - $row[3]/$row[2])*100).'%';
				//$row[] = sprintf('%.2f',(1 - $row[1]/$row[2])*100).'%';
				//$table[$j] = $row;


			}
			$chart[] = $labels;
			$chart[] = $salesBudget;
			$chart[] = $cySales;
			$chart[] = $pySales;

			//$this->deptCharts[] = $chart;
			//if($i==0){
			//	$this->totalsChart = $chart;
			//} else {
				$this->deptCharts[] = $chart;
			//}
			//combine the preishable and wellness sub departments
			if(in_array($i, array(5,6,7))){
				$return[$tableNo-1] = array_merge($return[$tableNo-1], $data);
			} elseif (in_array($i, array(10,11))) {
				$return[$tableNo-1] = array_merge($return[$tableNo-1], $data);
			} else {
				$return[$tableNo] = $data;
				$tableNo++;
			}

		}
		$chart = array();
		$chart[] = $tLabels;
		$chart[] = $tbdLine;
		$chart[] = $tcyLine;
		$chart[] = $tpyLine;
		$this->totalsChart = $chart;

		// calculate the basket size values for the chart.
		$basketLabels = $tLabels;
		$basketline1 = array();
		$basketline2 = array();
		foreach ($tcyLine as $key => $value) {
			$basket = (!array_key_exists($key, $this->custChart[1]) || $this->custChart[1][$key] ==0) 
				? null : $value/$this->custChart[1][$key];
			$histBasket = (!array_key_exists($key, $this->custChart[2]) || $this->custChart[2][$key] ==0) 
				? null : $value/$this->custChart[2][$key];	
			$basketline1[] = $basket;
			$basletline2[] = $histBasket;
			//echo '<script>console.log("Basket Line: '.$basket.'");</script>';
		}
		$this->basketChart = array($basketLabels,$basketline1,$basketline2);

		
		$totalLastYearDiff = 0;
		$totalBudgetDiff = 0;
		if($totalThisYear !=0 && $totalBudget !=0) {
			$totalBudgetDiff = sprintf('%.2f%%',($totalThisYear/$totalBudget)*100);
			$totalLastYearDiff = sprintf('%.2f%%',(1-$totalLastYear/$totalThisYear)*100);
		}
		
		$totals = array('Store Totals','$'.number_format($totalThisYear,2),'$'.number_format($totalBudget,2),$totalBudgetDiff, '$'.number_format($totalLastYear,2),$totalLastYearDiff, '$'.number_format($yearTotals[0],2));
		$return = array_merge(array(array($totals)),$return);

		return $return;
	}


	private function getDepartmentTotals($dbc, $store, $startThisYear, $endThisYear,$startLastYear,$endLastYear, $dlog) {
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
			WHERE `date` BETWEEN ? AND ? AND m.storeNo = ? GROUP BY m.superDeptNo, WEEK(s.`date`) ORDER BY m.superDeptNo');
        $lastYearR = $dbc->execute($lastYearQ,$args);

        $args = array($startThisYear->format('Y-m-d').' 00:00:00', $endThisYear->format('Y-m-d').' 23:59:59', $store);
        $salesQ = $dbc->prepare("SELECT WEEK(t.tdate), sum(t.total), s.superID
			FROM {$dlog} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			AND WEEK(t.tdate) != WEEK(NOW())
			GROUP BY s.superID,WEEK(t.tdate) ORDER BY s.superID");
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
			$graphDate->modify('this Saturday');
			$record[] = $graphDate->format('m-d');
        	//$record[] = sprintf('%.2f',$budgetW[2]);
        	$record[] = $budgetW[1];//number_format($budgetW[1],2);//sprintf('$%.2f',$budgetW[1]);
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
        	$record[] = $salesW[1];//sprintf('%.2f',$salesW[1]);
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
        	$record[] = $lastYearW[1];//sprintf('%.2f',$lastYearW[1]);
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

	function getCustomerCount ($dbc, $store,$d1,$d2, $dlog) {
		$startDate = DateTime::createFromFormat('Y-m-d' ,$d1);
		$startDay = $startDate->format('l');
		$endDate = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endDay = $endDate->format('l');
		$interval = $startDate->diff($endDate);
		$changeS = ($interval->d+1)*4; // go back four intervals, the days are one shorter then we want.
		$changeE = ($interval->d+1)*3;
		$startDate->modify("-{$changeS} days");
		$endDate->modify("+{$changeE} days");

		
		$startDateHist = DateTime::createFromFormat('Y-m-d' ,$d1);
		$endDateHist = DateTime::createFromFormat('Y-m-d' ,$d2);
		$startDateHist->modify('-52 weeks');
		//$startDateHist->modify("next {$startDay}");
		$startDateHist->modify("-{$changeS} days");
		$endDateHist->modify('-52 weeks');
		//$endDateHist->modify("next {$endDay}");
		$endDateHist->modify("+{$changeE} days");

		$chartLabels = array();
		$labelDate = DateTime::createFromFormat('Y-m-d', $startDate->format('Y-m-d'));
		$labelDate->modify('this Saturday');
		$changeL = $interval->d+1;
		for ($i=0; $i < 8; $i++) { 
			$chartLabels[] = $labelDate->format('m-d');
			$labelDate->modify("+{$changeL} days");
		}

		echo '<script>console.log(" Interval:'.$interval->d.'");</script>';
		
		$args= array($startDate->format('Ymd'), $endDate->format('Ymd'), $store);
		$custCountQ = $dbc->prepare("SELECT 
			count(distinct concat(t.trans_num, t.date_id)) AS customerCount, t.date_id 
			FROM {$dlog} t 
			WHERE t.date_id BETWEEN ? AND ? AND t.store_id=? 
			AND WEEK(t.tdate) != WEEK(NOW())
			GROUP BY t.date_id");
		$custCountR = $dbc->execute($custCountQ,$args);


		
		$chartLine1 = array();
		$chartLine2 = array();
		$i = 0;
		$key = -1;
		$countLine = false;
		while($row = $dbc->fetchRow($custCountR)) {
			if($i==0) {
				$startDateI = DateTime::createFromFormat('Ymd', $row[1]);
				$countLine = ($startDateI->format('Y-m-d') == $d1) ? true : false ;
				//$chartLabels[] = $startDateI->format('m-d');
				$chartLine1[] = $row[0];
				if($countLine)
					$this->customerCount += $row[0];
				$key++;
			} else {
				$chartLine1[$key] += $row[0];
				if($countLine)
					$this->customerCount += $row[0];
			} 

			$i = ($i < $interval->d) ? $i+1 : 0 ;
		}
		
		$dlogHist = DTransactionsModel::selectDLog($startDateHist->format('Y-m-d'),$endDateHist->format('Y-m-d'));
		$args = array($startDateHist->format('Ymd'),$endDateHist->format('Ymd'),$store);
		$custCtHistQ = $dbc->prepare("SELECT 
			count(distinct concat(t.trans_num, t.date_id)) AS customerCount, t.date_id 
			FROM {$dlogHist} t 
			WHERE t.date_id BETWEEN ? AND ? AND t.store_id=? 
			AND WEEK(t.tdate) != WEEK(NOW())
			GROUP BY t.date_id");
		$custCtHistR = $dbc->execute($custCtHistQ,$args);
		$key = -1;
		$i = 0;
		while($row = $dbc->fetchRow($custCtHistR)) {
			if($i==0) {
				$key++;
				//$startDateI = DateTime::createFromFormat('Ymd', $row[1]);
				$chartLine2[] = $row[0];
				//$table[$key][] = $row[0];	
			} else {
				$chartLine2[$key] += $row[0];
				//$table[$key][2] = $table[$key][2] + $row[0];
			} 

			$i = ($i < $interval->d) ? $i+1 : 0 ;
		}

		$this->custChart = array($chartLabels,$chartLine1,$chartLine2);
		

		//echo '<script>console.log(" Table:'.$this->customerCount.'");</script>';

		return true;

	}

	function getFiscalYearBalnce($date,$dbc, $store, $dlog) {
		$endDate = new DateTime($date->format('Y-m-d'));
		if($date->format('n') >= 10) {
       		$date->modify('first day of october');
   		} else {
       		$date->modify('first day of october last year');
   		}
   		$date->setTime(00,00,00);
		$endDate->setTime(23,59,58);
   		$args = array($date->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'),$store);
   		$salesTotalQ = $dbc->prepare("");

   		$salesTotalQ = $dbc->prepare("SELECT SUM(t.total), s.superID FROM {$dlog} t
			JOIN core_op.superdepts s on t.department=s.dept_ID
			WHERE  t.tdate BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID<15
			GROUP BY s.superID ORDER BY s.superID");
   		$salesTotalR = $dbc->execute($salesTotalQ,$args);

   		$args = array($date->format('Y-m-d'), $endDate->format('Y-m-d'),$store);
		$budgetTotalQ = $dbc->prepare("SELECT SUM(b.budget) , m.superDeptNo
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m ON b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ?
			GROUP BY m.superDeptNo ORDER BY m.superDeptNo");
		$budgetTotalR = $dbc->execute($budgetTotalQ, $args);

		$yearBalance = array();
		$yearBalance[] = 0;
		while($row = $dbc->fetchRow($salesTotalR)) {
			$yearBalance[0] += $row[0];
			$yearBalance[] = $row[0];
		}
		//$return = array();
		$budget = array();
		$budget[] = 0;
		$key = 0;
		while ($row = $dbc->fetchRow($budgetTotalR)) {
			$key++;
			$yearBalance[0] -= $row[0];
			if ($key < 13)
				$yearBalance[$key] = $yearBalance[$key] - $row[0];
			//$key++;
		}

		return $yearBalance;
   	}


	function calculate_footers($data)
    {
		$store = FormLib::get('store', 0);
		//switch($this->multi_counter){
		$this->report_headers[0] = $this->tableNames[$this->multi_counter];

		if($this->tableNames[$this->multi_counter] != '') {
			$budgetQty=0.0;
			$thisQty=0.0;
			$lastQty=0.0;
			$budgetDiff = 0;
			$lastDiff = 0;
			$yearBal = 0;
			foreach($data as $key => $row){
            	$number = str_replace(',', '', ltrim($row[2],'$'));
            	$budgetQty += $number;
            	$number = str_replace(',', '', ltrim($row[1],'$'));
            	$thisQty += $number;
            	$number = str_replace(',', '', ltrim($row[4],'$'));
            	$lastQty += $number;
            	$number = str_replace(',', '', ltrim($row[6],'$'));
            	$yearBal += $number;
       	 	}
       	 	if($thisQty != 0 && $budgetQty!=0) {
       	 		$budgetDiff = number_format((floatval($thisQty)/floatval($budgetQty))*100).'%';
       	 		$lastDiff = number_format((1 - floatval($lastQty) / floatval($thisQty))*100).'%';
       	 	}
       	 	$budgetQty = '$'.number_format($budgetQty,2);
       	 	$thisQty = '$'.number_format($thisQty,2);
       	 	$lastQty = '$'.number_format($lastQty,2);
 			$yearBal = '$'.number_format($yearBal,2);
       	 	
       	 	return array('Totals',$thisQty,$budgetQty,$budgetDiff,$lastQty,$lastDiff,$yearBal);
		} elseif ($this->multi_counter == 1) {
			$number = str_replace(',', '', ltrim($data[0][1],'$'));
			$basketSize = '$'.number_format($number/$this->customerCount,2);
			return array('','Customer Count',$this->customerCount,'' ,'Basket Size', $basketSize, '');
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
    			var deptCanvases ='.json_encode($this->deptCanvases).';
    			var canvasPos = '.json_encode($this->canvasPos).';
    			var chartTitles = '.json_encode($this->deptNames).';
    			var custChart = '.json_encode($this->custChart).';
    			var basketChart = '.json_encode($this->basketChart).';
			</script>';

            $this->addOnloadCommand('budgetSales.totals('.(count($this->report_headers)-3).');');
            $this->addOnloadCommand('budgetSales.chartAll('.(count($this->report_headers)-3).');');
            $this->addOnloadCommand('budgetSales.chartBaskets();');
        }

        return $default;
    }



	function form_content()
    {
    	/*
        <div class="form-group">
           <?php echo $this->superOpts('buyer'); ?>
        </div>
        */
        ob_start();
        ?>
        <form action=BudgetSalesReport.php method=get>
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
