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

    public function put_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);
        $missing_pk = false;
        foreach ($model->getColumns() as $name => $info) {
            $val = FormLib::get($name);
            $model->$name($val);
            if (isset($info['primary_key']) && $info['primary_key'] && $val === '') {
                $missing_pk = true;
            }
        }
        if (!$missing_pk) {
            $saved = $model->save();
        }

        if (!$missing_pk && $saved) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Added+Entry');
        } else {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Error+Adding+Entry');
        }

        return false;
    }

    public function post_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);
        $columns = array_keys($model->getColumns());
        $col0 = $columns[0];
        $vals0 = FormLib::get($col0, array());
        for ($i=0; $i<count($vals0); $i++) {
            $model->$col0($vals0[$i]);
            for ($j=1; $j<count($columns); $j++) {
                $col = $columns[$j];
                $vals = FormLib::get($col);
                if (is_array($vals) && isset($vals[$i])) {
                    $model->$col($vals[$i]);
                }
            }
            $model->save();
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Saved+Changes');

        return false;
    }

    public function delete_id_handler()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);
        foreach ($model->getColumns() as $name => $info) {
            if (isset($info['primary_key']) && $info['primary_key']) {
                $model->$name($this->id);
                break;
            }
        }
        if ($model->delete()) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?flash=Deleted+Entry');
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

    public function get_view()
    {
        $dbc = $this->connection;
        $dbc->selectDB($this->config->get('OP_DB'));
        $model = new $this->model_name($dbc);

        $memTypes = $this->getMemTypes($dbc);

        $ret = '';
        if (FormLib::get('flash') !== '') {
            $ret .= '<div class="alert alert-info">' . FormLib::get('flash') . '</div>';
        }
        $ret .= '<form method="get">';
        $ret .= '<div class="form-inline">';
        foreach ($model->getColumns() as $name => $info) {
            switch ($name) {
                case 'changeID':
                    break; //do nothing.      
                default:
                    $ret .= sprintf('
                    <div class="form-group">
                        <label class="control-label">%s</label>
                        <input type="text" class="form-control" name="%s" %s />
                    </div> ',
                    ucwords($name),
                    $name,
                    (isset($info['primary_key']) && $info['primary_key']) ? 'required' : ''
                    );
                    break;
            }

        }
        $ret .= '<input type="hidden" name="_method" value="put" />';
        $ret .= '<button type="submit" class="btn btn-default">Add Entry</button>';
        $ret .= '</div>';
        $ret .= '</form>';

        $ret .= '<hr />';

        //Main Editing Table;

        $ret .= '<form method="post">';
        $ret .= '<table class="table table-striped table-bordered">';
        $ret .= '<thead><tr>';
        
        foreach ($model->getColumns() as $name => $info) {
            if($name != 'changeID') {
                $ret .= '<th>' . ucwords($name) . '</th>';
            }
        }
        $ret .= '</tr></thead>';
        $ret .= '<tbody>';
        foreach ($model->find() as $obj) {
            $ret .= '<tr>';
            $pk = false;
            foreach ($model->getColumns() as $name => $info) {
                $memTypeOpts = $this->arrayToOpts($memTypes, $obj->$name());
                switch ($name) {
                    case 'newMemType':
                        $ret .= sprintf('<td><select name="newMemType[]" class="newMemType form-control input-sm">%s</select></td>',$memTypeOpts);
                        break;
                    case 'changeID':
                        break; // do nothing;
                    default:
                        $ret .= sprintf('<td>%s
                                <input type="hidden" name="%s[]" value="%s" />
                                </td>',
                                $obj->$name(), $name, $obj->$name()
                        );        
                        if ($pk === false) {
                            $pk = $obj->$name();
                        } else {
                            $pk = false;
                        }
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
        // after table
        $ret .= '<p><button class="btn btn-default">Save Changes</button></p>';
        $ret .= '</form>';

        $this->addOnloadCommand("\$('input:first').focus();\n");

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

