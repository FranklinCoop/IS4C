<?php
/*******************************************************************************

    Copyright 2026 Franklin Coop

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

use COREPOS\Fannie\API\jobs\QueueManager;

include(dirname(__FILE__) . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

class NCGSignFileImportPage extends \COREPOS\Fannie\API\FannieUploadPage {
    protected $title = "Fannie -  NCG Sign Data";
    protected $header = "Upload NCG Sign Data";

    protected $auth_classes = array('batches');
    protected $must_authenticate = true;

    public $description = 'Import NCG Sign Data.';

    /*
        'SignSize' => array('type'=>'VARCHAR(30)'),
        'SignType' => array('type'=>'VARCHAR(30)'),
        'start_date' => array('type'=>'DATETIME','primary_key'=>True),
        'end_date' => array('type'=>'DATETIME','primary_key'=>True,
        'upc' => array('type'=>'VARCHAR(13)','primary_key'=>True)),
        'sms_upc' => array('type'=>'INT'),
        'brand' => array('type'=>'VARCHAR(30)'),
        'description' => array('type'=>'VARCHAR(50)'),
        'unitSize' => array('type'=>'VARCHAR(25)'),
        'unitOfMesure' => array('type'=>'VARCHAR(25)'),
        'posPrice' => array('type'=>'MONEY'),
        'signPrice' => array('type'=>'VARCHAR(25)'),
        'priceDevider' => array('type'=>'INT'),
        'multiPrice' => array('type'=>'INT'),
        'unitPrice' => array('type'=>'MONEY'),
        'attribute' => array('type' =>'VARCHAR(30)'),
    */
    protected $preview_opts = array(
        'SignSize' => array(
            'display_name' => 'Sign Size',
            'default' => 0,
            'required' => true
        ),
        'SignType' => array(
            'display_name' => 'Sign Type',
            'default' => 1,
            'required' => true
        ),
        'start_date' => array(
            'display_name' => 'Start Date',
            'default' => 2,
            'required' => false,
        ),
        'end_date' => array(
            'display_name' => 'End Date',
            'default' => 3,
            'required' => false,
        ),
        'upc' => array(
            'display_name' => 'UPC',
            'default' => 4,
            'required' => false,
        ),
        'sms_upc' => array(
            'display_name' => 'SMS UPC',
            'default' => 5,
        ),
        'brand' => array(
            'display_name' => 'Brand Name',
            'default' => 6,
        ),
        'description' => array(
            'display_name' => 'Product Name',
            'default' => 7,
        ),
        'unitSize' => array(
            'display_name' => 'Unit Size',
            'default' => 8,
        ),
        'unitOfMesure' => array(
            'display_name' => 'Unit Of Mesure',
            'default' => 9,
        ),
        'posPrice' => array(
            'display_name' => 'POS Price',
            'default' => 10,
        ),
        'signPrice' => array(
            'display_name' => 'Sign Price',
            'default' => 11,
        ),
        'priceDevider' => array(
            'display_name' => 'BOGO Multi',
            'default' => 12,
        ),
        'multiPrice' => array(
            'display_name' => 'BOGO Price',
            'default' => 13,
        ),        
        'unitPrice' => array(
            'display_name' => 'Unit PRice',
            'default' => 14,
        ),
        'attribute' => array(
            'display_name' => 'attribute',
            'default' => 15,
        )

    );

    private $results = '';

    private $stats = array('imported'=>0, 'errors'=>array(), 'updated'=>0);
    
    function process_file($linedata, $indexes)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $ret = '';
        $model = new NCGSignDataModel($dbc);
        $dbc->startTransaction();

        foreach ($linedata as $line) {

            $upc = $line[$indexes['upc']];
            if ($upc == 'Catapult UPC') {
                continue; //skip first line
            }

            
            $start_date = new DateTime($line[$indexes['start_date']] . ' 00:00:00');
            $end_date = new DateTime($line[$indexes['end_date']] . ' 23:59:59');

            $unitOfMesure = $line[$indexes['unitOfMesure']];
            $posPrice = $line[$indexes['posPrice']];
            $priceDevider = $line[$indexes['priceDevider']];
            $multiPrice = $line[$indexes['multiPrice']];
            $unitPrice = $line[$indexes['unitPrice']];


            $model->reset();
            $model->SignSize($line[$indexes['SignSize']]);
            $model->SignType($line[$indexes['SignType']]);
            $model->start_date($start_date->format('Y-m-d H:i:s'));
            $model->end_date($end_date->format('Y-m-d H:i:s'));            
            $model->upc($upc);
            $exists = $model->load();

            if (!$exists) {
                // fully init new record
                $model->sms_upc($line[$indexes['sms_upc']]);
                $model->brand($line[$indexes['brand']]);
                $model->description($line[$indexes['description']]);
                $model->unitSize($line[$indexes['unitSize']]);
                if ($unitOfMesure !='') $model->unitOfMesure($unitOfMesure);
                if ($posPrice !='') $model->posPrice($posPrice);
                $model->signPrice($line[$indexes['signPrice']]);
                if ($priceDevider !='') $model->priceDevider($priceDevider);
                if ($multiPrice !='') $model->multiPrice($multiPrice);
                if ($unitPrice !='') $model->unitPrice($unitPrice);
                $model->attribute($line[$indexes['attribute']]);
            }

            $try = $model->save();

            if ($try) {
                if (!$exists){
                    $this->stats['imported']++;
                } else {
                    $this->stats['updated']++;
                }   
            } else {
                $this->stats['errors'][] = 'Error importing UPC ' . $upc;
            }

                /*

        'SignSize'
        'SignType'
        'start_date'
        'end_date'
        'upc'
        'sms_upc'
        'brand'
        'description'
        'unitSize' 
        'unitOfMesure' 
        'posPrice' 
        'signPrice' 
        'priceDevider'
        'multiPrice' 
        'unitPrice' 
        'attribute' 
    */

        }
        $dbc->commitTransaction();
        return true;
    }

 

    function form_content()
    {
        ob_start();
        ?>
        <div class="well">
        Use this tool to import NCG sign Pilot Data.
        </div>
        <?php
        return ob_get_clean();
    }


    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->basicForm()));
        $phpunit->assertNotEquals(0, strlen($this->preview_content()));
        $this->results = 'foo';
        $phpunit->assertNotEquals(0, strlen($this->results_content()));
        $data = array('4011', 0.99);
        $indexes = array('upc_lc' => 0, 'price' => 1);
        $phpunit->assertEquals(true, $this->process_file(array($data), $indexes));
    }
}

FannieDispatch::conditionalExec();

