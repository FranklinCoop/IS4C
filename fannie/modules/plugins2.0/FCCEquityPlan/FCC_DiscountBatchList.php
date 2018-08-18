<?php
/*******************************************************************************

    Copyright 2015 Whole Foods Community Co-op

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

class FCC_DiscountBatchList extends FannieRESTfulPage
{
    protected $header = 'Monthly Discount Changes';
    protected $title = 'Monthly Disount Changes';

    public $description = '[Ignored Barcodes] are barcodes that purposely should not scan
    at the lanes. It is used primarily to suppress unexpected accidental scans on produce
    stickers or packaging when items are intended to be entered by PLU.';

    public $themed = true;
    
    protected $model_name = 'FCC_MonthlyDiscountChangesModel';
    protected $date = '';
    protected $sortby = 'LastName';
    protected $columnNames = array('card_no'=>'Member No',
                         'LastName'=>'Last name',
                         'FirstName'=>'First name',
                         'oldMemType'=>'Status',
                         'newMemType'=>'New Status');

    public function preprocess()
    {
        if ($this->date == '') {
            $this->date = date('Y-m').'-01'; // date is year plus month day one.
        }
        
        $this->__routes[] = 'get<filter>';
        $this->__routes[] = 'post<id><newMemType>';

        return parent::preprocess();
    }

    public function put_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);
        $saved = false;
        $missing_pk = false;
        $memberID = FormLib::get('card_no');
        //$args = array($memberID[0],'=',False);
        $model->card_no($memberID,'=',False);
        $obj = $model->find();
        if (sizeof($obj)==0) {
            if(true) {
                $custQ = $dbc->prepare("SELECT CardNo, memType FROM custdata WHERE CardNo = ? AND personNum=1");
                $custR = $dbc->execute($custQ,array($memberID));
                if ($custR) {
                    $memType = $dbc->fetch_row($custR)['memType'];
                        $model = new $this->model_name($dbc);
                        $model->month(date('Y-m').'-01');
                        $model->card_no($memberID);
                        $model->oldMemType($memType);
                        $model->newMemType($memType);
                        $saved = $model->save();
                } else {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Error+Adding+DBCon');
                }   
            } 
        } else {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Error+Already+'.$obj[0]->card_no());
            }

        if ($saved) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Added+Entry');
        } else {
            header('Unknowen Error in FCC_DiscountBatchList.php put_handler()');
        }
        return false;

}
    public function get_filter_handler()
    {
        if (isset($$this->filter['date']) && $$this->filter['date'] != '') {
            
            $this->date = $this->filter['date'];
        }
        echo 'gaga';

        return false;
    }

    public function post_id_newMemType_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $json = array('msg'=>'');


        $mtModel = new FCC_MonthlyDiscountChangesModel($dbc);
        $saved=false;
        $mtModel->changeID($this->id,'=');
        $mtModel->month($this->date,'=');
        foreach ($mtModel->find() as $obj) {
            $obj->newMemType($this->newMemType);
            $saved = $obj->save();
        }
        if (!$saved) {
            $json['msg'] = 'Error saving membership status: *'.$this->id.'* *'.$this->newMemType.'*';
        }

        echo json_encode($json);

        return false;
    }

    public function delete_id_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);
        $model->changeID($this->id,'=');
        $model->month($this->date,'=');
        $deleteMsg ='';
        foreach ($model->find() as $obj) {
            if ($obj->changeID() == $this->id) {
                $deleteMsg ='?flash=Deleted+Entry+'.$obj->changeID();
                $obj->delete();
            }
            
        }
        if ($deleteMsg != '') {
            header('Location: ' . $_SERVER['PHP_SELF'] . $deleteMsg);
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Error+Deleting+Entry');
        }

        return false;
    }

    private function getMemTypes($dbc) {
        $memTypes = array();
        $memTypeValues = $dbc->query('SELECT memtype, memDesc FROM memtype');
        while($row = $dbc->fetch_row($memTypeValues)) {
            $memTypes[$row['memtype']] = $row['memDesc'];
        }

        return $memTypes;
    }

    private function arrayToOpts($arr, $selected=-999, $id_label=false)
    {
        $opts = '';
        foreach ($arr as $num => $name) {
            if ($id_label === true) {
                $name = $num . ' ' . $name;
            }
            $opts .= sprintf('<option %s value="%d">%s</option>',
                                ($num == $selected ? 'selected' : ''),
                                $num, $name);
        }

        return $opts;
    }

    private function getDateSelector($dbc) {
        $dates = $dbc->query('SELECT YEAR(`month`) AS Y,MONTH(`month`) AS M 
                                FROM FCC_MonthlyDiscountChanges 
                                GROUP BY M,Y ORDER BY Y,M');
        $selected = date('Yn');
        $opts ='';
        while($row = $dbc->fetch_row($dates)){
            if($row[0] != '') {
                $name = date('M Y',strtotime($row[0].'-'.$row[1].'-01')); 
                $value = $row[0].$row[1];
                $opts .= sprintf('<option %s value="%d">%s</option>',
                        ($value == $selected ? 'selected' : ''),
                            $value, $name);
            }
        }

        //sprintf('<td><select class="%s form-control input-sm">%s</select></td>',$obj->$name(),$memTypeOpts);

        $ret = sprintf('<select onchange="redrawList(this, this.value);" class="date form-control input-sm"
            id="filterDate">%s</select>',
                $opts);

        return $ret;
    }

    private function getSortSelector($dbc) {
        $viewModel = new FCC_MonthlyDiscountChangesViewModel($dbc);
        $opts = '';
        foreach ($viewModel->getColumns() as $name => $info) {
            if ($name != 'month' && $name != 'changeID')
            $opts .= sprintf('<option %s value="%s">%s</option>',
                        ($name == $this->sortby ? 'selected' : ''),
                            $name, $this->columnNames[$name]);
        }
        $ret = sprintf('<select class="date form-control input-sm">%s</select>',
                $opts);

        return $ret;
    }

    function javascript_content()
    {
        ob_start();
        ?>
        function saveMemType(memType,t_id){
            var elem = $(this);
            var orig = this.defaultValue;
            $.ajax({url:'FCC_DiscountBatchList.php',
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
            $.ajax({url:'FCC_DiscountBatchList.php',
                cache: false,
                type: 'post',
                data: 'newDate='+newDate,
                dataType: 'json'
            }).done(function(data){
                showBootstrapPopover(elem, orig, data.msg);
            });
        }

        function refilter() {
            filters.date = $('#filterDate').val();
            pageStart = '';
          redrawList();
    };

        function redrawList(filterDate) {
            data += '&filter=' +filterDate
            $.ajax({
                url: 'FCC_DiscountBatchList.php',
                type: 'get',
                data: data
            }).done(function(resp) {
                $('#displayarea').html(resp);
            });
        };

        <?php
        return ob_get_clean();
    }

    public function get_view()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        //$model = new $this->model_name($dbc);

        $memTypes = $this->getMemTypes($dbc);

        

        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline">';
        
        //add a member to the list.
        $ret .= sprintf('<div class="form-group">
                        <label class="control-label">Add Number</label>
                        <input type="text" class="form-control" name="card_no" required />
                        </div> '
                    );

        $ret .= '<input type="hidden" name="_method" value="put" />';
        $ret .= '<button type="submit" class="btn btn-default">Add Member</button>';
        
        //select Month
        $ret .= '<div class="form-group">';
        $ret .= '<label class="control-label">Select Month</label>';
        $ret .= $this->getDateSelector($dbc);
        $ret .='</div> ';
        //sort
        $ret .= '<div class="form-group">';
        $ret .= '<label class="control-label">Sort By</label>';
        $ret .= $this->getSortSelector($dbc);
        $ret .= '</select>';
        $ret .='</div> ';
        //group


        $ret .= '</div>';
        $ret .= '</form>';

        $ret .= '<hr />';


        $table = $this->getTable($dbc);
        
        //Main Editing Table;
        $ret .= sprintf('<div id="displayarea">
                    %s
                </div>',$table);
        // after table
        //removed this save button because they table saves automatically.
        //$ret .= '<p><button class="btn btn-default">Save Changes</button></p>';
        //$ret .= '</form>';

        $this->addOnloadCommand("\$('input:first').focus();\n");

        return $ret;
    }

    private function getTable() {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $memTypes = $this->getMemTypes($dbc);
        $ret = '<form method="post">';
        $ret .= '<table class="table table-striped table-bordered">';
        $ret .= '<thead><tr>';
        
        foreach ($this->columnNames as $name => $info) {
            $ret .= '<th>' . $info . '</th>';
        }
        $ret .= '</tr></thead>';
        $ret .= '<tbody>';
        
        $viewModel = new FCC_MonthlyDiscountChangesViewModel($dbc);
        $viewModel->month($this->date,'=');
        foreach ($viewModel->find($this->sortby) as $obj) {
            $ret .= '<tr>';
            $pk = '';
            foreach ($viewModel->getColumns() as $name => $info) {
                switch ($name) {
                    case 'oldMemType':
                        $ret .= sprintf('<td>%s
                                <input type="hidden" name="%s[]" value="%s"/>
                                </td>',
                                $memTypes[$obj->$name()], $obj->$name(), $obj->$name()
                        );     
                        break;
                    case 'newMemType':
                        $memTypeOpts = $this->arrayToOpts($memTypes, $obj->$name());
                        $ret .= sprintf('<td><select onchange="saveMemType.call(this, this.value, %d);" class="%s form-control input-sm">%s</select></td>',$pk,$obj->$name(),$memTypeOpts);
                        break;
                    case 'changeID':
                        $pk = $obj->$name();
                        break; // nothing;
                    case'month':
                        break;// donothing
                    default:
                        $ret .= sprintf('<td>%s
                                <input type="hidden" name="%s[]" value="%s" />
                                </td>',
                                $obj->$name(), $name, $obj->$name()
                        );        
                        break;
                }
            }
            $ret .= '<td>';
            if ($pk != false) {
                $ret .= '<a href="?_method=delete&id=' . $pk . '" class="btn btn-danger">'
                    . COREPOS\Fannie\API\lib\FannieUI::deleteIcon() . '</a>';
            }
            $ret .= '</td>';
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';

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

