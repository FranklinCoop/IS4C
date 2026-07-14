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
use \BarcodeLib;
use \NCGSignDataModel;
use lib\FPDF_extended;
use DateTime;

class NCGSignage extends \COREPOS\Fannie\API\item\FannieSignage 
{   
    //Retrive the line data from NCG if avalable.
    protected function getNCGSignData($item) {
        $dbc = $this->getDB();
        
        $upc = ltrim($item['upc'], '0');
        $len = strlen($upc);
        $is_ean = false;
        if (strlen($upc) == 12) { 
            // must be EAN
            $check = BarcodeLib::getCheckDigit($upc);
            $upc .= $check;
            $is_ean = true;
        } else {
            $upc = str_pad($upc, 11, '0', STR_PAD_LEFT);
            $check = BarcodeLib::getCheckDigit($upc);
            $upc = '0' . $upc . $check;
        }
        $start_date = new DateTime($item['startDate']);
        $end_date = new DateTime($item['endDate']);

        $newItem = array();

        $model = new NCGSignDataModel($dbc);
        $model->start_date($start_date->format('Y-m-d').' 00:00:00');
        $model->end_date($end_date->format('Y-m-d').' 23:59:59');            
        $model->upc(str_pad(ltrim($upc, '0'), 12, '0', STR_PAD_LEFT));
        $exists = $model->load();

        if ($exists) {
                // fully init new record
                $newItme['unitOfMesure'] = $model->unitOfMesure();
                $newItme['salePrice'] = $model->posPrice();
                $newItme['signPrice'] = $model->signPrice();
                $newItme['priceDevider'] = $model->priceDevider();
                $newItme['multiPrice'] = $model->multiPrice();
                $newItme['unitPrice'] = $model->unitPrice();
            $newItem = array(
                'upc' => $model->upc(),
                'SignSize' => '',
                'SignType' => '',
                'startDate' => $model->start_date(),
                'endDate' => $model->end_date(),
                'brand' => $model->brand(),
                'description' => $model->description(),
                'unitSize' => $model->unitSize(),
                'unitOfMesure' => $model->unitOfMesure(),
                'salePrice' => $model->posPrice(),
                'normalPrice' => $item['nonSalePrice'],
                'signPrice' => sprintf('$%.2f', $item['normal_price']),
                'priceDevider' => $model->priceDevider(),
                'multiPrice' => $model->multiPrice(),
                'unitPrice' => $item['pricePerUnit'],
                'saleUnitPrice' => $model->unitPrice(),
                'attribute' => $model->attribute(),
                'vendor' => $item['vendor'],
                'sku' => $item['sku'],
                'dept_name' => $item['dept_name']
            );
        } else {
            $newItem = array(
                'upc' => $upc,
                'SignSize' => '',
                'SignType' => '',
                'startDate' => $item['startDate'],
                'endDate' => $item['endDate'],
                'brand' => $item['brand'],
                'description' => $item['description'],
                'unitSize' => $item['size'],
                'unitOfMesure' => $item['unitofmeasure'],
                'salePrice' => $item['normal_price'],
                'normalPrice' => $item['nonSalePrice'],
                'signPrice' => sprintf('$%.2f', $item['normal_price']),
                'priceDevider' => '',
                'multiPrice' => '',
                'unitPrice' => $item['pricePerUnit'],
                'saleUnitPrice' => '',
                'attribute' => $item['numflag'],
                'vendor' => $item['vendor'],
                'sku' => $item['sku'],
                'dept_name' => $item['dept_name']
            );
        }

        return $newItem;
    }

    private function getUnitPrice() {

    }

    private function getUnit() {
        
    }

    protected function calculateSaved($normalPrice, $salePrice) {
        $textString = 'You Saved ';
        $amount = $normalPrice - $salePrice;
        $textString .= ($amount< 1) ? ($amount*100).'¢' : sprintf('$%.2f', $amount) ;
        
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $textString);
    }
    
    public function drawPDF()
    {

    }
}

}

