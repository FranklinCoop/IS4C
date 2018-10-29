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

    protected $report_headers = array('Dept Name','OverShort','LastYear','Budget');
    protected $required_fields = array('date1','date2');

	function report_description_content() {
		return(array('<p>Budget vs sales report</p>'));
	}

	public function preprocess()
    {
        parent::preprocess();
        // custom: needs graphing JS/CSS
        if ($this->content_function == 'report_content' && $this->report_format == 'html') {
            $this->addScript('../../src/javascript/Chart.min.js');
            $this->addScript('../../src/javascript/CoreChart.js');
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

		//$startThisYear = DateTime::createFromFormat($d1,'Y-m-d');
		$startThisYear = DateTime::createFromFormat('Y-m-d' ,$d1);
		$startDay = $startThisYear->format('l');
		$startLastYear = DateTime::createFromFormat('Y-m-d' ,$d1);
		$startLastYear->modify('-1 year');
		$startLastYear->modify('next ' . $startDay);
		$endThisYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endDay = $endThisYear->format('l');
		$endLastYear = DateTime::createFromFormat('Y-m-d' ,$d2);
		$endLastYear->modify('-1 year');
		$endLastYear->modify('next ' . $endDay);

		$dlog = DTransactionsModel::selectDTrans($d1,$d2);

		$args = array($startThisYear->format('Y-m-d'), $endThisYear->format('Y-m-d'),$store);
		$budgetQ = $dbc->prepare("SELECT  MAX(s.super_name) as deptName, SUM(b.budget) as budget
			FROM gfm_approach.daily_dept_sales_budget b
			JOIN gfm_approach.sage_to_core_acct_maps m on b.sageAcctNo = m.sageAcctNo
			JOIN core_op.superdeptnames s on s.superID = m.superDeptNo
		WHERE b.budgetDate BETWEEN ? AND ? AND m.storeNo = ? GROUP BY b.sageAcctNo");
        $budgetR = $dbc->execute($budgetQ,$args);
        
		$report = $this->getFiscalYearBalnce($endThisYear, $dbc, $store);
        $i = 0;
    	while($row = $dbc->fetchRow($budgetR)){
        	$record = array();
        	//$record[] = $row[0];
        	$record[] = sprintf('%.2f',$row[1]);
        	$report[$i] = array_merge($report[$i],$record);
            $i++;
        }

        $args = array($startLastYear->format('Y-m-d'), $endLastYear->format('Y-m-d'),$store);
        $lastYearQ = $dbc->prepare('SELECT MAX(m.superDeptNo) as superDept ,SUM(s.creditAmt) as deptSales 
        	FROM gfm_approach.daily_sales_sage s
			JOIN gfm_approach.sage_to_core_acct_maps m on s.accountID = m.sageAcctNo
			where `date` between ? AND ? AND m.storeNo = ? group by s.accountID');
        $lastYearR = $dbc->execute($lastYearQ,$args);
       	
       	$i=0;
        while($row = $dbc->fetchRow($lastYearR)){
        	$record = array();
        	//$record[] = $row[0];
        	$record[] = sprintf('%.2f',$row[1]);
        	$report[$i] = array_merge($report[$i],$record);
            $i++;
        }

        $args = array($d1.' 00:00:00', $d2.' 23:59:59', $store);
        $salesQ = $dbc->prepare("SELECT s.superID, sum(t.total) 
			FROM core_trans.transarchive t
			JOIN core_op.superdepts s on t.department = s .dept_ID
			WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id = ?
			AND t.trans_type IN ('D', 'I')
			group by s.superID");
        $salesR = $dbc->execute($salesQ, $args);

        $i=0;
        while($row = $dbc->fetchRow($salesR)){
        	$record = array();
        	$record[] = $row[0];
        	$record[] = sprintf('%.2f',$row[1]);
        	$report[$i] = array_merge($report[$i],$record);
            $i++;
        }



		return $report;
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
            $default .= '<div class="row">
                <div class="col-sm-10"><canvas id="dailyCanvas"></canvas></div>
                </div><div class="row">
                <div class="col-sm-10"><canvas id="totalCanvas"></canvas></div>
                </div>';

            $this->addOnloadCommand('chartAll('.(count($this->report_headers)-1).')');
        }

        return $default;
    }

        public function javascriptContent()
    {
        if ($this->report_format != 'html') {
            return;
        }

        return <<<JAVASCRIPT
function chartAll(totalCol) {
    var xLabels = $('td.reportColumn0').toArray().map(x => x.innerHTML.trim());
    var totals = $('td.reportColumn' + totalCol).toArray().map(x => Number(x.innerHTML.trim()));
    var daily = [];
    var dailyLabels = [];
    for (var i=1; i<totalCol; i++) {
        dailyLabels.push($('th.reportColumn'+i).first().text().trim());
        var yData = $('td.reportColumn' + i).toArray().map(x => Number(x.innerHTML.trim()));
        daily.push(yData);
    }

    CoreChart.lineChart('dailyCanvas', xLabels, daily, dailyLabels);
    CoreChart.lineChart('totalCanvas', xLabels, [totals], ["Total"]);
}
JAVASCRIPT;
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
