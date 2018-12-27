<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

class TransactionsWithVoids extends FannieReportPage 
{

    protected $title = "Fannie : Cashier Transactions With Voids";
    protected $header = "Cashier Transactions With Voids Report";
    protected $required_fields = array('date1', 'date2');

    protected $report_headers = array('Date','Store', 'Cashier', 'Register', 'Trans No','Void Total', 'Void Count');

    public $description = '[Cashier Transactions With Voids] shows all transactions where an item has been voided.';
    public $themed = true;
    public $report_set = 'Cashiering';
    //protected $multi_report_mode = true;

    function fetch_report_data()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $date1 = $this->form->date1;
        $date2 = $this->form->date2;
        $emp_no = FormLib::get('emp_no', false);

        $dtrans = DTransactionsModel::selectDTrans($date1,$date2);

        //select only one cashier.
        $empStr ='';
        if ($emp_no) {
            $empStr .= ' AND d.emp_no ='.$emp_no;
        }

        $transQ = $dbc->prepare("SELECT DATE(`datetime`) as `date`,
            store_id,
            emp_no,
            register_no,
            trans_no,
            sum(total) as totalVoids,
            count(*) as voidCount
            FROM {$dtrans}
            WHERE trans_status = 'V' 
            AND `datetime` BETWEEN ? AND ? {$empStr}
            group by DATE(`datetime`), store_id, register_no, emp_no, trans_no");

        $args = array($date1 . ' 00:00:00', $date2 . ' 23:59:59');

        $transR = $dbc->execute($transQ, $args);
        $data = array();
        while ($row = $dbc->fetch_row($transR)) {
            $record = array(
                $row[0],
                $row[1],
            );
            $data[] = $record;
        }

        return $data;
    }

    public function calculate_footers($data)
    {
        if (count($data) == 0) {
            return array();
        }
        $sums = array();
        for ($i = 0; $i<count($data[0]); $i++) {
            $sums[$i] = 0.0;
        }
        $count = 0.0;
        foreach ($data as $row) {
            for ($i=1; $i<count($row); $i++) {
                $val = trim($row[$i], '$%');
                $sums[$i] += $val;
            }
            $count++;
        }

        $ret = array('Average');
        for ($i=1; $i<count($sums); $i++) {
            $ret[] = sprintf('%.2f', $sums[$i] / $count);
        }

        return $ret;
    }

    private function safeDivide($a, $b)
    {
        if ($b == 0) {
            return 0.0;
        } else {
            return ((float)$a) / ((float)$b);
        }
    }
    
    function form_content()
    {
        global $FANNIE_URL;
        ob_start();
?>
<form method = "get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div class="col-sm-4">
    <div class="form-group">
        <label>Cashier#
            <?php echo \COREPOS\Fannie\API\lib\FannieHelp::ToolTip('Leave blank to list all cashiers'); ?></label>
        <input type=text name=emp_no id=emp_no  class="form-control" />
    </div>
    <div class="form-group">
        <label>Date Start</label>
        <input type=text id=date1 name=date1 class="form-control date-field" required />
    </div>
    <div class="form-group">
        <label>End Start</label>
        <input type=text id=date2 name=date2 class="form-control date-field" required />
    </div>
    <div class="form-group">
        <input type="checkbox" name="excel" id="excel" value="xls" />
        <label for="excel">Excel</label>
    </div>
    <p>
        <button type=submit class="btn btn-default btn-submit">Submit</button>
        <button type=reset class="btn btn-default btn-reset">Start Over</button>
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
            This report displays information about one or many
            cashiers during the time period. The base unit of 
            measurement is rings. Each ring is one item passing
            through the scanner-scale. Refunds, voids, and cancels
            are shown as both totals and percentages of total
            rings. In this context, "void" means reversing a single
            line item in a transaction and "cancel" means abandoning
            an in-progress transaction completely.
            </p>
            <p>
            The last three columns, #Trans, Minutes, and Rings/Minute
            are only valid for the past 90 days or so. Calculating
            the time spent ringing items on the fly is not feasible so
            that data must be prebuilt. Minutes is measured from the
            first <strong>item</strong> entered into the transaction
            to the last <strong>item</strong> entered into the
            transaction. Time spent entering member numbers, dealing
            with tenders, or between transactions is not included. 
            </p>';
    }
}

FannieDispatch::conditionalExec();

