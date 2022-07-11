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

        $lookupHistory = 'SELECT * FROM core_op.FCC_MemberReportArchive WHERE `month` BETWEEN ? AND ?';
        $lookupP = $dbc->prepare($lookupHistory);
        $lookupR = $dbc->execute($lookupP, 
            array($date1, $date2));

        $data = array();
        while ($row = $dbc->fetch_row($lookupR)) {
            
            $tempArray = array();
            $tempArray[] = $row[1];
            $tempArray[] = $row[2];
            $tempArray[] = $row[3];
            $tempArray[] = $row[4];
            $tempArray[] = $row[5];
            $tempArray[] = $row[6];
            $tempArray[] = $row[7];
            $tempArray[] = $row[8];
            $tempArray[] = $row[9];
            $tempArray[] = $row[10];
            $data[] = $tempArray;
        }

        $today = $this->get_current_data($dbc);

        $data[] = $today;

        return $data;
    }

private function get_current_data($dbc) {
            $data = array();
            $data[] = date('Y-m-d');
            //members in good standing
            $query = "SELECT count(*) AS gsMembers FROM core_op.custdata WHERE personNum =1 AND memType IN (1,3,5,6,8,9,10)";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
    
            //members not in good standing
            $query = "SELECT count(*) AS ngsMembers FROM core_op.custdata WHERE personNum =1 AND memType =12";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
    
            //total members
            $query = "SELECT count(*) AS totalMembers FROM core_op.custdata WHERE personNum =1 AND memType IN (1,3,5,6,8,9,10,12)";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //reachable in good stnading
            $query = "SELECT count(*) AS gsReachable FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10) AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //reachable not in good standing
            $query = "SELECT count(*) AS ngsReachable FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType = 12 AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //total reachable
            $query = "SELECT count(*) AS reachableTotal FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10,12) AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //unreachable in good standing
            $query = "SELECT count(*) AS gsUnreach FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10) AND ((i.street IN('','*','.','\n') OR i.street IS NULL))";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //unreachable not in good standing
            $query = "SELECT count(*) AS ngsUnreach FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType = 12 AND (i.street IN('','*','.','\n') OR i.street IS NULL)";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];
            //unreachable total
            $query = "SELECT count(*) AS totalUnreach FROM core_op.custdata c
                      LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                      WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10,12) AND (i.street IN('','*','.','\n') OR i.street is NULL)";
            $prep = $dbc->prepare($query);
            $results = $dbc->execute($prep,array());
            $row = $dbc->fetch_row($results);
            $data[] = $row[0];

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

