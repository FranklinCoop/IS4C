<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

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

class FCCBatchPage extends \COREPOS\Fannie\API\FannieUploadPage {
    protected $title = "Fannie -  Sales Batch";
    protected $header = "Upload Batch file";

    protected $auth_classes = array('batches');
    protected $must_authenticate = true;

    public $description = '[Excel Batch] creates a sale or price change batch from a spreadsheet.';

    protected $preview_opts = array(
        'upc_lc' => array(
            'display_name' => 'UPC/LC',
            'default' => 0,
            'required' => true
        ),
        'price' => array(
            'display_name' => 'Price',
            'default' => 2,
            'required' => true
        ),
        'cost' => array(
            'display_name' => 'Cost',
            'default' => 3,
            'required' => false,
        ),
        'vendor' => array(
            'display_name' => 'Vendor',
            'default' => 6,
            'required' => false,
        ),
        'name' => array(
            'display_name' => 'Name',
            'default' => 1,
            'required' => false,
        ),'dept' => array(
            'display_name' => 'Department #',
            'default' => 4,
        ),
        'brand' => array(
            'display_name' => 'Brand Name',
            'default' => 5,
        ),
        'local' => array(
            'display_name' => 'Local',
            'default' => 7,
        ),
        'organic' => array(
            'display_name' => 'Organic',
            'default' => 8,
        ),
        'nongmo' => array(
            'display_name' => 'NON-GMO',
            'default' => 9,
        ),
        'glutenfree' => array(
            'display_name' => 'Gluten Free',
            'default' => 10,
        ),
        'vegan' => array(
            'display_name' => 'Vegan',
            'default' => 11,
        ),
            'bipoc' => array(
            'display_name' => 'BIPOC',
            'default' => 12,
        ),
        'women_owned' => array(
            'display_name' => 'Women Owned',
            'default' => 13,
        ),        
        'lgbtq' => array(
            'display_name' => 'LGBTQ',
            'default' => 14,
        ),
        'traitor' => array(
            'display_name' => 'Traitor Brand',
            'default' => 15,
        ),
        'coopbasic' => array(
            'display_name' => 'Coop Basics',
            'default' => 16,
        ),
        'pack_size' => array(
            'display_name' => 'pack_size',
            'default' => 17,
        ),
        'unitOfMesure' => array(
            'display_name' => 'Tag Format',
            'default' => 18,
        ),
        'sku' => array(
            'display_name' => 'Vendor SKU',
            'default' => 19,
        )

    );

    private $results = '';

    private function get_batch_types(){
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $batchtypes = array();
        $typesQ = $dbc->prepare("select batchTypeID,typeDesc from batchType order by batchTypeID");
        $typesR = $dbc->execute($typesQ);
        while ($typesW = $dbc->fetchRow($typesR))
            $batchtypes[$typesW[0]] = $typesW[1];
        return $batchtypes;
    }

