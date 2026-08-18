<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op, Duluth, MN

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

namespace COREPOS\Fannie\Plugin\NCGSignPilot {
use COREPOS\Fannie\API\lib\PriceLib;
use \BarcodeLib;
use \NCGSignDataModel;
use lib\FPDF_extended;
use DateTime;
use \VendorSKUtoPLUModel;
use \FannieConfig;
class NCGSignage extends \COREPOS\Fannie\API\item\FannieSignage 
{   
    //sgin hanger types
    CONST PRICE_TYPE_NORMAL = 1;
    CONST PRICE_TYPE_SPLIT  = 2;
    CONST PRICE_TYPE_BOGO   = 3;
    //Retrive the line data from NCG if avalable.
    protected function getNCGSignData($item) {
        $dbc = $this->getDB();
        $upc = str_pad(ltrim($item['upc'], '0'), 12, '0', STR_PAD_LEFT);

        if (strlen($upc) == 12) {
            $upc = ltrim($upc, '0');
            $upc .= $check = BarcodeLib::getCheckDigit($upc);
            $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
        }
        //check if the upc is a plu and needs to be mapped to a upc.
        
        $lookUpUPC = str_pad(ltrim($upc, '0'), 12, '0', STR_PAD_LEFT);
        /*
        if ($len < 6) {
            $vendMapModel = new VendorSKUtoPLUModel($this->getDB());
            $vendMapModel->sku[$item['upc']];
            //$vendModel->vind();
            foreach ($vendMapModel->find() as $obj) {
                $lookUpUPC = $obj->upc;
            }
        }*/

        // dates for formating
        $start_date = new DateTime($item['startDate']);
        $end_date = new DateTime($item['endDate']);

        //error checking to make sure we don't thow any undefiend index warnings;
        $price = (array_key_exists('nonSalePrice',$item)) ? $item['nonSalePrice'] : $item['normal_price'];
        $quantity = (array_key_exists('nonSaleQuantity',$item)) ? $item['nonSaleQuantity'] : $item['quantity'];
        $groupPrice = '';
        $priceDevider = 1;

        if (array_key_exists('nonSaleGroupPrice',$item)) {
            //$newItem['groupPrice'] = $item['nonSaleGroupPrice'];
        }
        if($newItem['priceDevider'] == 2) {
            //$newItem['signPriceType'] = NCGSignage::PRICE_TYPE_BOGO;
        }

        $pricePerUnit = false;
        $pricePerUnit = $this->getUnitPrice($dbc, $price,$item['unitofmeasure'],$item['size']);
        $pricePerUnit = ($pricePerUnit) ? $pricePerUnit : $item['pricePerUnit'];

        $salePricePerUnit = false;
        $salePricePerUnit = $this->getUnitPrice($dbc, $item['normal_price'],$item['unitofmeasure'],$item['size'],$item['unitofmeasure']);
        $salePricePerUnit = ($salePricePerUnit) ? $salePricePerUnit : $item['pricePerUnit'];

        $superName = $item['superDeptName'];
        $strArray = explode(':', $item['superDeptName']);
        if (sizeof($strArray) > 1){
            $superName = ltrim($strArray[1]);
        }
        $newItem = array(
                'upc' => $item['upc'],
                'SignSize' => '',
                'SignType' => '',
                'startDate' => $item['startDate'],
                'endDate' => $item['endDate'],
                'brand' => $item['brand'],
                'description' => $item['description'],
                'unitSize' => $item['size'],
                'unitOfMeasure' => $this->getUnit($item['unitofmeasure'], $item['size']),
                'salePrice' => $item['normal_price'],
                'normalPrice' => $price,
                'signPrice' => sprintf('$%.2f', $item['normal_price']),
                'priceDevider' => $item['quantity'],
                'multiPrice' => '',
                'unitPrice' => $pricePerUnit,
                'saleUnitPrice' => $salePricePerUnit,
                'attribute' => $this->getAttributes($dbc, $item['upc']),
                'vendor' => $item['vendor'],
                'sku' => $item['sku'],
                'dept_name' => $item['dept_name'],
                'superDeptName' => $superName,
                'signPriceType' => NCGSignage::PRICE_TYPE_NORMAL,
                'saleGroupPrice' => '',
                'nonSaleQuantity' => '',
                'groupPrice' => ''
        );

        $model = new NCGSignDataModel($dbc);
        $model->start_date($start_date->format('Y-m-d').' 00:00:00');
        //$model->end_date($end_date->format('Y-m-d').' 23:59:59');            
        $model->upc($lookUpUPC);
        $exists = $model->load();

        if ($exists) {
                $salePricePerUnit = false;

                $salePricePerUnit = $this->getUnitPrice($dbc, $model->posPrice(),$item['unitofmeasure'], $model->unitSize(), $model->unitOfMesure());
                $pricePerUnit = false;
                $pricePerUnit = $this->getUnitPrice($dbc, $item['nonSalePrice'],$item['unitofmeasure'], $model->unitSize(), $model->unitOfMesure());
                // update return array
                //$newItem['unitOfMesure'] = $model->unitOfMesure();
                $newItem['salePrice'] = $model->posPrice();
                $newItem['signPrice'] = $model->signPrice();
                $newItem['priceDevider'] = $model->priceDevider();
                $newItem['multiPrice'] = $model->multiPrice();
                $newItem['saleUnitPrice'] = ($salePricePerUnit) ? $salePricePerUnit :  $model->unitPrice();
                $newItem['unitPrice'] = ($pricePerUnit) ? $pricePerUnit :  $newItem['unitPrice'];
                $newItem['description'] = $model->description();
                $newItem['brand'] = $model->brand();
                $newItem['unitSize'] = $model->unitSize();
                $newItem['unitOfMeasure'] = $this->getUnit($item['unitofmeasure'], $model->unitOfMesure());

                if ($model->signPrice() === 'BOGO') {
                    $newItem['signPriceType'] = NCGSignage::PRICE_TYPE_BOGO;
                } elseif ($model->priceDevider() > 1) {
                    $newItem['signPriceType'] = NCGSignage::PRICE_TYPE_SPLIT;
                }

        } else {
            $newItem['unitSize'] .= ' -**';
        }
        return $newItem;
    }

