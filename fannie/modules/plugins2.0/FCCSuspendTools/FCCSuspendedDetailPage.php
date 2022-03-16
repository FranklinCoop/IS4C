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

class FCCSuspendedDetailPage extends FannieRESTfulPage
{
    protected $header = 'Suspended Transactions List';
    protected $title = 'Suspended Transactions List';

    public $description = '[FCC Suspened Transactions List] a list of old suspended trasactions.';

    public $themed = true;
    
    protected $date = '';
    protected $emp_no = '';
    protected $register_no = '';
    protected $trans_no = '';
    protected $total = '';
    protected $sales_tax = '';
    protected $meals_tax = '';
    protected $columnNames = array('upc'=>'upc',
                         'description'=>'description',
                         'ItemQtty'=>'ItemQtty',
                         'total'=>'total',
                        'tax'=>'tax');

    public function preprocess()
    {
        if ($this->date == '') {
            $this->date = date('Y-m').'-01'; // date is year plus month day one.
        }
        $this->date = FormLib::get('date');
        $this->emp_no = FormLib::get('empno');
        $this->register_no = FormLib::get('regno');
        $this->trans_no = FormLib::get('transno');
        $this->total = FormLib::get('total');
        $this->sales_tax = FormLib::get('salestax');
        $this->meals_tax = FormLib::get('mealstax');

        //post_tender_date_empno_regno_transno_newdate_handler
        //post_tender_date_empno_regno_transno_newdate_handler
        $this->__routes[] = 'post<tender><date><empno><regno><transno><total><salestax><mealstax><newdate>';
        $this->__routes[] = 'post<date><empno><regno><transno><total><salestax><mealstax>';
        $this->__routes[] = 'post<delete><date><empno><regno><transno><total><salestax><mealstax>';
        

        return parent::preprocess();
    }

