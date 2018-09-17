<?php
/*******************************************************************************

    Copyright 2018 Franklin Community co-op

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

class OverShortSettlementPage extends FannieRESTfulPage
{
    protected $header = 'Daily Settlements';
    protected $title = 'Daily Settlements';

    public $description = '[Ignored Barcodes] are barcodes that purposely should not scan
    at the lanes. It is used primarily to suppress unexpected accidental scans on produce
    stickers or packaging when items are intended to be entered by PLU.';

    public $themed = true;
    
    protected $model_name = 'DailySettlements';
    protected static $tableData;
    protected static $loadedDate = '';

    public function preprocess()
    {
        $this->__routes[] = 'get<date><store><pdf>';
        $this->__routes[] = 'post<date><store>';
        $this->__routes[] = 'post<id><value>';
        $this->__routes[] = 'post<id><total>';
        $this->__routes[] = 'post<id><notes>';
        return parent::preprocess();
    }

    public function post_date_store_handler()
    {
        //$date = FormLib::get('date');]
        if (FormLib::get('pdf')==='print') {
            echo '<html>we in here </html>';
        } else {
            GLOBAL $FANNIE_PLUGIN_SETTINGS;
            $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
            echo $this->getTable($dbc,$this->date,$this->store);
        }
        return false;

   }
    public function get_date_store_pdf_handler()
    {
        //$date = FormLib::get('date');]
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $date = FormLib::get_form_value('date');
        $store = 1;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $pdf = new SettlementReportPDF($dbc,$date,1);
        $pdf->drawPDF();

        echo '<html>we in here </html>';
     
        return false;

   }


    public function post_id_value_handler()
    {
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $json = FCCSettlementModule::updateCell($dbc,$this->value,$this->id);

        echo json_encode($json);
        return false;
    }

    public function post_id_total_handler()
    {
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $json = FCCSettlementModule::updateTotalCell($dbc,$this->total,$this->id);

        echo json_encode($json);
        return false;
    }

    public function post_id_notes_handler()
    {
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $json = array('msg'=>'');

        $model = new DailySettlementNotesModel($dbc);
        $model->id($this->id);
        $model->notes($this->notes);
        $saved = $model->save();

        if (!$saved)
            $json['msg'] = "didn't save bro.";

        echo json_encode($json);
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

        function saveNotes(value,t_id) {
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'OverShortSettlementPage.php',
                cache: false,
                type: 'post',
                dataType: 'json',
                data: 'id='+t_id+'&notes='+value
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function saveTotal(value,t_id) {
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'OverShortSettlementPage.php',
                cache: false,
                type: 'post',
                dataType: 'json',
                data: 'id='+t_id+'&total='+value
            }).done(function(data){
                var secID = data.secID;
                var grandID = data.grandTotalID;
                var diffID = data.diffID;
                var secTotal = data.secTotal;

                $("#diff"+t_id).attr("data-value",data.diff);
                $("#count"+t_id).attr("data-value",value);
                $("#diff"+t_id).empty().append(data.diff);

                $("#total"+secID).attr("data-value",data.secTotal);
                $("#diff"+secID).attr("data-value",data.secDiff);
                $("#diff"+secID).empty().append(data.secDiff);

                
                $("#total"+grandID).attr("data-value",data.grandTotal);
                $("#total"+grandID).empty().append(data.grandTotal);


                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function saveValue(value,t_id){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'OverShortSettlementPage.php',
                cache: false,
                type: 'post',
                dataType: 'json',
                data: 'id='+t_id+'&value='+value
            }).done(function(data){
                var secID = data.secID;
                var grandID = data.grandTotalID;
                var diffID = data.diffID;
                var secTotal = data.secTotal;

                $("#diff"+t_id).attr("data-value",data.diff);
                $("#count"+t_id).attr("data-value",value);
                $("#diff"+t_id).empty().append(data.diff);

                $("#total"+secID).attr("data-value",data.secTotal);
                $("#diff"+secID).attr("data-value",data.secDiff);
                $("#total"+diffID).attr("data-value",data.grandDiff);
                $("#total"+secID).empty().append(data.secTotal);
                $("#diff"+secID).empty().append(data.secDiff);
                $("#total"+diffID).empty().append(data.grandDiff);

                
                $("#total"+grandID).attr("data-value",data.grandTotal);
                $("#total"+grandID).empty().append(data.grandTotal);


                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function setdate()
        {
            var dataStr = $('#osForm').serialize();
            dataStr += '&action=date';
            $('#date').val('');
            $('#forms').html('');
            $('#loading-bar').show();
            $.ajax({
                url: 'OverShortSettlementPage.php',
                data: dataStr,
            success: function(data){
                $('#loading-bar').hide();
                $('#forms').html(data);
            }
            });
        }

        function selectDay() {
            var setdate = $('#date').val();
            var store = $('#storeID').val();
            var data = 'date='+setdate+'&store='+store;
            $.ajax({
                url: 'OverShortSettlementPage.php',
                type: 'post',
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
        $table = $this->getTable($dbc,'',1);

        ob_start();
        ?>
        <div id="inputarea">
            <?php echo $inputArea; ?>
        </div>
        <div id="loading-bar" class="collapse">
            <?php echo \COREPOS\Fannie\API\lib\FannieUI::loadingBar(); ?>
        </div>
        <div id="displayarea">
            <?php echo $table; ?>
        </div>

        <?php
        return ob_get_clean();

    }

    private function getInputHeader($dbc) {
        $dbc->selectDB($this->config->get('OP_DB'));
        $storePicker = FormLib::storePicker('store',false); 
        $storeSelect = str_replace('<select ', '<select id="storeID" ', $storePicker['html']);


        $ret = '';
        $ret = '<form style="margin-top:1.0em;"" id="osForm" >';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info hidden-print">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<div class="form-inline hidden-print">';
        $ret .=  '<label>Date</label>';
        $ret .= '<input class="form-control date-field" type=text id="date" name="date" />';
        $ret .=  '<label>Store</label>';
        $ret .= $storeSelect;
        
        $ret .= '<button type="button" onclick="selectDay();"  class="btn btn-default">Set</button>';
        $ret .= '<button type="submit" name="pdf" value="print" class="btn btn-default">Report</button>';
        $ret .= '</div></div>';

        $ret .= '<hr />';
        return $ret;
    }

    private function getTable($dbc, $date,$store) {
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $ret = 'Pick a Day';
        $columnNames = array('1','2','3','4','5','6');
        $ret = '<form method="post">';
        $ret .= '<table id="dataTable" class="table table-bordered">';
        $ret .= sprintf('<thead>
        <tr><th colspan="5"><label class="table-label">%s</label></th></tr></thead><thead>
        <tr>',$date);
        
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $dlog = DTransactionsModel::selectDTrans($date);

        if($date != '') {
            $tableData = new FCCSettlementModule($dbc,$dlog,$date,$store);
            $model = $tableData->getTable($dbc,$dlog,$date,$store);
            $ret .= $tableData->getColNames();
        
            $ret .= '</tr></thead>';
            $ret .= '<tbody>';
            foreach ($model->find() as $obj) {
                $objID = $obj->id();
                $totalID = $obj->totalRow();
                $ret .= '<tr>';
                foreach ($model->getColumns() as $name => $info) {
                    $value = $obj->$name();
                    $str = $tableData->getCellFormat($obj->lineNo(),$name);
                    $str = str_replace('{$value}', $value, $str);
                    $str = str_replace('{$totalID}', $totalID, $str);
                    $str = str_replace('{$objID}', $objID, $str);
                    $str = str_replace('{$name}', $name, $str);
                    $ret .= $str;
                    //name,ID, value, id,id, name, value
                }  
                $ret .= '</tr>';
            }
            $notesModel = new DailySettlementNotesModel($dbc);
            $notesModel->date($date,'=');
            $notesModel->storeID($store,'=');
            $notesRes = $notesModel->find();
            if($notesRes) {
                $notes = $notesRes[0];
                $note = $notes->notes();
                $id = $notes->id();
                $ret .= "<tr><td>Notes</td><td colspan=5</td>";
                $ret .= "<textarea class='form-control' onchange='saveNotes.call(this, this.value, $id);'' rows=5 cols=35 id='note'>$note</textarea></td></tr>";
            }

            $ret .= '</tbody>';
        }
        $ret .= '</form>';



        return $ret;

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