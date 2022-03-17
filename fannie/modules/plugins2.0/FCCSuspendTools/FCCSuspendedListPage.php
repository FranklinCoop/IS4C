<?php
/*******************************************************************************

    Copyright 2022 Franklin Community co-op

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

require(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

class FCCSuspendedListPage extends FannieRESTfulPage
{
    protected $header = 'Suspended Transactions List';
    protected $title = 'Suspended Transactions List';

    public $description = '[FCC Suspened Transactions List] a list of old suspended trasactions.';

    public $themed = true;
    
    protected $model_name = 'FCC_MonthlyDiscountChangesModel';
    protected $date = '';
    protected $sortby = 'datetime';
    protected $groupby = 'datetime';
    protected $columnNames = array('date'=>'date',
                         'register_no'=>'register_no',
                         'emp_no'=>'emp_no',
                         'trans_no'=>'trans_no',
                        'total'=>'total',
                        'paid'=>'paid');

    public function preprocess()
    {
        if ($this->date == '') {
            $this->date = date('Y-m').'-01'; // date is year plus month day one.
        }
        
        $this->__routes[] = 'get<filter>';
        $this->__routes[] = 'get<generate>';
        $this->__routes[] = 'get<update>';
        $this->__routes[] = 'post<id><newMemType>';

        return parent::preprocess();
    }


    public function edit_date_empno_regno_transno_handler()
    {//2018-05
        //http://localhost/CORE/fannie/modules/plugins2.0/FCCSuspendTools/FCCSuspendedListPage.php?_method=edit&date=2018-03-01&empno=317&regno=3&transno=28

        header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Test+TEst+TEST');

        return false;
    }

    function javascript_content()
    {
        ob_start();
        ?>
        var filters = {
            owner: "",
            store: "",
            name: "",
            date: "",
        };

        function saveMemType(memType,t_id){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'FCCSuspendedListPage.php',
                cache: false,
                type: 'post',
                data: 'id='+t_id+'&newMemType='+memType,
                dataType: 'json'
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }
        function setNewDate(newDate){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'FCCSuspendedListPage.php',
                cache: false,
                type: 'post',
                data: 'newDate='+newDate,
                dataType: 'json'
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function redrawList() {
            var data = 'filter=' + encodeURIComponent(JSON.stringify(filters));
            $.ajax({
                url: 'FCCSuspendedListPage.php',
                type: 'get',
                data: data
            }).done(function(resp) {
                $('#displayarea').html(resp);
            });
        };

        function refilter() {
            filters.date = $('#filterDate').val();
            filters.sort = $('#filterSort').val();
            filters.group = $('#filterGroup').val();
            pageStart = '';
            redrawList();
        };

        function regenList() {
            var generate=0;
            if(confirm("All changes will be lost.\nDo you want to regnerate member update list?")) {
                generate=1;
            }
            var data = 'generate='+generate;
            $.ajax({
                url: 'FCCSuspendedListPage.php',
                type: 'get',
                data: data
            }).done(function(resp) {
                $('#displayarea').html(resp);
            });
        }
        function updateCuastdata() {
            var shouldUpdate=0;
            if(confirm("Update can not be undone.")) {
                shouldUpdate=1;
            }
            var data = 'update='+shouldUpdate;
            $.ajax({
                url: 'FCCSuspendedListPage.php',
                type: 'get',
                data: data
            }).done(function(resp) {
                $('#displayarea').html(resp);
            });
        }

        <?php
        return ob_get_clean();
    }

    public function get_view()
    {
        $dbc = $this->connection;


        $inputArea = $this->getInputHeader($dbc);
        $table = $this->getTable($dbc);
        $this->addScript('../../../src/javascript/tablesorter/jquery.tablesorter.min.js');
        //$this->addCssFile('index.css');
        $this->addOnloadCommand("\$('.tablesorter').tablesorter();");

        return <<<HTML
        <div id="inputarea">
            {$inputArea}
        </div>
        <div id="displayarea">
            {$table}
        </div>

HTML;

    }

    private function getInputHeader($dbc) {
        $dbc->selectDB($this->config->get('TRANS_DB'));
        //$model = new $this->model_name($dbc);        

        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info hidden-print">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline hidden-print">';
        
        $ret .= '<div class="form-row">';



        $ret .= '<input type="hidden" name="_method" value="put" />';


        $ret .= '</div>';
        $ret .= '</div>';
        $ret .= '</form>';

        $ret .= '<hr />';

        return $ret;
    }

    private function getTable($dbc) {


        $dbc->selectDB($this->config->get('TRANS_DB'));
        //$memTypes = $this->getMemTypes($dbc);

        $args = array();
        $sql = 'SELECT DATE(`datetime`) as `date`, register_no, emp_no, trans_no, 
        MAX(CASE WHEN `description` like "Subtotal%" AND trans_type = "C" then unitPrice else 0 end) as total,
                        SUM(CASE WHEN tax = 1 THEN total*.0625 ELSE 0 END) as sales_tax,
                        SUM(CASE WHEN tax = 2 THEN total*.0700 ELSE 0 END) as meals_tax,
                        MAX(CASE WHEN `description` = "PayPal" THEN 1 ELSE 0 END) AS paid
                        FROM core_trans.suspended 
                        GROUP BY DATE(`datetime`), store_id, register_no, emp_no, trans_no
                        ORDER BY DATE(`datetime`)';
        $prep = $dbc->prepare($sql);
        $result = $dbc->execute($prep, $args);
        
        
        $ret = '<form method="post">';
        $ret .= '<table class="table-condensed table-striped table-bordered">';
        $ret .= '<thead>
        <tr><th colspan="5"><label class="table-label">%s</label></th></tr></thead><thead>
        <tr>';
        
        foreach ($this->columnNames as $name => $info) {
            $ret .= '<th>' . $info . '</th>';
        }
        $ret .= '</tr></thead>';
        $ret .= '<tbody>';

        $emp_no ='';
        $reg_no ='';
        $trans_no = '';
        $trans_date = '';
        $total = '';
        $sales_tax ='';
        $meals_tax='';
        while($row = $dbc->fetch_row($result)){
            $ret .= '<tr>';
            foreach ($this->columnNames as $name => $info) {
                switch ($name) {
                    case 'date':
                        $trans_date = $row[$info];
                        $ret .= '<td>'.$row[$info].'</td>';
                        break;
                    case 'emp_no':
                        $emp_no = $row[$info];
                        $ret .= '<td>'.$row[$info].'</td>';
                        break;
                    case 'register_no':
                        $reg_no = $row[$info];
                        $ret .= '<td>'.$row[$info].'</td>';
                        break;
                    case 'trans_no':
                        $trans_no = $row[$info];
                        $ret .= '<td>'.$row[$info].'</td>';
                        break;
                    case 'total':
                        $sales_tax = round($row['sales_tax'],2);
                        $meals_tax = round($row['meals_tax'],2);
                        $total = $row['total'];
                        $ret .= '<td>'.$total.'</td>';
                        break;
                    case 'paid':
                        $ret .= '<td>'.$row[$info].'</td>';
                    default:
                        // do nothing.
                        break;
                }
            }
            $varString = '?date='.$trans_date.'&empno='.$emp_no.'&regno='.$reg_no.'&transno='.$trans_no.'&total='.$total.'&salestax='.$sales_tax.'&mealstax='.$meals_tax;
            $ret .= '<td><a href="FCCSuspendedDetailPage.php'.$varString.'" >'
            . COREPOS\Fannie\API\lib\FannieUI::editIcon() . '</a></td>';
            $ret .= '</tr>';   
        }


        $ret .= '</tbody></table>';

    

        return $ret;
    }

    private function getTableRow() {

    }



    public function helpContent()
    {
        return '<p>
            Ignored Barcodes are barcodes that purposely should not scan
            at the lanes. It is used primarily to suppress unexpected accidental scans on produce
            stickers or packaging when items are intended to be entered by PLU.
            </p>';
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
    }
}

FannieDispatch::conditionalExec();

