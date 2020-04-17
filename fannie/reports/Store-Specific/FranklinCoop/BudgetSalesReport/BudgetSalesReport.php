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
    protected $deptToSuper = array(0=>0, 1=>1, 2=>2,3=>3, 4=>4, 5=>4, 6=>5, 7=>5,8=>5, 9=>5, 10=>7, 11=>8, 12=>8, 13=>8);
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


		echo 'StartDate: '.$dates['start']->format('Y-m-d').' EndDate: '.$dates['end']->format('Y-m-d').'</br>';
		echo 'dlog: '.$dlog.' dlogHist: '.$dlogHist.'/br';
		//$yearTotals = $this->getFiscalYearBalnce($dates['end'],$dbc, $store, $dlog);
		$departmentTotals = $this->getDeptTotalsNew($dbc, $dlog, $dlogHist,$store,$dates, $dates['cutOffDate']);
		$storeTotals = $this->getStoreTotals($dbc, $dlog, $dlogHist,$store,$dates);
		//$this->calculateStoreTotals($departmentTotals,$dates['cutOffDate']);
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
			$yearChange = number_format(100*($thisYearSales/$lastYearSales),2).'%';
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

	private function calculateStoreTotals($deptData, $cutOffDate) {
		// not complate, something I was trying to try and improve preformance by not having
		// to double queiers.
		$dateLables = $deptData[1][0];
		$thisData = array();
		$lastData = array();
		$budgetData = array();
		foreach ($deptData as $deptKey => $dept) {
			foreach ($dateLables as $dateKey => $intervalDate) {
				if (!(array_key_exists($intervalDate, $budgetData))) {
					if ($intervalDate <= $cutOffDate)
						$thisData[] = array($intervalDate => 0);
					else
						$thisData[] = array($intervalDate => Null);
					$lastData[] = array($intervalDate =>0);
					$budgetData[] = array($intervalDate =>0);
				}
				if ($intervalDate <= $cutOffDate)
					$thisData[$intervalDate] += $dept[1][$intervalDate];
				$lastData[$intervalDate] += $dept[2][$intervalDate];
				$budgetData[$intervalDate] += $dept[3][$intervalDate];
			}
		}


		// creates a return array.
		$return = array('weekDateStarts'=> $dateLables,
						'thisYear' => $thisData, 
						'lastYear' => $lastData, 
						'budget' => $budgetData);

		return $return;
	}

	private function getStoreTotals($dbc, $dlog, $dlogHist, $store, $dates) {
		//prepare and exicute all the SQL so we can sort it in our loops.
		$start = $dates['start'];
		$end = $dates['end'];
		$cutOffDate = $dates['cutOffDate'];

		// query this years sales data.
		$args = array($start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'), $store);
		$thisYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total)
			FROM {$dlog} t
			JOIN core_op.superdepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			GROUP BY DATE(t.tdate)
            ORDER BY DATE(t.tdate)");
		$thisYearR  = $dbc->execute($thisYearQ,$args);
		
		// query last years sales data
        $end->modify('-1 day'); //We get the wrong set if we don't do this, has to do with DATE vs DATETIME
		$args = array($start->format('Y-m-d'),$end->format('Y-m-d'), $store);
		$budgetQ = $dbc->prepare("SELECT b.budgetDate, SUM(b.budget)
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
			GROUP BY b.budgetDate
			ORDER BY b.budgetDate");
		$budgetR = $dbc->execute($budgetQ, $args);
		$end->modify('+1 day'); //fix what we did on line 188ish

		$startHist = $dates['historyStart'];
		$endDateHist = $dates['historyEnd'];
		// Query historical data.
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
		$nextInterval = $intervals[$intervalKey];

		//$writeInterval = false;


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
			//if the current date is less then the start of the next interval then
			//we are still inside the current interval.
			if($currentDate == $nextInterval) {
				//reset our interval counters
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				$nextInterval = $intervals[$intervalKey];
			} 

			// add the interval.
			if (!(array_key_exists($intervalDate, $lastData))){
				$dateLables[] = $intervalDate;
				$lastData = array_merge($lastData, array($intervalDate => 0));
				$budgetData = array_merge($budgetData, array($intervalDate => 0));
				if($intervalDate <= $cutOffDate) // stop grahing data if the interval is incomplate.
					$thisData = array_merge($thisData, array($intervalDate =>0 ));
				else
					$thisData = array_merge($thisData, array($intervalDate => Null));
			}
			
			//sum the interval.
			if($intervalDate <= $cutOffDate) // stop grahing data if the interval is incomplate.
				$thisData[$intervalDate] += $thisRow[1];
			$lastData[$intervalDate] += $lastRow[1];
			$budgetData[$intervalDate] += $budgetRow[1];
		}

		// creates a return array.
		$return = array('weekDateStarts'=> $dateLables,
						'thisYear' => $thisData, 
						'lastYear' => $lastData, 
						'budget' => $budgetData);

		return $return;
	}

	private function getDeptTotalsNew($dbc, $dlog, $dlogHist, $store, $dates, $cutOffDate) {
		$return = array();
		//prepare and exicute all the SQL so we can sort it in our loops.
		$start = $dates['start'];
		$end = $dates['end'];

		$args = array($start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'), $store);
		$thisYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total), s.superID
			FROM {$dlog} t
			JOIN core_op.MasterSuperDepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14 and s.superID != 5
			GROUP BY DATE(t.tdate),s.superID
            ORDER BY DATE(t.tdate),s.superID");
		$thisYearR  = $dbc->execute($thisYearQ,$args);
		
		$end->modify('-1 day');
		$args = array($start->format('Y-m-d'),$end->format('Y-m-d'), $store);
		$budgetQ = $dbc->prepare("SELECT b.budgetDate, SUM(b.budget),m.superDeptNo
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			WHERE b.budgetDate BETWEEN ? AND ?  AND m.storeNo = ?
			GROUP BY b.budgetDate, m.superDeptNo
			ORDER BY b.budgetDate, m.superDeptNo");
		$budgetR = $dbc->execute($budgetQ, $args);

		$end->modify('+1 day');
		$startHist = $dates['historyStart'];
		$endDateHist = $dates['historyEnd'];

		$args = array($startHist->format('Y-m-d H:i:s'),$endDateHist->format('Y-m-d H:i:s'),$store);
		$lastYearQ = $dbc->prepare("SELECT DATE(t.tdate), sum(t.total), s.superID
			FROM {$dlogHist} t
			JOIN core_op.MasterSuperDepts s on t.department = s.dept_ID
			WHERE t.`tdate` BETWEEN ? AND ?  AND t.store_id = ?
			AND t.trans_type IN ('D', 'I') AND s.superID < 14
			GROUP BY DATE(t.tdate), s.superID
            ORDER BY DATE(t.tdate), s.superID");
		$lastYearR = $dbc->execute($lastYearQ,$args);

		$intervals = $dates['intervals'];
		$historicals = $dates['historicals'];
		
		//department counters for while loop
		$currentDept = 1;
		//$lastDept = 0; //we need to track this because we only know to write the last line when we see the new dept on the next.
		$nextDept = 2;
		$writeDept = false;
		
		//inerval counters for while loop
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		$nextInterval = $intervals[$intervalKey];
		$writeInterval = false;


		$data = array(); // 'Dept' => array(thisData,$lastData, $budgetData)

		//format the results into our intervals.
		while($budgetRow = $dbc->fetchRow($budgetR)) {
			$lastRow = $dbc->fetchRow($lastYearR);
			$thisRow = $dbc->fetchRow($thisYearR);
			$currentDate = $budgetRow[0];
			
			//echo '</br> Budget Date: '.$budgetRow[0].' LastDate: '.$lastRow[0].' This Date: '.$thisRow[0].'</br>';
			//echo '</br> Budget Total: '.$budgetRow[1].' Last Total: '.$lastRow[1].' This Total: '.$thisRow[1].'</br>';
			//echo '</br> Budget Dept: '.$budgetRow[2].' Last Dept: '.$lastRow[2].' This Dept: '.$thisRow[2].'</br>';
			//echo '</br></br> Next Row</br>';

			$department = $lastRow[2];
			//create the department and the first interval for all if it doesn't exisit
			if(!(array_key_exists($department, $data))) {
				//echo '***Department**** '.$department.'</br>';
				$data[$department] = array();
				$data[$department][] = array($intervalDate);
				if ($intervalDate <= $cutOffDate)
					$data[$department][] = array($intervalDate => 0);
				else
					$data[$department][] = array($intervalDate => Null);
				$data[$department][] = array($intervalDate => 0);
				$data[$department][] = array($intervalDate => 0);
			}

			//set the interval and incrament when we are at the end of an interval.
			if($currentDate == $nextInterval) {
				//echo '</br> CurrentDate'.$currentDate.' Next Interval Start Date'.$nextInterval.'</br>';
								//$data[$department][0][] = $intervalDate;
				$lastIntervalDate = $intervalDate;
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				$nextInterval = $intervals[$intervalKey];
				//foreach ($data as $key => $value) {
				//		$data[$key][0][] = $intervalDate;
				//		//if(!is_null($thisRow)) 
				//		array_merge($data[$key][1] ,array($intervalDate => $thisRow[1]));
				//		array_merge($data[$key][2] ,array($intervalDate => $lastRow[1]));
				//		array_merge($data[$key][3] ,array($intervalDate => $budgetRow[1]));
				//}
			} else {

			}
			if(!(array_key_exists($intervalDate, $data[$department][1]))) {
				$data[$department][0][] = $intervalDate;
				if ($intervalDate <= $cutOffDate) //cuts the current sales off at the end date.
					$data[$department][1] =  array_merge($data[$department][1] ,array($intervalDate => 0));
				else
					$data[$department][1] =  array_merge($data[$department][1] ,array($intervalDate => Null));
				$data[$department][2] =  array_merge($data[$department][2] ,array($intervalDate => 0));
				$data[$department][3] =  array_merge($data[$department][3] ,array($intervalDate => 0));
			}

			if($intervalDate <= $cutOffDate) {
				$data[$department][1][$intervalDate] += $thisRow[1];//  = array(array(),array(), array()); dept(dates, this, last)
			} 
			
			
			$data[$department][2][$intervalDate] += $lastRow[1];
			$data[$department][3][$intervalDate] += $budgetRow[1];

			//if(!is_null($thisRow)) 


		}

				//inerval counters for while loop
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		$nextInterval = $intervals[$intervalKey];
		$writeInterval = false;
		while ($thisRow = $dbc->fetchRow($thisYearR)) {
		 	$currentDate = $thisRow[0];
		 	$department = $thisRow[2];

		 	if($currentDate == $nextInterval) {
				$lastIntervalDate = $intervalDate;
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				$nextInterval = $intervals[$intervalKey];
			}

			$data[$department][1][$intervalDate] += $thisRow[1];
		}

		return $data;
	}

	function getCustomerCount ($dbc,$dlog,$dlogHist, $store, $dates,$totals,$histTotals) {

		$start = $dates['start'];
		$end = $dates['end'];

		$args= array($start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $store);
		$countQ = $dbc->prepare("SELECT DATE(tdate), SUM(DISTINCT(trans_num))
            FROM {$dlog} t
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
		

		

		//inerval counters for while loop
		$intervals = $dates['intervals'];
		$historicals = $dates['historicals'];
		$intervalKey = 1;
		$intervalDate = $intervals[0];
		$nextDate = $historicals[$intervalKey];
		$cutOffDate = $dates['cutOffDate'];
		//$historicalDate = $historicals[0];
		$basketData = array($intervalDate => 0);
		$basketHistory = array($intervalDate =>0);
		$countData = array($intervalDate => 0);
		$historyData = array($intervalDate => 0);
		$count = 0;
		$chartLabels = array($intervalDate);

		
		while($historyRow = $dbc->fetchRow($historyR)) {
			$countRow = $dbc->fetchRow($countR);
			$currentDate = $historyRow[0];
			if ($currentDate == $nextDate) {
				if ($intervalKey == 3)
					$count = $countData[$intervalDate];
				$intervalDate = $intervals[$intervalKey];
				$intervalKey++;
				$nextDate = $historicals[$intervalKey];
			}

			if (!(array_key_exists($intervalDate, $historyData))) {
				$chartLabels[] = $intervalDate;
				$historyData = array_merge($historyData, array($intervalDate =>0));
				if ($intervalDate <= $cutOffDate) {
					$countData = array_merge($countData, array($intervalDate =>0));
					$basketData = array_merge($historyData, array($intervalDate =>0));
				} else {
					$countData = array_merge($countData, array($intervalDate => Null));
					$basketData = array_merge($basketData, array($intervalDate => Null));
				}
				$basketHistory = array_merge($basketHistory, array($intervalDate =>0));
			}

			if ($intervalDate <= $cutOffDate) {
				$countData[$intervalDate] += $countRow[1];
				//$basketData[$intervalDate] += $totals[$intervalDate]/$countRow[1];
			}
			$historyData[$intervalDate] += $historyRow[1];
			//$basketHistory[$intervalDate] += $histTotals[$intervalDate]/$historyRow[1];


		}

		foreach ($chartLabels as $key => $intervalDate) {
			$basketHistory[$intervalDate] = $histTotals[$intervalDate]/$historyData[$intervalDate];
			if($countData[$intervalDate] != Null) {
				$basketData[$intervalDate] = $totals[$intervalDate]/$countData[$intervalDate];
			}
		}
		
		$this->custChart = array(array_values($chartLabels),array_values($countData),array_values($historyData));
		$this->basketChart = array(array_values($chartLabels),array_values($basketData), array_values($basketHistory));
		
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

		$today = date('Y-m-d');

		for($i =0;$i<8;$i++) {
			$startDate->modify($delta);
			$startDateLastYear->modify($delta);
			$intervals[] = $startDate->format('Y-m-d');
			$historicals[] = $startDateLastYear->format('Y-m-d');
			if ($today > $startDate->format('Y-m-d')) {
				$startDate->modify('-1 day');
				$cutOffDate = $startDate->format('Y-m-d');
				$startDate->modify('+1 day');
			}
		}

		// asign array values
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
   			"historyEnd" => $historyEnd,
   			"cutOffDate" => $cutOffDate
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
