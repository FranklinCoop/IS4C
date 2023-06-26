<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op, Greenfield, MA

    This file is part of CORE-POS.

    IT CORE is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    IT CORE is distributed in the hope that it will be useful,
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

class SearchListImportPage extends \COREPOS\Fannie\API\FannieUploadPage 
{
    protected $title = "Fannie :: Product Tools";
    protected $header = "Import Searchable Items";

    protected $must_authenticate = true;
    protected $auth_classes = array('departments', 'admin');

    public $description = '[Department Import] load POS departments from a spreadsheet.';

    /*
        'upc' => array('type'=>'VARCHAR(13)', 'primary_key'=>true),
    'store_id' => array('type'=>'INT', 'primary_key'=>true),
    'searchable' => array('type'=>'TINYINT', 'default'=>1),
    */

    protected $preview_opts = array(
        'upc' => array(
            'name' => 'upc',
            'display_name' => 'UPC',
            'default' => 0,
            'required' => True
        ),
        'store_id' => array(
            'name' => 'store_id',
            'display_name' => 'Store #',
            'default' => 1,
            'required' => True
        ),
        'searchable' => array(
            'name' => 'searchable',
            'display_name' => 'Show In Search',
            'default' => 0,
            'required' => False
        )
    );

    private $stats = array('imported'=>0, 'errors'=>array());
    
    public function process_file($linedata, $indexes)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        // prepare statements
        $marP = $dbc->prepare("INSERT INTO deptMargin (dept_ID,margin) VALUES (?,?)");
        $scP = $dbc->prepare("INSERT INTO deptSalesCodes (dept_ID,salesCode) VALUES (?,?)");
        $model = new ProduceSearchListModel($dbc);

        foreach($linedata as $line) {
            // get info from file and member-type default settings
            // if applicable
            $upc = str_pad($line[$indexes['upc']], 13, "0", STR_PAD_LEFT);
            $store_id = $line[$indexes['store_id']];
            $searchable = $line[$indexes['searchable']];

            $model->reset();
            $model->upc($upc);
            $model->store_id($store_id);
            $imported = $model->save();

            if ($imported) {
                $this->stats['imported']++;
            } else {
                $this->stats['errors'][] = 'Error imported UPC:' . $dept_no .' Store ID:'.$store_id;
            }
        }

        return true;
    }
    
    function form_content()
    {
        return '<div class="well"><legend>Instructions</legend>
        Upload a CSV or XLS file containing upc, store_id, and 1 for search and zero to turn it off.
        <br />A preview helps you to choose and map spreadsheet fields to the database.
        <br />The uploaded file will be deleted after the load.
        </div><br />';
    }

    function results_content()
    {
        $ret = '
            <p>Import Complete</p>
            <div class="alert alert-success">' . $this->stats['imported'] . ' search list imported</div>';
        if ($this->stats['errors']) {
            $ret .= '<div class="alert alert-error"><ul>';
            foreach ($this->stats['errors'] as $error) {
                $ret .= '<li>' . $error . '</li>';
            }
            $ret .= '</ul></div>';
        }

        return $ret;
    }

    public function unitTest($phpunit)
    {
        $this->stats = array('imported'=>0, 'errors'=>array('foo'));
        $phpunit->assertNotEquals(0, strlen($this->results_content()));
        $data = array(99999, 1, 1);
        $indexes = array('upc'=>0, 'store_id'=>1, 'searchable'=>2);
        $phpunit->assertEquals(true, $this->process_file(array($data), $indexes));
    }
}

FannieDispatch::conditionalExec();