    public function put_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=INSIDE+PUT+HANDLER');
        return false;

    }

    public function post_date_empno_regno_transno_total_salestax_mealstax_handler() {
        echo '<script>console.log("Your stuff here")</script>';
        $dbc = $this->connection;
        echo $this->getTransaction($dbc);
        return false;
    } 

    public function post_delete_date_empno_regno_transno_total_salestax_mealstax_handler() {
        $dbc = $this->connection;
        //get<delete><date><empno><regno><transno>
        $deleteMsg ='?flash=Old+Data+For+Review+Only+'.date('m');
        $doDelete = FormLib::get('delete');
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('TRANS_DB'));
        if ($doDelete) {
            $args = array(FormLib::get('date'),FormLib::get('empno'),FormLib::get('regno'),FormLib::get('transno'));
            $deleteQ = $dbc->prepare("DELETE
                                     FROM core_trans.suspended
                                     WHERE DATE(`datetime`) = ? AND emp_no =? AND register_no =? AND trans_no = ?"
                                    );
            $result = $this->connection->execute($deleteQ, $args);
            $deleteMsg = $result;
            
            $url = "FCCSuspendedListPage.php?flash=Transaction+{$this->date} {$this->emp_no}-{$this->register_no}-{$this->trans_no}+Deleted";
        
            $json = array('msg'=>$url, 'error' => 0);
            echo json_encode($json);
        } else {
            //$deleteMsg = '?flash=Delete+Canceled+'.FormLib::get('date');
            //header('Location: ' . $_SERVER['PHP_SELF'] . $deleteMsg);
            //echo $this->getTransaction($dbc);
            $json = array('msg'=>'delete cancled by user.','error'=>1);
            echo json_encode($json);
        }
        

        return false;
    }

    public function post_tender_date_empno_regno_transno_total_salestax_mealstax_newdate_handler() {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('TRANS_DB'));
        
        //total validation.
        if (!$this->lastTotalCheck($dbc)) {
            $json = array('msg'=>'Transaction not totaled','error'=>1);
            echo json_encode($json);
            return false;
        }

        //date validation.
        $new_date = $this->newdate;
        if ($new_date == '2009-00-00' || $new_date == 'undefined' || is_null($new_date)) {
            $new_date = date('Y-m-d');
        }
        $ret_date = $new_date;
        //find the new trans number
        $args = array($new_date);
        $arcTN = $this->getTransNo($dbc,'core_trans.transarchive', $args);
        $dayTN = $this->getTransNo($dbc,'core_trans.dtransactions', $args);
        $susTN = $this->getTransNo($dbc,'core_trans.suspended', $args);
        $newTransNo = max(1,$arcTN, $dayTN, $susTN);
        
        // check if we should do this and get common vairables for adds later   
        $sql = 'SELECT MAX(trans_id), SUM(total), MAX(percentDiscount), MAX(card_no), MAX(memType),MAX(staff),
                MAX(CASE WHEN `description`="PayPal" THEN 1 ELSE 0 END)
                FROM core_trans.suspended 
                WHERE DATE(`datetime`) = ? AND emp_no = ? AND register_no=? AND trans_no = ?
                GROUP BY DATE(`datetime`),emp_no,register_no,trans_no';
        $args = array($this->date,$this->empno,$this->regno,$this->transno);
        $prep = $dbc->prepare($sql);
        $result = $dbc->execute($prep, $args);
        $trans_id = 99999;
        $total = 0;
        $card_no = 99999;
        $percent_d = 0;
        $mem_type = 0;
        $staff = 0;
        $discount = 0;
        if ($row = $dbc->fetch_row($result)) {
            //termante function if it's already paid
            if($row[6]){
                $json = array('msg'=>'Transaction is already complate.','error'=>1);
                echo json_encode($json);
                return false;
            }

            $trans_id = $row[0];
            $trans_id++;
            $total = $this->total;
            $percent_d = $row[2];
            $card_no = $row[3];
            $mem_type = $row[4];
            $staff = $row[5];
            $discount = $row[1] * ($row[2]/100);
        } else {
            $json = array('msg'=>'Unable to locate common transaction vairables.'.$new_date,' - '.$this->newdate,'error'=>1);
            echo json_encode($json);
            return false;
        }


        //find store id.
        $store_id = 0;
        if ($this->regno > 20) {
            $store_id = 2;
        } else {
            $store_id = 1;
        }
        
        // update date, store and trans number for new tender.
        $args = array($new_date.' 09:00:00',$newTransNo, $store_id , $this->date,$this->empno,$this->regno,$this->transno);
        $updateDatesQ = 'UPDATE core_trans.suspended
                        SET `datetime` = ?, emp_no = 1001, register_no = 30, trans_no=?, store_id =?
                        WHERE DATE(`datetime`) = ?
                        AND emp_no = ? AND register_no = ? AND trans_no = ?';
        $prep = $this->connection->prepare($updateDatesQ);
        $result = $this->connection->execute($prep, $args);

        //add tender line first find trans line id

        $new_date .= ' 09:00:00';
        $args = array(
            'datetime' => $new_date, 
            'store_id' => $store_id,
            'trans_no' => $newTransNo,
            'total' => -$this->total,
            '$precent_d' => $percent_d,
            'memType' => $mem_type,
            'staff' => $staff,
            'card_no' => $card_no,
            'trans_id' => $trans_id
        );

        $insertSQL = 'INSERT INTO core_trans.suspended
                      VALUES(?, ?, 30, 1001, ?, 0, "PayPal", "T", "PY", "", 0, 0, 0, 0.00, 0.00, ?,
                      0.00, 0, 0, 0.00, 0.00, 0, 0, 0, ?, 0, 0, 0, 0.00, 0, 0, ?, ?, 0, "", ?, ?)';
        $prep = $this->connection->prepare($insertSQL);
        $result = $this->connection->execute($prep, $args);
        $trans_id++;
        // add change line it will be zero.
        $args = array(
            'datetime' => $new_date, 
            'store_id' => $store_id,
            'trans_no' => $newTransNo,
            '$precent_d' => $percent_d,
            'memType' => $mem_type,
            'staff' => $staff,
            'card_no' => $card_no,
            'trans_id' => $trans_id
        );
        $insertSQL = 'INSERT INTO core_trans.suspended
                      VALUES(?, ?, 30, 1001, ?, 0, "Change", "T", "CA", "", 0, 0, 0, 0.00, 0.00, 0.00,
                      0.00, 0, 0, 0.00, 0.00, 0, 0, 8, ?, 0, 0, 0, 0.00, 0, 0, ?, ?, 0, "", ?, ?)';
        $prep = $this->connection->prepare($insertSQL);
        $result = $this->connection->execute($prep, $args);
        $trans_id++;

        //add discount line
        $args = array(
            'datetime' => $new_date, 
            'store_id' => $store_id,
            'trans_no' => $newTransNo,
            'unit_price' => $discount,
            'total' => $discount,
            '$precent_d' => $percent_d,
            'memType' => $mem_type,
            'staff' => $staff,
            'card_no' => $card_no,
            'trans_id' => $trans_id
        );
        $insertSQL = 'INSERT INTO core_trans.suspended
                      VALUES(?, ?, 30, 1001, ?, "DISCOUNT", "Discount", "S", "", "", 0, 1, 0, 0.00, ?, ?,
                      0.00, 0, 0, 0.00, 0.00, 0, 0, 0, ?, 1, 0, 0, 0.00, 0, 0, ?, ?, 0, "", ?, ?)';
        $prep = $this->connection->prepare($insertSQL);
        $result = $this->connection->execute($prep, $args);
        $trans_id++;

        //add taxline
        $args = array(
            'datetime' => $new_date, 
            'store_id' => $store_id,
            'trans_no' => $newTransNo,
            'total' => $this->sales_tax+$this->meals_tax,
            '$precent_d' => $percent_d,
            'memType' => $mem_type,
            'staff' => $staff,
            'card_no' => $card_no,
            'trans_id' => $trans_id
        );
        $insertSQL = 'INSERT INTO core_trans.suspended
                      VALUES(?, ?, 30, 1001, ?, "TAX", "Tax", "A", "", "", 0, 0, 0, 0.00, 0.00, ?,
                      0.00, 0, 0, 0.00, 0.00, 0, 0, 0, ?, 0, 0, 0, 0.00, 0, 0, ?, ?, 0, "", ?, ?)';
        $prep = $this->connection->prepare($insertSQL);
        $result = $this->connection->execute($prep, $args);
        $trans_id++;

        //transfer to dtransactions.
        $args = array($new_date,$newTransNo, $store_id);
        $transferSQL = 'INSERT INTO core_trans.dtransactions
        (`datetime`, store_id, register_no, emp_no, trans_no, upc, `description`, trans_type, trans_subtype, trans_status, department, quantity, scale, cost, unitPrice, total, regPrice, tax, foodstamp, discount, memDiscount, discountable, discounttype, voided, percentDiscount, ItemQtty, volDiscType, volume, VolSpecial, mixMatch, `matched`, memType, staff, numflag, charflag, card_no, trans_id, pos_row_id)
                        SELECT *, 123 FROM core_trans.suspended
                        WHERE `datetime` = ? AND emp_no = 1001 AND register_no=30 AND trans_no = ? AND store_id = ?';
        $prep = $this->connection->prepare($transferSQL);
        $result = $this->connection->execute($prep, $args);

        $url = "FCCSuspendedDetailPage.php?date={$ret_date}&empno=1001&regno=30&transno={$newTransNo}&total={$this->total}&salestax={$this->sales_tax}&mealstax={$this->meals_tax}";
        
        $json = array('msg'=>$url, 'error' => 0);
        //echo '<script>console.log("'.json_encode($json).'")</script>';
        echo json_encode($json);
        //$tenderMsg ='?flash=PAYPAL+TENDER+COMPLATE';
        //header('Location: ' . $url . $tenderMsg);
        //echo $this->getTransaction($dbc);
        

        return false;
    }

    private function getTransNo($dbc, $db, $args) {
        $newTransNo = 0;
        $sql = "SELECT MAX(trans_no) FROM {$db}
                WHERE DATE(`datetime`) = ? AND emp_no = 1001 AND register_no=30";
        $prep = $dbc->prepare($sql);
        $result = $dbc->execute($prep, $args);
        $newTransNo = 1;
        if ($row = $dbc->fetch_row($result)) {
            $newTransNo = $row[0] + 1;
        } 

        return $newTransNo;
    }

    private function lastTotalCheck($dbc) {
        if($this->total == 0){
            // no need to run this on a zero total transaction.
            return false;
        }
        
        $dbc->selectDB($this->config->get('TRANS_DB'));
        $args = array($this->date, $this->emp_no, $this->register_no, $this->trans_no);
        $sql = "SELECT * FROM core_trans.suspended
                WHERE DATE(`datetime`) =? AND emp_no = ? AND register_no = ? AND trans_no = ?";
        $prep = $this->connection->prepare($sql);
        $result = $this->connection->execute($prep, $args);
        $lastTotal = False;
        $subTotal = 0;
        //touch each row and make sure there are no items entered after the subtotal.
        while($row = $dbc->fetch_row($result)){
            switch ($row['trans_type']) {
                case 'C':
                    if ($this->inStr($row['description'],'Subtotal')) {
                        // we are on a sub total line set status to true if this is the last subtotal line it should stay ture.
                        $lastTotal = True;
                    }
                    break;
                case 'D':
                case 'I':
                    //if we have seen a subtotal line the status will be true but this is a item entry line so we set it back to false.
                    if ($lastTotal) {
                        $lastTotal = False;
                    }        
                    break;
            }
        }
        return $lastTotal; // returns the status of the check.
    }

    function javascript_content()
    {
        ob_start();
        ?>
        function deleteTransaction(date, empno, regno, transno,total, salestax, mealstax) {
            console.log('DELETE');
            var del=0;
            if(confirm("This action can not be undone.\nAre you sure you want to delete this transaction?")) {
                del=1;
            }
            var data = 'delete='+del+'&date='+date+'&empno='+empno+'&regno='+regno+'&transno='+transno+'&total='+total+'&salestax='+salestax+'&mealstax='+mealstax;
            $.ajax({
                url: 'FCCSuspendedDetailPage.php',
                type: 'post',
                dataType: 'json',
                data: data
            }).done(function(data) {
                if(data.error == 0){
                    window.location = data.msg;
                } else {
                    alert("Error:"+data.msg);
                }
            });
        }
        
        function tenderPaypal(date, empno, regno, transno, total, salestax, mealstax) {
            console.log('Tender');
            var tender='PY';
            var newdate = $('#finalDate').val();
            if (!newdate) {
                newdate = getToday();
            }
            var data = 'tender='+tender+'&date='+date+'&empno='+empno+'&regno='+regno+'&transno='+transno+'&total='+total+'&salestax='+salestax+'&mealstax='+mealstax+'&newdate='+newdate;
            console.log(newdate);
            $.ajax({
                cache: false,
                url: 'FCCSuspendedDetailPage.php',
                type: 'post',
                dataType: 'json',
                data: data
            }).done(function(data) {
                if(data.error == 0){
                    window.location = data.msg;
                } else {
                    alert("Error:"+data.msg);
                }
                    
            });
        }

        function getToday() {
            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
            var yyyy = today.getFullYear();

            today = yyyy + '-' + mm + '-' + dd;
            return today;
        }

        <?php
        return ob_get_clean();
    }


    public function get_view()
    {
        $dbc = $this->connection;


        $inputArea = $this->getInputHeader($dbc);
        $table = $this->getTransaction($dbc);
        $this->addScript('../../../src/javascript/tablesorter/jquery.tablesorter.min.js');
        //$this->addCssFile('index.css');
        //$this->addOnloadCommand("\$('.tablesorter').tablesorter();");

        return <<<HTML
        <div id="inputarea">
            {$inputArea}
        </div>
        <div id="displayarea">
            {$table}
        </div>
        HTML;

    }

    private function get_deleted_view() {
        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info hidden-print">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline hidden-print">';
        

        $ret .= '<div class="form-row">';

        //FormLib::get('date'),FormLib::get('empno'),FormLib::get('regno'),FormLib::get('transno')
        $dt = $this->date;
        $en = $this->emp_no;
        $rn = $this->register_no;
        $tn = $this->trans_no;
        $ret .= "<a href='FCCSuspendedListPage.php'>Back to Suspend List</a> | ";

        $ret .= '<input type="hidden" name="_method" value="put" />';


        $ret .= '</div>';
        $ret .= '</div>';
        $ret .= '</form>';

        $ret .= '<hr />';
    }

    private function getInputHeader($dbc) {
                $dbc->selectDB($this->config->get('OP_DB'));
        //$model = new $this->model_name($dbc);     


        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info hidden-print">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline hidden-print">';
        

        $ret .= '<div class="form-row">';

        //FormLib::get('date'),FormLib::get('empno'),FormLib::get('regno'),FormLib::get('transno')
        $dt = $this->date;
        $en = $this->emp_no;
        $rn = $this->register_no;
        $tn = $this->trans_no;
        $tl = $this->total;
        $st = $this->sales_tax;
        $mt = $this->meals_tax;

        $ret .= "<a href='FCCSuspendedListPage.php'>Back to batch list</a> | ";
        $ret .= "<button type='button' onclick='deleteTransaction(\"{$dt}\",{$en},{$rn},{$tn},{$tl},{$st},{$mt});' class='btn btn-default'>Delete Transaction</button>";
        $ret .= '<input class="form-control date-field" placeholder="Date" type=text id=finalDate name="finalDate" />';
        //$ret .= '<input type="hidden" name="_method" value="put" />';
        $ret .= "<button type='button' onclick='tenderPaypal(\"{$dt}\",{$en},{$rn},{$tn},{$tl},{$st},{$mt});' class='btn btn-default'>Complate Paypal</button>";
        //$ret .= "<button type='button' onclick='tenderPaypal(\"{$dt}\",{$en},{$rn},{$tn});' class='btn btn-default'>Complate Paypal</button>";

        //$ret .= '<input type="hidden" name="_method" value="put" />';


        $ret .= '</div>';
        $ret .= '</div>';
        $ret .= '</form>';

        $ret .= '<hr />';

        return $ret;
    }

    private function getTransaction($dbc) {
        $dbc->selectDB($this->config->get('TRANS_DB'));
        //$memTypes = $this->getMemTypes($dbc);

        $args = array($this->date,$this->emp_no,$this->register_no,$this->trans_no);
        $header = $this->date."<br>".$this->emp_no."-".$this->register_no."-".$this->trans_no;
        $sql = 'SELECT *
                FROM core_trans.suspended
                WHERE DATE(`datetime`) = ? AND emp_no =? AND register_no =? AND trans_no = ?';
        $prep = $this->connection->prepare($sql);
        $result = $this->connection->execute($prep, $args);
        
        
        $ret = '<form method="post">';
        $ret .= '<table class="table-condensed table-striped table-bordered">';
        $ret .= "<thead>
        <tr><td colspan='6' align='center'><label class='table-label' align='center'>{$header}</label></td></tr></thead><thead>
        <tr>";
        
        foreach ($this->columnNames as $name => $info) {
            $ret .= '<th>' . $info . '</th>';
        }
        $ret .= '</tr></thead>';
        $ret .= '<tbody>';

        
        $subtotal_desc='';
        $subtotal_total='';
        $discount_desc='';
        $discount_total='';
        $display_subtotal=1;
        while($row = $dbc->fetch_row($result)){
            if ($row['trans_type']=='C' && $this->inStr($row['description'],'Subtotal')) {
                    $subtotal_desc = $row['description'];
                    $subtotal_total = $row['unitPrice'];
            } else if ($row['trans_type']=='C' && $this->inStr($row['description'],'Discount')){
                $discount_desc = $row['description'];
                $discount_total = $row['unitPrice'];
            } else { // display lines.
                // if we have tender disply the subtotal first.
                if ($row['description'] == 'PayPal') {
                    $ret .= $this->getSubtotalRow($discount_desc, $discount_total, $subtotal_desc, $subtotal_total);
                    $display_subtotal =0;
                }
                $ret .= $this->getTableRow($row);
            }
            
        } 
        //no tender disply the subtotal as the last line;
        if($display_subtotal) {
            $ret .= $this->getSubtotalRow($discount_desc, $discount_total, $subtotal_desc, $subtotal_total);
        }

        $ret .= '</tbody></table>';

        $ret .= '</form>';

        return $ret;
    }

    private function getTableRow($row) {
                $ret = '<tr>';
                $ret .= '<td>'.$row['upc'].'</td>';
                //list mem number if this is a number entery
                if ($row['upc']=='MEMENTRY')
                    $ret .= '<td>'.$row['numflag'].'</td>';
                else
                    $ret .= '<td>'.$row['description'].'</td>';
                $ret .= '<td>'.$row['ItemQtty'].'</td>';
                // total column is total unless it is the subtotal.
                if ($row['trans_type']=='C' && $row['unitPrice'] != 0) {
                    $ret .= '<td>'.$row['unitPrice'].'</td>';
                } elseif ($row['total']!=0) {
                    $ret .= '<td>'.$row['total'].'</td>';
                }  else {
                    $ret .= '<td>'.'</td>';
                }
                //tax/food stamp column
                $tax_fs_flag ='';
                switch ($row['tax']) {
                    case '1':
                        if($row['foodstamp']=='1')
                            $tax_fs_flag .= 'TF';
                        else
                            $tax_fs_flag .= 'F';
                        break;
                        case '1':
                        if($row['foodstamp']=='1')
                            $tax_fs_flag .= 'RF';
                        else
                            $tax_fs_flag .= 'F';
                        break;
                    default:
                        if($row['foodstamp']=='1')
                            $tax_fs_flag .= 'F';
                        break;
                }
                $ret .= '<td>'.$tax_fs_flag.'</td>';
                $ret .= '</tr>';
                return $ret;
    }

    private function getSubtotalRow($discount_desc, $discount_total, $subtotal_desc, $subtotal_total) {
        $ret = '';
                // add the last discount and subtotal.
        $ret .= '<tr>';
        $ret .= '<td></td>'; //upc is blank
        $ret .= '<td>'.$discount_desc.'</td>';
        $ret .= '<td></td>'; //qty is blank
        $ret .= '<td>'.$discount_total.'</td>';
        $ret .= '<td></td>'; //flag is blank
        $ret .= '</tr>';
        $ret .= '<tr>';
        $ret .= '<td></td>'; //upc is blank
        $ret .= '<td>'.$subtotal_desc.'</td>';
        $ret .= '<td></td>'; //qty is blank
        $ret .= '<td>'.$subtotal_total.'</td>';
        $ret .= '<td></td>'; //flag is blank
        $ret .= '</tr>';
        return $ret;
    }

    private function inStr($haystack, $needle) {
        if(function_exists('str_contains')) {
            return str_contains($haystack,$needle);
        } else {
            return empty($needle) || strpos($haystack, $needle) !== false;
        }
    }    


    public function helpContent()
    {
        return '<p>
            Transaction detail for suspened transactions, allows deletion from the backend or complation
            with PayPal on the selected invoice date.
            </p>';
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
    }
}

FannieDispatch::conditionalExec();

