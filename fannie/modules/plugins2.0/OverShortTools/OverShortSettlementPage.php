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

    public function preprocess()
    {
        $this->__routes[] = 'get<date>';
        $this->__routes[] = 'post<id><value>';
        return parent::preprocess();
    }

    public function get_date_handler()
    {
        //$date = FormLib::get('date');]
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        echo $this->getTable($dbc,$this->date);

        return false;

   }

    public function post_id_value_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $json = array('msg'=>'');

        $model = new DailySettlements($dbc);
        $model->id($this->id);
        foreach ($model->find() as $obj) {
            $obj->count($this->value);
            $obj->diff($obj->amt() - $this->value);
        }
        $saved = $model->save();

        if (!$saved) {
            $json['msg'] = 'Error saving membership status';
        }
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

        function saveValue(value,t_id){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'OverShortSettlementPage.php',
                cache: false,
                type: 'post',
                dataType: 'json',
                data: 'id='+t_id+'&value='+value
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function saveMemType(memType,t_id){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'OverShortSettlementPage.php',
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
            $.ajax({url:'OverShortSettlementPage.php',
                cache: false,
                type: 'get',
                data: 'newDate='+newDate,
                dataType: 'json'
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function redrawList() {
            var data = 'filter=' + encodeURIComponent(JSON.stringify(filters));
            $.ajax({
                url: 'OverShortSettlementPage.php',
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
                url: 'OverShortSettlementPage.php',
                type: 'get',
                data: data
            }).done(function(resp) {
                $('#displayarea').html(resp);
            });
        }
        function selectDay() {
            var data = 'date='+$('#date').val();
            $.ajax({
                url: 'OverShortSettlementPage.php',
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
        $table = $this->getTable($dbc,'');
        $this->addScript('../../src/javascript/tablesorter/jquery.tablesorter.min.js');
        $this->addCssFile('index.css');
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
        $dbc->selectDB($this->config->get('OP_DB'));
        $storePicker = FormLib::storePicker('store',false); 


        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info hidden-print">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline hidden-print">';
        $ret .= '<div class="form-row">';
        
        $ret .= '<input class="form-control date-field" type=text id=date name=arg />';
        $ret .= $storePicker['html'];

        $ret .= '<input type="hidden" name="_method" value="get" />';
        $ret .= '<button type="button" onclick="selectDay();" class="btn btn-default">Set</button>';
        $ret .= '</div></div>';
        $ret .= '</form>';

        $ret .= '<hr />';
        return $ret;
    }

    private function getTable($dbc, $date) {
        $store = 1;
        $ret = 'Pick a Day';
        $columnNames = array('1','2','3','4','5','6');
                $ret = '<form method="post">';
        $ret .= '<table class="table table-bordered">';
        $ret .= sprintf('<thead>
        <tr><th colspan="5"><label class="table-label">%s</label></th></tr></thead><thead>
        <tr>',$date);
        


        if($date != '') {
            $tableData = new FCCSettlementModule($dbc,'trans_archive.bigArchive',$date,$store);
            $model = $tableData->getTable($dbc,'trans_archive.bigArchive',$date,$store);
            $columnNames = $tableData->getColNames();
                    foreach ($columnNames as $name => $info) {
            $ret .= '<th>' . $info . '</th>';
        }
        $ret .= '</tr></thead>';
                    $ret .= '<tbody>';
        foreach ($model->find() as $obj) {
            $pk = null;
            $ret .= '<tr>';
            foreach ($model->getColumns() as $name => $info) {
                    $ret .= sprintf($tableData->getCellFormat($obj->lineNo(),$name),
                        $obj->$name(),$obj->id,$name,$obj->$name());
                
            }
            $ret .= '</tr>';
        }

        $ret .= '</tbody>';
        }




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