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
    protected $header = "Budget Sales Report Report (Wait it takes about a minute to load)";
    protected $report_cache = 'none';
    
    protected $sortable = false;
    protected $no_sort_but_style = true;
    protected $new_tablesorter = true;
    protected $multi_report_mode = true;

    protected $report_headers = array('Dept Name','Sales','Budget','% Budget', 'Last Year Sales', '% Change', 'Year Balance');
    protected $required_fields = array('date1','date2');
  	
  	protected $chartTitles = array(0 => 'Store Totals', 1 => 'Bakery', 2=> 'PFD', 3=>'Grocery', 4=>'Bulk', 5=>'Cheese', 6=>'Dairy', 7=>'Frozen',
    	8=>'Meat',9=>'Produce', 10=>'Body Care', 11 =>'Genral Merch', 12=>'Supplements');
    protected $deptNames = array(0 => 'Store Totals', 1 => 'Bakery', 2=> 'PFD', 3=>'Grocery', 4=>'Bulk', 5=>'Beer', 6=>'Cheese', 7=>'Dairy', 8=>'Frozen',
    	9=>'Meat',10=>'Produce', 11=>'Body Care', 12 =>'Genral Merch', 13=>'Supplements');
    protected $deptToSuper = array(0=>0, 1=>1, 2=>2,3=>3, 4=>4, 5=>4, 6=>5, 7=>5,8=>5, 9=>5, 10=>6, 11=>7, 12=>7,13=>7);
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
        // grab input
        $dbc = FannieDB::get($FANNIE_OP_DB);
		$d1 = FormLib::get_form_value('date1',date('Y-m-d'));
		$d2 = FormLib::get_form_value('date2',date('Y-m-d'));
		$superDepts = FormLib::get_form_value('buyer','');
		$store = FormLib::get('store');
		$superDepts = (!$superDepts) ? 'All' : $superDepts;

		
		$report = array();

		// get dates
		$dates = $this->calcDates($d1, $d2);
		// select dlog
		$dlog = DTransactionsModel::selectDLog($dates['start']->format('Y-m-d'),$dates['end']->format('Y-m-d'));
		$dlogHist = DTransactionsModel::selectDLog($dates['historyStart']->format('Y-m-d'),$dates['historyEnd']->format('Y-m-d'));

		//$yearTotals = $this->getFiscalYearBalnce($dates['end'],$dbc, $store, $dlog);
		$departmentTotals = $this->getDeptTotalsNew($dbc, $dlog, $dlogHist,$store,$dates);
		$storeTotals = $this->getStoreTotals($dbc, $dlog, $dlogHist,$store,$dates);
		$yearTotals = $this->getFiscalYearBalnce($d1,$d2,$dbc, $store);
		$this->getCustomerCount($dbc, $dlog, $dlogHist,$store,$dates,$storeTotals['thisYear'],$storeTotals['lastYear']);

		$return = array();
		$table = array();
		$thisTotal = $storeTotals['thisYear'][$d1];
		$lastTotal = $storeTotals['lastYear'][$d1];
		$budgetTotal = $storeTotals['budget'][$d1];
		$budgetPercent = number_format(($thisTotal/$budgetTotal)*100,2).'%';
		$changeOverLast = number_format(($thisTotal/$lastTotal)*100,2).'%';
		$thisTotal = '$'.number_format($thisTotal,2);
		$lastTotal = '$'.number_format($lastTotal,2);
		$budgetTotal ='$'.number_format($budgetTotal,2);
		$yearBudget = '$'.number_format($yearTotals[0],2);

		$row = array($this->deptNames[0], $thisTotal,$budgetTotal,$budgetPercent,$lastTotal,$changeOverLast,$yearBudget);
		$table[] = $row;
		$return[0] = $table;


		$table = array();


		//$return = array('',$storeTotals[0][1],$storeTotals[1][1], $storeTotals[2][1]);
		$row = array();
		foreach ($departmentTotals as $deptKey => $department) {
			$thisYearSales = $department[1][$d1];
			$lastYearSales = $department[2][$d1];
			$budget = $department[3][$d1];
			$budgetPercent = number_format(100*($thisYearSales/$budget),2).'%';
			$yearChange = number_format(100*($lastYearSales/$thisYearSales),2).'%';
			$yearBudget = '$'.number_format($yearTotals[$deptKey],2);
			$thisYearSales = '$'.number_format($thisYearSales,2);
			$lastYearSales = '$'.number_format($lastYearSales,2);
			$budget ='$'.number_format($budget,2);

			$row = array($this->deptNames[$deptKey], $thisYearSales,$budget,$budgetPercent,$lastYearSales,$yearChange,$yearBudget);
			switch ($deptKey) {
				case '6':
				case '7':
				case '8':
					$table[] = $row;
					break;
				case '11':
				case '12':
					$table[] = $row;
					break;
				default:
					$table[] = $row;
					$tableKey = $this->deptToSuper[$deptKey];
					$return[$tableKey] = $table;
					$table = array();
					$row = array();
					break;
			}

			$this->deptCharts[] = array(array_values($department[0]), array_values($department[3]),array_values($department[1]),array_values($department[2]));			
		}

		$this->totalsChart = array(array_values($storeTotals['weekDateStarts']), array_values($storeTotals['budget']),array_values($storeTotals['thisYear']),array_values($storeTotals['lastYear']));

		return $return;
	}

	private function getStoreTotals($dbc, $dlog, $dlogHist, $store, $dates) {
		//prepare and exicute all the SQL so we can sort it in our loops.
		$start = $dates['start'];
		$end = $dates['end'];

		$args = array($start->format('Y-m-d Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'), $store);
		$thisYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total)
			FROM {$dlog} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			AND WEEK(t.tdate) != WEEK(NOW())
			GROUP BY DATE(t.tdate)
            ORDER BY DATE(t.tdate)");
		$thisYearR  = $dbc->execute($thisYearQ,$args);
		
        //$end->modify('-1 day');
		$args = array($start->format('Y-m-d'),$end->format('Y-m-d'), $store);
		$budgetQ = $dbc->prepare("SELECT b.budgetDate, SUM(b.budget)
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
			GROUP BY b.budgetDate
			ORDER BY b.budgetDate");
		$budgetR = $dbc->execute($budgetQ, $args);

		$startHist = $dates['historyStart'];
		$endDateHist = $dates['historyEnd'];

		$args = array($startHist->format('Y-m-d H:i:s'),$endDateHist->format('Y-m-d H:i:s'),$store);
		$lastYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total)
			FROM {$dlogHist} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			GROUP BY DATE(t.tdate)
            ORDER BY DATE(t.tdate)");
		$lastYearR = $dbc->execute($lastYearQ,$args);

		$intervals = $dates['intervals'];
		$historicals =$dates['historicals'];
		
		
		//inerval counters for while loop
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		$writeInterval = false;


		$data = array(); // 'Dept' => array(thisData,$lastData, $budgetData)
		//arrays for saving intervals once the sum is complate.
		$thisData = array();
		$lastData = array();
		$budgetData = array();
		$dateLables = array();
		//variables for summing one interval.
		$thisTotal=0;
		$lastTotal=0;
		$budgetTotal=0;
		//format the results into our intervals.
		while($budgetRow = $dbc->fetchRow($budgetR)) {
			$lastRow = $dbc->fetchRow($lastYearR);
			$thisRow = $dbc->fetchRow($thisYearR);
			$currentDate = $budgetRow[0];
			$writeInterval = false;
			//if the current date is less then the start of the next interval then
			//we are still inside the current interval with the exception of when we swich departments.
			if($currentDate < $intervals[$intervalKey]) {

				$thisTotal += $thisRow[1];
				$lastTotal += $lastRow[1];
				$budgetTotal += $budgetRow[1];

			} else { // we are in a new interval need to write out the last, reset coutners, and start a new sum
				$writeInterval = true;
			}

			//write out the interval, happens two places in the last if so I put it here for bervity.
			if($writeInterval) {
				$writeInterval = false;
				//write out the old interval into the new array.
				$thisData[$intervalDate] = $thisTotal;
				$lastData[$intervalDate] =  $lastTotal;
				$budgetData[$intervalDate] = $budgetTotal;
				$dateLables[] = $intervalDate;
				//reset our interval counters
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				//start a new sum for the new interval.
				$thisTotal = $thisRow[1];
				$lastTotal = $lastRow[1];
				$budgetTotal = $budgetRow[1];
				
			}
		}


		//the last interval doesn't catch so we need to write out the last one outside the loop
		//$thisData[$intervalDate] = $thisTotal;
		//$lastData[$historicals[$intervalKey+1]] =  $lastTotal;
		//$budgetData[$intervalDate] = $budgetTotal;
		$return = array('weekDateStarts'=> $dateLables,
						'thisYear' => $thisData, 
						'lastYear' => $lastData, 
						'budget' => $budgetData);

		return $return;
	}

	private function getDeptTotalsNew($dbc, $dlog, $dlogHist, $store, $dates) {
		$return = array();
		//prepare and exicute all the SQL so we can sort it in our loops.
		$start = $dates['start'];
		$end = $dates['end'];

		$args = array($start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'), $store);
		$thisYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total), s.superID
			FROM {$dlog} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			AND WEEK(t.tdate) != WEEK(NOW())
			GROUP BY s.superID,DATE(t.tdate)
            ORDER BY s.superID,DATE(t.tdate)");
		$thisYearR  = $dbc->execute($thisYearQ,$args);
		
		$end->modify('-1 day');
		$args = array($start->format('Y-m-d'),$end->format('Y-m-d'), $store);
		$budgetQ = $dbc->prepare("SELECT b.budgetDate, SUM(b.budget),m.superDeptNo
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
			GROUP BY m.superDeptNo, b.budgetDate
			ORDER BY m.superDeptNo,b.budgetDate");
		$budgetR = $dbc->execute($budgetQ, $args);

		$end->modify('+1 day');
		$startHist = $dates['historyStart'];
		$endDateHist = $dates['historyEnd'];

		$args = array($startHist->format('Y-m-d H:i:s'),$endDateHist->format('Y-m-d H:i:s'),$store);
		$lastYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total), s.superID
			FROM {$dlogHist} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			GROUP BY s.superID,DATE(t.tdate)
            ORDER BY s.superID,DATE(t.tdate)");
		$lastYearR = $dbc->execute($lastYearQ,$args);

		$intervals = $dates['intervals'];
		$historicals =$dates['historicals'];
		
		//department counters for while loop
		$currentDept = 1;
		//$lastDept = 0; //we need to track this because we only know to write the last line when we see the new dept on the next.
		$nextDept = 2;
		$writeDept = false;
		
		//inerval counters for while loop
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		$writeInterval = false;


		$data = array(); // 'Dept' => array(thisData,$lastData, $budgetData)
		//arrays for saving intervals once the sum is complate.
		$thisData = array();
		$lastData = array();
		$budgetData = array();
		$theseDates = array();
		//variables for summing one interval.
		$thisTotal=0;
		$lastTotal=0;
		$budgetTotal=0;
		//format the results into our intervals.
		while($budgetRow = $dbc->fetchRow($budgetR)) {
			$lastRow = $dbc->fetchRow($lastYearR);
			$thisRow = $dbc->fetchRow($thisYearR);
			$lastDept = $currentDept; //this will give us the proper department when we write the last line.
			$currentDept = $budgetRow[2];
			$currentDate = $budgetRow[0];
			$writeInterval = false;
			$writeDept = false;
			//if the current date is less then the start of the next interval then
			//we are still inside the current interval with the exception of when we swich departments.
			if($currentDate < $intervals[$intervalKey]) {
				if($currentDept == $nextDept) {
					//increment our department counter.
					$nextDept++;
					if($store == 1 && $nextDept == 5) {$nextDept = 6;};
					//write the interval.
					$intervalKey = 0;
					$writeInterval = true;
					$writeDept = true;
				} else { // if the date and the dept are both good we can just keep summing here.
					$thisTotal += $thisRow[1];
					$lastTotal += $lastRow[1];
					$budgetTotal += $budgetRow[1];
				}
			} else { // we are in a new interval need to write out the last, reset coutners, and start a new sum
				$writeInterval = true;
			}

			//write out the interval, happens two places in the last if so I put it here for bervity.
			if($writeInterval) {
				$writeInterval = false;
				//write out the old interval into the new array.
				$theseDates[] = $intervalDate;
				$thisData[$intervalDate] = $thisTotal;
				$lastData[$intervalDate] = $lastTotal;
				$budgetData[$intervalDate] = $budgetTotal;
				
				if ($writeDept) {
					$data[$lastDept] = array($theseDates,$thisData,$lastData,$budgetData);
					//zero out the department daata for the new department.
					$thisData = array();
					$lastData = array();
					$budgetData = array();
					$theseDates = array();
				}

				//reset our interval counters
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				//start a new sum for the new interval.
				$thisTotal = $thisRow[1];
				$lastTotal = $lastRow[1];
				$budgetTotal = $budgetRow[1];
			}
		}
		
		$theseDates[] = $intervalDate;
		$thisData[$intervalDate] = $thisTotal;
		$lastData[$intervalDate] = $lastTotal;
		$budgetData[$intervalDate] = $budgetTotal;
		$data[$lastDept] = array($theseDates,$thisData,$lastData,$budgetData);
		//$return = array($thisData, $lastData, $budgetData);


		return $data;
	}

	function getCustomerCount ($dbc,$dlog,$dlogHist, $store, $dates,$totals,$histTotals) {

		$start = $dates['start'];
		$end = $dates['end'];

		$args= array($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $store);
		$countQ = $dbc->prepare("SELECT DATE(tdate), SUM(DISTINCT(trans_num))
            FROM {dlog} t
            WHERE 
            t.tdate BETWEEN ? and ?
            and t.trans_type = 'T'
            AND t.upc <> 'RRR'
            AND t.store_id = ?
            group by DATE(t.tdate)
            order by Date(t.tdate)");
		$countR = $dbc->execute($countQ,$args);


		$startHist = $dates['historyStart'];
		$endDateHist = $dates['historyEnd'];

		$args = array($startHist->format('Y-m-d H:i:s'),$endDateHist->format('Y-m-d H:i:s'), $store);
		$historyQ = $dbc->prepare("SELECT DATE(tdate), SUM(DISTINCT(trans_num))
            FROM {$dlogHist} t
            WHERE 
            t.tdate BETWEEN ? and ?
            and t.trans_type = 'T'
            AND t.upc <> 'RRR'
            AND t.store_id = ?
            group by DATE(t.tdate)
            order by Date(t.tdate)");
		$historyR = $dbc->execute($historyQ,$args);
		
		$basketData = array();
		$basketHistory = array();
		$countData = array();
		$countTotal = 0;
		$historyData = array();
		$historyTotal = 0;
		$count = 0;

		$chartLabels = array();

		//inerval counters for while loop
		$intervals = $dates['intervals'];
		$historicals = $dates['historicals'];
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		//$historicalDate = $historicals[0];
		
		while($historyRow = $dbc->fetchRow($historyR)) {
			$countRow = $dbc->fetchRow($countR);
			$currentDate = $historyRow[0];
			if($currentDate < $historicals[$intervalKey]) {
				$countTotal += $countRow[1];
				$historyTotal += $historyRow[1];
				if($intervalKey == 3) {
					$count += $countRow[1];
				}
			} else { // we are in a new interval need to write out the last, reset coutners, and start a new sum
				$countData[] = $countTotal;
				$historyData[] = $historyTotal;
				$chartLabels[] = $intervalDate;
				$basketData[] = $totals[$intervalDate]/$countTotal;
				$basketHistory[] = $histTotals[$intervalDate]/$historyTotal;
				//reset our interval counters
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				// start new sums
				$countTotal = $countRow[1];
				$historyTotal = $historyRow[1];
			}
		}

		$countData[] = $countTotal;
		$historyData[] = $historyTotal;
		$chartLabels[] = $intervalDate;

		$this->custChart = array($chartLabels,$countData,$historyData);
		$this->basketChart = array($chartLabels,$basketData, $basketHistory);
		
		$this->customerCount = $count;

		return true;

	}

	function getFiscalYearBalnce($d1, $d2,$dbc, $store) {
		$endDate = DateTime::createFromFormat('Y-m-d',$d2);
		$intervalDate = DateTime::createFromFormat('Y-m-d', $d1);
		// our fiscal year starts on october 1st so we need to back out to the correct one.
		if($intervalDate->format('n') >= 10) {
       		$intervalDate->modify('first day of october');
   		} else {
       		$intervalDate->modify('first day of october last year');
   		}
   		$intervalDate->setTime(00,00,00);
		$endDate->setTime(23,59,58);
   		$args = array($intervalDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'),$store);


   		$dlog = DTransactionsModel::selectDLog($intervalDate->format('Y-m-d'),$endDate->format('Y-m-d'));
   		$salesTotalQ = $dbc->prepare("SELECT SUM(t.total), s.superID FROM {$dlog} t
			JOIN core_op.superdepts s on t.department=s.dept_ID
			WHERE  t.tdate BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID between 1 and 13 and s.superID !=5
			GROUP BY s.superID ORDER BY s.superID");
   		$salesTotalR = $dbc->execute($salesTotalQ,$args);

   		$args = array($intervalDate->format('Y-m-d'), $endDate->format('Y-m-d'),$store);
		$budgetTotalQ = $dbc->prepare("SELECT SUM(b.budget) , m.superDeptNo
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m ON b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ?
			GROUP BY m.superDeptNo ORDER BY m.superDeptNo");
		$budgetTotalR = $dbc->execute($budgetTotalQ, $args);

		$yearBalance = array();
		$yearBalance[] = 0;
		
		$budget = array();
		$budget[] = 0;
		$key = 0;
		while($budgetRow = $dbc->fetchRow($budgetTotalR)) {
			$salesRow = $dbc->fetchRow($salesTotalR);
			$deptKey = $budgetRow[1];
			$key++;
			$yearBalance[0] += $salesRow[0] - $budgetRow[0];
			if($key < 13)
				$yearBalance[$deptKey] = $salesRow[0] - $budgetRow[0]; 
		}

		return $yearBalance;
   	}

   	private function calcDates($d1, $d2) {
   		// we always have 8 intervals start dates, table inserval the user selected and three before
		// and three after for display on the graph the extra is that start of the next interval to
		// use as an end date in searches and for less then comparisons.
		$intervals = array(); //interval start dates for current year and budget
		$historicals = array(); //interval start dates for last year.
		$tableIntervalDate = DateTime::createFromFormat('Y-m-d', $d1); //interval start to display on the table.

		$startDate = DateTime::createFromFormat('Y-m-d', $d1);
		$startDateLastYear = DateTime::createFromFormat('Y-m-d', $d1);
		$startDateLastYear->modify('-52 weeks');
		$endDate = DateTime::createFromFormat('Y-m-d', $d2);
		$interval = $startDate->diff($endDate);

		$delta = '';
		$startDelta = '';
		//are we in a month?
		if($startDate->format('Y-m-d') == $startDate->format('Y-10-01') 
   			&& $endDate->format('Y-m-d') == $endDate->format('Y-09-t')){
			// iinterval is a year.
			$startDelta = "-4 years";
			$delta = "+1 year";
		} else if($startDate->format('Y-m-d') == $startDate->format('Y-m-01') 
		 			 && $endDate->format('Y-m-d') == $startDate->format('Y-m-t')) {
			//inserval is a single month 
			$startDelta = "-4 months";
			$delta = "+1 month";
	
		} else if($startDate->format('Y-m-d') == $startDate->format('Y-m-01') 
				  && $endDate->format('Y-m-d') == $endDate->format('Y-m-t')) {
			//inserval is a number of months.
			$startDelta = "-".(4*($interval->m+1))." months";
			$delta = "+".($interval->m+1)." months";
	
		} else if(($interval->d +1)%7 == 0) {
			// the interval is some number of weeks
			$delta = "+".(($interval->d+1)/7)."weeks"; //how many weeks is the interval
			$startDelta = "-".(4*(($interval->d+1)/7))." weeks"; //how far back to start
	
		} else {
			//interval is in days;
			$delta = "+".($interval->d+1)." days"; //how may days is the interval
			$startDelta = "-".(4*$delta)." days"; //how far back to start
	
		}

		$startDate->modify($startDelta);
		$startDateLastYear->modify($startDelta);

		for($i =0;$i<8;$i++) {
			$startDate->modify($delta);
			$startDateLastYear->modify($delta);
			$intervals[] = $startDate->format('Y-m-d');
			$historicals[] = $startDateLastYear->format('Y-m-d');
		}

		$start = DateTime::createFromFormat('Y-m-d',$intervals[0]);
		$start->setTime(00,00,00);
		$end = DateTime::createFromFormat('Y-m-d',$intervals[7]);
		$end->setTime(00,00,00);
		$historyStart = DateTime::createFromFormat('Y-m-d',$historicals[0]);
		$historyStart->setTime(00,00,00);
		$historyEnd = DateTime::createFromFormat('Y-m-d',$historicals[7]);
		$historyEnd->setTime(00,00,00);

		$endDate->setTime(23,59,58);

   		return array(
   			"intervals" => $intervals,
   			"historicals" => $historicals,
   			"tableIntervalDate" => $tableIntervalDate,
   			"start" => $start,
   			"end" => $end,
   			"historyStart" => $historyStart,
   			"historyEnd" => $historyEnd
   		);
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
    			var chartTitles = '.json_encode($this->chartTitles).';
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