    private function getUnitPrice($dbc, $price, $unitofmaesure, $sizeStr = '', $ncgunit = '') {
        $unitSize = 0;
        $packUnit = '';
        $strUnit = '';
        $rowConversion ='';
        $pricePerUnit = 'TOPERR';
        $strArray = explode('/', $unitofmaesure);
        if (sizeof($strArray) < 3 || $unitofmaesure == '') {
            $sizeArray = PriceLib::splitSizeStr($sizeStr);
            $packUnit = $sizeArray['unit'];
            $unitSize = $sizeArray['number'];
            $stdUnit = $sizeArray['unit'];
            if ($ncgunit != 0 && !is_null($ncgunit)) {
                $stdUnit = $ncgunit;
            }
        } else {
            $unitSize = $strArray[0];
            $packUnit = $strArray[1];
            $stdUnit = $strArray[2];
        }

        ///look up the unit conversion.
        $conversion = 1;
        if ($packUnit != $stdUnit) {
            $queryConversion = "SELECT c.rate FROM unitConversion c WHERE c.unit_name = ? AND c.unit_std = ?";
            $args = array($packUnit, $stdUnit);
            $prepConversion = $dbc->prepare($queryConversion);
            $resConversion = $dbc->execute($prepConversion, $args);
            if (!$resConversion || $dbc->numRows($resConversion) == 0) {
                $conversion = 1;
            } else {
                $rowConversion = $dbc->fetchRow($resConversion);
                $conversion = $rowConversion['rate'];
            }
        }
        //if ($conversion == 0) return 'CONERR';
        
        $pricePerUnit = ($unitSize && $unitSize != 0) ? number_format(($price*($conversion/$unitSize)),2) : $pricePerUnit ;
        //if ($pricePerUnit == 0) return false;
        return $pricePerUnit;
    }

    private function getUnit($fannieUnit, $ncgUnit = false, $sizeStr ='') {
        $strArray = explode('/', $fannieUnit);
        $ret = $fannieUnit;
        if (sizeof($strArray) < 3) {
            if ($ncgUnit) {
                $ret = $ncgUnit;
            } else if($sizeStr != '') {
                $sizeArray = PriceLib::splitSizeStr($sizeStr);
                if(sizeof($sizeArray) >1 ) {
                    $ret = $sizeArray['unit'];
                }
            }
        } else {
            $ret = $strArray[2];
        }
        return $ret;
    }

    protected function calculateSaved($normalPrice, $salePrice, $bogo = false) {
        $textString = 'You Saved ';
        $amount = 0;
        if ($bogo) {
            $amount = $normalPrice;
        } else {
            $amount = $normalPrice - $salePrice;
        }
        
        $textString .= ($amount< 1) ? ($amount*100).'¢' : sprintf('$%.2f', $amount) ;
        
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $textString);
    }

    protected function getAttributes($dbc, $upc) {
        //get unit and flagging data;        
        $query = "
            SELECT f.description,
                f.bit_number,
                (1<<(f.bit_number-1)) & p.numflag AS flagIsSet
            FROM products AS p, 
                prodFlags AS f
            WHERE p.upc=?
                " . (FannieConfig::config('STORE_MODE') == 'HQ' ? ' AND p.store_id=? ' : '') . "
                AND f.active=1";
        $args = array($upc);
        if (FannieConfig::config('STORE_MODE') == 'HQ') {
            $args[] = FannieConfig::config('STORE_ID');
        }
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep,$args);
        
        if ($dbc->numRows($res) == 0){
            // item does not exist
            $prep = $dbc->prepare('
                SELECT f.description,
                    f.bit_number,
                    0 AS flagIsSet
                FROM prodFlags AS f
                WHERE f.active=1');
            $res = $dbc->execute($prep);
        }

        //please use the order  "Local, Organic, NONGMO, Gluten Free, cv, glyphosate-free
        $flags = array('Local'=> false, 'Organic' => false, 'Non_GMO' => false, 'Gluten Free'=>false, 'cv' => false, 'glyphosate-free' => false);
        
        while($info = $dbc->fetchRow($res)){
                $flags[$info['description']] = $info['flagIsSet'];
       }
       return $flags;
    }
    
    public function drawPDF()
    {
        
    }
}

}

