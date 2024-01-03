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
    protected $report_headers = array('month', 'members_good_standing', 'members_not_good_standing', 'members_total', 'reachable_good_standing',
                    'reachable_not_good_standing', 'reachable_total', 'unreachable_good_standing', 'unreachable_not_good_standing', 'unreachable_total');
    protected $required_fields = array('date1', 'date2');

    public $description = '[Item Purchases] lists each transaction containing a particular item';
    public $themed = true;
    public $report_set = 'Transaction Reports';

    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $date1 = $this->form->date1;
        $date2 = $this->form->date2;

        $startDate = new DateTime($date1);
        $endDate = new DateTime($date2);

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


        return $data;
    }

    
    
    function form_content()
    {
        ob_start();
?>
<form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div class="col-sm-5">
    <div class="form-group">
        <label>Start Date</label>
        <input type=text id=date1 name=date1 
            class="form-control date-field" required />
    </div>
    <div class="form-group">
        <label>End Date</label>
        <input type=text id=date2 name=date2 
            class="form-control date-field" required />
    </div>
    <p>
        <button type=submit class="btn btn-default btn-core">Submit</button>
        <button type=reset class="btn btn-default btn-reset">Start Over</button>
    </p>
</div>
<div class="col-sm-5">
    <?php echo FormLib::date_range_picker(); ?>
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

FannieDispatch::conditionalExec();