    private function createBatch($dbc)
    {
        $btype = FormLib::get('btype',0);
        $date1 = FormLib::get('date1',date('Y-m-d'));
        $date2 = FormLib::get('date2',date('Y-m-d'));
        $bname = FormLib::get('bname','');
        $owner = FormLib::get('bowner','');

        if ($date2 == '') {
            $date2 = NULL;
        }

        $dtQ = $dbc->prepare("SELECT discType FROM batchType WHERE batchTypeID=?");
        $discountType = $dbc->getValue($dtQ, array($btype));
        if ($discountType === false || !is_numeric($discountType)) {
            $discountType = 0;
        }

        $insQ = $dbc->prepare("
            INSERT INTO batches 
            (startDate,endDate,batchName,batchType,discounttype,priority,owner)
            VALUES 
            (?,?,?,?,?,0,?)");
        $args = array($date1,$date2,$bname,$btype,$discountType,$owner);
        $insR = $dbc->execute($insQ,$args);
        $batchID = $dbc->insertID();
        $bu = new BatchUpdateModel($dbc);
        $bu->batchID($batchID);
        $bu->logUpdate($bu::UPDATE_CREATE);

        return $batchID;
    }

    function process_file($linedata, $indexes)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $ftype = FormLib::get('ftype','UPCs');
        $has_checks = FormLib::get('has_checks') !== '' ? True : False;
        //used later for tag batches if they don't have needed
        $btype = FormLib::get('btype',0);

        //update or adds products as needed
        if ($ftype=='UPCs') {
            $this->updateProducts($linedata, $indexes);
        }

        $batchID = $this->createBatch($dbc);
        if ($this->config->get('STORE_MODE') === 'HQ') {
            StoreBatchMapModel::initBatch($batchID);
        }

        $upcChk = $dbc->prepare("SELECT upc FROM products WHERE upc=?");

        $insP = $dbc->prepare("INSERT INTO batchList 
            (batchID, pricemethod, quantity, active, upc, salePrice, groupSalePrice, signMultiplier)
            VALUES
            (?, 0, 0, 0, ?, ?, ?, ?)");
        $batchList = $dbc->tableDefinition('batchList');
        $saveCost = false;
        if (isset($batchList['cost'])) {
            $insP = $dbc->prepare("INSERT INTO batchList 
                (batchID, pricemethod, quantity, active, upc, salePrice, groupSalePrice, cost, signMultiplier)
                VALUES
                (?, 0, 0, 0, ?, ?, ?, ?, ?)");
            $saveCost = true;
        }

        $lc1P = $dbc->prepare("UPDATE likeCodes SET signOrigin=? WHERE likeCode=?");
        $lc2P = $dbc->prepare("UPDATE likeCodes SET origin=? WHERE likeCode=?");

        $queue = new QueueManager();

        $ret = '';
        $dbc->startTransaction();
        foreach ($linedata as $line) {
            if (!isset($line[$indexes['upc_lc']])) continue;
            if (!isset($line[$indexes['price']])) continue;
            $upc = $line[$indexes['upc_lc']];
            $price = $line[$indexes['price']];
            $upc = str_replace(" ","",$upc);    
            $upc = str_replace("-","",$upc);    
            $price = trim($price,' ');
            $price = trim($price,'$');
            $mult = 1;
            if (!is_numeric($upc)) {
                $ret .= "<i>Omitting item. Identifier {$upc} isn't a number</i><br />";
                continue; 
            } elseif(!is_numeric($price)){
                $ret .= "<i>Omitting item. Price {$price} isn't a number</i><br />";
                continue;
            }

            $cost = 0;
            if ($indexes['cost'] && isset($line[$indexes['cost']])) {
                $tmp = trim($line[$indexes['cost']]);
                $tmp = trim($tmp, '$');
                if (is_numeric($tmp)) {
                    $cost = $tmp;
                }
            }

            $upc = ($ftype=='UPCs') ? BarcodeLib::padUPC($upc) : 'LC'.$upc;
            if ($has_checks && $ftype=='UPCs')
                $upc = '0'.substr($upc,0,12);

            if ($ftype == 'UPCs'){
                $chkR = $dbc->execute($upcChk, array($upc));
                if ($dbc->num_rows($chkR) ==  0) continue;
            }

            if (isset($line[5]) && trim($line[5]) == 's') {
                $mult = 0;
            }

            //for tag batches
            if ($btype == 6) {
                $prodModel = new ProductsModel($dbc);
                $prodModel->reset();
                $prodModel->upc($upc);
                $prodModel->store_id(1);
                $exists = $prodModel->load();

                if (($price == 0 || $price =='')&& $exists) {
                    $price = $prodModel->price();
                }
                if (($cost == 0 || $cost =='')&& $exists) {
                    $cost = $prodModel->cost();
                }
            }

            $insArgs = array($batchID, $upc, $price, $price);
            if ($saveCost) {
                $insArgs[] = $cost;
            }
            $insArgs[] = $mult;
            $dbc->execute($insP, $insArgs);
            /** Worried about speed here. Log many?
            $bu = new BatchUpdateModel($dbc);
            $bu->batchID($batchID);
            $bu->upc($upc);
            $bu->logUpdate($bu::UPDATE_ADDED);
             */

            if ($this->config->COOP_ID == 'WFC_Duluth' && substr($upc, 0, 2) == 'LC' && $indexes['vendor'] && $indexes['name']) {
                $vendor = isset($line[$indexes['vendor']]) ? trim($line[$indexes['vendor']]) : '';
                $name = isset($line[$indexes['name']]) ? trim($line[$indexes['name']]) : '';
                if ($vendor != '' && $name != '') {
                    $this->queueUpdate($queue, $upc, $vendor, $name);
                }
            }
            if ($this->config->COOP_ID == 'WFC_Duluth' && substr($upc, 0, 2) == 'LC' && isset($line[6]) && isset($line[7])) {
                $signOrigin = strtoupper(trim($line[7])) == 'Y' ? 1 : 0;
                $origin = strtoupper(trim($line[6]));
                $like = substr($upc, 2);
                $dbc->execute($lc1P, array($signOrigin, $like));
                if ($origin) {
                    $dbc->execute($lc2P, array($origin, $like));
                }
            }
        }
        $dbc->commitTransaction();

        $ret .= '
        <p>
            Batch created
            <a href="' . $this->config->URL . 'batches/newbatch/EditBatchPage.php?id=' . $batchID 
                . '" class="btn btn-default">View Batch</a>
        </p>';
        $this->results = $ret;

        return true;
    }

    //functions for importing products.    
    private $stats = array('imported'=>0, 'errors'=>array(), 'updated'=>0);

    private function deptDefaults($dbc)
    {
        $defaults_table = array();
        $defQ = $dbc->prepare("SELECT dept_no,dept_tax,dept_fs,dept_discount FROM departments");
        $defR = $dbc->execute($defQ);
        while($defW = $dbc->fetch_row($defR)){
            $defaults_table[$defW['dept_no']] = array(
                'tax' => $defW['dept_tax'],
                'fs' => $defW['dept_fs'],
                'discount' => $defW['dept_discount']
            );
        }

        return $defaults_table;
    }

    private function getDefaultableSettings($dept, $defaults_table)
    {
        $tax = 0;
        $fstamp = 0;
        $discount = 1;
        if ($dept && isset($defaults_table[$dept])) {
            if (isset($defaults_table[$dept]['tax']))
                $tax = $defaults_table[$dept]['tax'];
            if (isset($defaults_table[$dept]['discount']))
                $discount = $defaults_table[$dept]['discount'];
            if (isset($defaults_table[$dept]['fs']))
                $fstamp = $defaults_table[$dept]['fs'];
        }

        return array($tax, $fstamp, $discount);
    }

    public function updateProducts($linedata, $indexes)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $defaults_table = $this->deptDefaults($dbc);

        $stores = new StoresModel($dbc);
        $stores->hasOwnItems(1);
        $stores = $stores->find();

        $ret = true;
        $linecount = 0;
        $checks = FormLib::get('has_checks') !== '' ? True : False;
        //$skipExisting = FormLib::get('skipExisting', 0);
        $model = new ProductsModel($dbc);
        $vendModel = new VendorItemsModel($dbc);
        $dbc->startTransaction();
        foreach($linedata as $line) {
            // get info from file and member-type default settings
            // if applicable
            $upc = $line[$indexes['upc_lc']];
            $desc = $line[$indexes['name']];
            $price =  $line[$indexes['price']];  
            $price = str_replace('$', '', $price);
            $price = trim($price);

            $cost =  $line[$indexes['cost']];  
            $cost = str_replace('$', '', $cost);
            $cost = trim($cost);
            $dept = ($indexes['dept'] !== false) ? $line[$indexes['dept']] : 0;
            list($tax, $fstamp, $discount) = $this->getDefaultableSettings($dept, $defaults_table);
            $brand = $line[$indexes['brand']];
            $vendor = $line[$indexes['vendor']];
            $pack_size = $line[$indexes['pack_size']];
            $unitOfMesure = $line[$indexes['unitOfMesure']];
            $sku = $line[$indexes['sku']];

            //item flags 1 or 0 multiplied by only if flags are present
            $numflag = '';
            if (
                $line[$indexes['local']] != '' ||
                $line[$indexes['organic']] !='' ||
                $line[$indexes['coopbasic']] !='' ||
                $line[$indexes['nongmo']] !='' ||
                $line[$indexes['glutenfree']] !='' ||
                $line[$indexes['traitor']] !='' ||
                $line[$indexes['vegan']] !='' ||
                $line[$indexes['bipoc']] !='' ||
                $line[$indexes['women_owned']] !='' ||
                $line[$indexes['lgbtq']]
            ) {
                    $flags = array($line[$indexes['local']]*1,
                    $line[$indexes['organic']]*2,
                    $line[$indexes['coopbasic']]*3,
                    $line[$indexes['nongmo']]*4,
                    $line[$indexes['bipoc']]*5,
                    $line[$indexes['glutenfree']]*6,
                    $line[$indexes['women_owned']]*7,
                    $line[$indexes['traitor']]*8,
                    $line[$indexes['lgbtq']]*9,
                    $line[$indexes['vegan']]*10,);
                    $numflag = $this->proc_flags($upc, '', $flags);
                } 




            // upc cleanup
            $upc = str_replace(" ","",$upc);
            $upc = str_replace("-","",$upc);
            if (!is_numeric($upc)) continue; // skip header(s) or blank rows

            if ($checks) {
                $upc = substr($upc,0,strlen($upc)-1);
            }
            if (!is_numeric($upc)) {
                $ret .= "<i>Omitting item. Identifier {$upc} isn't a number</i><br />";
                $this->stats['errors'][] = 'UPC is non-numeric ' . $upc;
                continue; 
            } elseif(!is_numeric($price)){
                $ret .= "<i>Omitting item. Price {$price} isn't a number</i><br />";
                $this->stats['errors'][] = 'Item Price is non-numeric ' . $upc;
                continue;
            }


            $upc = BarcodeLib::padUPC($upc);

            if (strlen($desc) > 35) $desc = substr($desc,0,35);     
            //update product model.
            $model->reset();
            $model->upc($upc);
            $model->store_id(1);
            $exists = $model->load();
            if ($upc == 'upc') {
                continue; //skip first line
            }
            if ($desc !='') $model->description($desc);
            //$model->normal_price($price);
            if ($dept !='') $model->department($dept);
            if ($tax !='') $model->tax($tax);
            if ($fstamp !='') $model->foodstamp($fstamp);
            if ($discount !='') $model->discount($discount);
            if ($cost !='') $model->cost($cost);
            if ($brand !='') $model->brand($brand);
            if ($numflag !='') $model->numflag($numflag);
            //takes the currently set vendor if there is none to use for vendor cost updates.
            if ($vendor !='' && $vendor!='#N/A') {
                $model->default_vendor_id($vendor);
            } else {
                $vendor = $model->default_vendor_id();
            }
            if ($pack_size !='') $model->size($pack_size);
            if ($unitOfMesure !='') $model->unitofmeasure($unitOfMesure);
            if (!$exists) {
                // fully init new record
                $model->normal_price($price);
                $model->pricemethod(0);
                $model->special_price(0);
                $model->specialpricemethod(0);
                $model->specialquantity(0);
                $model->specialgroupprice(0);
                $model->advertised(0);
                $model->tareweight(0);
                $model->start_date('1900-01-01');
                $model->end_date('1900-01-01');
                $model->discounttype(0);
                $model->wicable(0);
                $model->inUse(1);
                $model->created(date('Y-m-d H:i:s'));
            }
            $try = $model->save();

            foreach ($stores as $s) {
                if ($s->storeID() != 1) {
                    $model->store_id($s->storeID());
                    $model->save();
                }
            }

            //update catalog only if vendor number is present.
            if($vendor != '') {
                $vendModel->reset();
                $vendModel->vendorID($vendor);
                $vendModel->upc($upc);
                $vendExist = $vendModel->load();
                if($sku != '') $vendModel->sku($sku);
                if ($cost !='') $vendModel->cost($cost);
                if ($brand !='') $vendModel->brand($brand);
                if ($pack_size !='') $vendModel->size($pack_size);
                if ($dept !='') $vendModel->vendorDept($dept);
                if ($desc !='') $vendModel->description($desc);
                $vendModel->save();
            }



            

            if ($try) {
                if (!$exists){
                    $this->stats['imported']++;
                } else {
                    $this->stats['updated']++;
                }   
            } else {
                $this->stats['errors'][] = 'Error importing UPC ' . $upc;
            }
        }
        $dbc->commitTransaction();

        return $ret;
    }

    function proc_flags($upc, $store, $flags) {
        $dbc = $this->connection;
        $attrs = array(1,2,3,4,5,6,7,8,9,10);
        $fnames = array('Local','Organic','Coop Basic','Non_GMO','bipoc','Gluten Free','Woman Owned','Traitor Brand','LGBTQ','Vegan');
        $bits = array(1,2,3,4,5,6,7,8,9,10);
        /**
          Collect known flags and initialize
          JSON object with all flags false
        */
        $json = array();
        $bitStatus = array();
        $flagMap = array();
        for ($i=0; $i<count($attrs); $i++) {
            $json[$attrs[$i]] = false;
            $flagMap[$bits[$i]] = $attrs[$i];
            $bitStatus[$bits[$i]] = false;
        }



        //flags needs to hold bit numbers.
        $numflag = 0;   
        foreach ($flags as $f) {
            if ($f != (int)$f || $f == 0) {
                continue;
            }
            $numflag = $numflag | (1 << ($f-1));

            // set flag in JSON representation
            $attr = $flagMap[$f];
            $json[$attr] = true;
            $bitStatus[$f] = true;
        }

        return $numflag;
    }

    private function queueUpdate($queue, $upc, $vendor, $name)
    {
        $vID = -1;
        if ($vendor == 'ALBERTS') {
            $vID = 28;
        } elseif ($vendor == 'CPW') {
            $vID = 25;
        } elseif ($vendor == 'RDW') {
            $vID = 136;
        } elseif ($vendor == 'UNFI') {
            $vID = 1;
        } 

        $job = array(
            'class' => 'COREPOS\\Fannie\\API\\jobs\\SqlUpdate',
            'data' => array(
                'table' => 'likeCodes',
                'set' => array(
                    'likeCodeDesc' => $name,
                    'preferredVendorID' => $vID,
                ),
                'where' => array(
                    'likeCode' => substr($upc, 2),
                ),
            ),
        );
        $queue->add($job);
    }

    function results_content()
    {

        $ret = '
            <p>Import Complete</p>
            <div class="alert alert-success">' . $this->stats['imported'] . ' records imported. - '.$this->stats['updated'].' records updated.</div>';
        if ($this->stats['errors']) {
            $ret .= '<div class="alert alert-error"><ul>';
            foreach ($stats['errors'] as $error) {
                $ret .= '<li>' . $error . '</li>';
            }
            $ret .= '</ul></div>';
        }

        //$ret = $this->stats['updated'];
        return $ret.$this->results;
    }

    function preview_content()
    {
        $batchtypes = $this->get_batch_types();
        $type = FormLib::get('btype');
        $ret = sprintf("<b>Batch Type</b>: %s <input type=hidden value=%d name=btype /><br />",
            isset($batchtypes[$type]) ? $batchtypes[$type] : 1, $type);
        $ret .= sprintf("<b>Batch Name</b>: %s <input type=hidden value=\"%s\" name=bname /><br />",
            FormLib::get('bname'),FormLib::get('bname'));
        $ret .= sprintf("<b>Owner</b>: %s <input type=hidden value=\"%s\" name=bowner /><br />",
            FormLib::get('bowner'),FormLib::get('bowner'));
        $ret .= sprintf("<b>Start Date</b>: %s <input type=hidden value=\"%s\" name=date1 /><br />",
            FormLib::get('date1'),FormLib::get('date1'));
        $ret .= sprintf("<b>End Date</b>: %s <input type=hidden value=\"%s\" name=date2 /><br />",
            FormLib::get('date2'),FormLib::get('date2'));
        $ret .= sprintf("<b>Product Identifier</b>: %s <input type=hidden value=\"%s\" name=ftype /><br />",
            FormLib::get('ftype'),FormLib::get('ftype'));
        $ret .= sprintf("<b>Includes check digits</b>: <input type=checkbox name=has_checks /><br />");
        $ret .= "<i>&nbsp;&nbsp;&nbsp;&nbsp;UPCs have check digits</i><br />";
        $ret .= "<br />";
        return $ret;
    }

    function form_content()
    {
        ob_start();
        ?>
        <div class="well">
        Use this tool to create a sales batch from an Excel file (XLS or CSV). Uploaded
        files should have a column identifying the product, either by UPC
        or likecode, and a column with prices.
        </div>
        <?php
        return ob_get_clean();
    }

    /**
      overriding the basic form since I need several extra fields   
    */
    protected function basicForm()
    {
        $batchtypes = $this->get_batch_types();
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $owners = new MasterSuperDeptsModel($dbc);
        ob_start();
        ?>
        <form enctype="multipart/form-data" action="FCCBatchPage.php" id="FannieUploadForm" method="post">
        <div class="row form-group form-horizontal">
            <label class="col-sm-2 control-label">Type</label>
            <div class="col-sm-4">
                <select name="btype" class="form-control">
                <?php foreach($batchtypes as $k=>$v) printf("<option value=%d>%s</option>",$k,$v); ?>
                </select>
            </div>
            <label class="col-sm-2 control-label">Start Date</label>
            <div class="col-sm-4">
                <input type="text" name="date1" id="date1" class="form-control date-field" />
            </div>
        </div>
        <div class="row form-group form-horizontal">
            <label class="col-sm-2 control-label">Name</label>
            <div class="col-sm-4">
                <input type="text" name="bname" class="form-control" />
            </div>
            <label class="col-sm-2 control-label">End Date</label>
            <div class="col-sm-4">
                <input type="text" name="date2" id="date2" class="form-control date-field" />
            </div>
        </div>
        <div class="row form-group form-horizontal">
            <label class="col-sm-2 control-label">File</label>
            <div class="col-sm-4">
                <input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
                <input type="file" id="FannieUploadFile" name="FannieUploadFile" />
            </div>
            <label class="col-sm-2 control-label">Owner</label>
            <div class="col-sm-4">
                <select name="bowner" class="form-control">
                <option value="">Choose...</option>
                <?php 
                $prev = '';
                foreach ($owners->find('super_name') as $obj) { 
                    if ($obj->super_name() == $prev) {
                        continue;
                    }
                    echo '<option>' . $obj->super_name() . '</option>';
                    $prev = $obj->super_name();
                }
                ?>
                </select>
            </div>
        </div>
        <div class="row form-group form-horizontal">
            <label class="col-sm-2 control-label">Type</label>
            <div class="col-sm-4">
                <select name="ftype" class="form-control" required>
                    <option value="">Select one...</option>
                    <option>UPCs</option>
                    <option>Likecodes</option>
                </select>
            </div>
            <div class="col-sm-2">
                <button type="submit" class="btn btn-default">Upload File</button>
            </div>
        </div>
        </form>
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

