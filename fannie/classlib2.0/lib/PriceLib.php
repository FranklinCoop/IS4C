<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

namespace COREPOS\Fannie\API\lib;

/**
  @class PriceLib
*/
class PriceLib 
{
    /**
     * Lookup the current sale status of item(s)
     * @param $dbc [SQLManager] database connection
     * @param $config [FannieConfig] configuration object
     * @param $upc [string|array] UPC(s)
     *      (may include batch-style LC### UPCs)
     * @return [array]
     *
     *  In single-store mode the array is simply keyed by UPC
     *  and the value is a record w/ pricemethod, salePrice, etc
     *
     *  In multi-store mode the array is keyed by UPC and the
     *  value is *another* array keyed by storeID. The value at
     *  $return[UPC][storeID] is a record w/ pricemethod, salePrice,
     *  etc. If the return value only has data from some storeIDs
     *  then that UPC is only on sale at some stores
     */
    public static function effectiveSalePrice($dbc, $config, $upc)
    {
        if (!is_array($upc)) {
            $upc = array($upc);
        }
        list($inStr, $args) = $dbc->safeInClause($upc);

        $query = "SELECT l.upc, 
                    l.batchID, 
                    l.pricemethod, 
                    l.salePrice, 
                    l.groupSalePrice,
                    l.quantity,
                    b.startDate, 
                    b.endDate, 
                    b.discounttype,
                    b.transLimit
                  FROM batches AS b
                    INNER JOIN batchList AS l ON b.batchID = l.batchID
                  WHERE b.discounttype > 0
                    AND l.upc IN ({$inStr})
                    AND ? BETWEEN b.startDate AND b.endDate
                  ORDER BY l.upc,
                    l.salePrice DESC";
        if ($config->get('STORE_MODE') === 'HQ') {
            $query = str_replace('WHERE', ' LEFT JOIN StoreBatchMap AS s ON b.batchID=s.batchID WHERE ', $query);
            $query = str_replace('SELECT', 'SELECT s.storeID,', $query);
        }
        $prep = $dbc->prepare($query);
        $args[] = date('Y-m-d 00:00:00');
        $res = $dbc->execute($prep, $args);
        $ret = array();
        while ($row = $dbc->fetchRow($res)) {
            if (isset($row['storeID'])) {
                if (!isset($ret[$row['upc']])) {
                    $ret[$row['upc']] = array();
                }
                $ret[$row['upc']][$row['storeID']] = $row;
            } else {
                $ret[$row['upc']] = $row;
            }
        }

        return $ret;
    }

    public static function pricePerUnit($price,$sizeStr,$upc = '')
    {
        $country = \FannieConfig::factory()->get('COUNTRY', 'US');

        $num = "";
        $unit = "";
        $mult = 1;
        $inNum = 1;
        for ($i=0; $i < strlen($sizeStr); $i++) {
            if ($inNum == 1) {
                if (is_numeric($sizeStr[$i]) or $sizeStr[$i] == ".") {
                    $num .= $sizeStr[$i];
                } else if ($sizeStr[$i] == "/" or $sizeStr[$i] == "-") {
                    $mult = $num;
                    $num = "";
                } else {
                    $inNum = 0;
                    $unit .= $sizeStr[$i];
                }
            } else {
                $unit .= $sizeStr[$i];
            }
        }

        $unit = ltrim($unit);
        $unit = strtoupper($unit);
        if (strpos($unit,"FL") !== False) {
            $unit = "FLOZ";
        }
        if ($num == "") {
            $num = 1;
        }
        $num = (float)$num;
        $num = $num*$mult;
        if ($num == 0) {
            return '';
        }

        switch($unit) {
            case '#':
            case 'LB':
            case 'LBS':    
                if ($country == "US") {
                    return round($price/($num*16),3)."/OZ";
                } else {
                    return round($price/($num*453.59),3)."/G";
                }
            case 'ML':
                if ($country == "US") {
                    return round($price/($num*0.034),3)."/OZ";
                } else {
                    return round($price/$num,3)."/ML";
                }
            case 'FLOZ':
                if ( $country == 'US' ) {
                    return round($price/$num,3)."/OZ";
                } else {
                    return round($price/($num*29.5735),3)."/ML"; 
                }
            case 'OZ':
            case 'Z':
                if ( $country == 'US' ) {
                    return round($price/$num,3)."/OZ";
                } else {
                    return round($price/($num*28.35),3)."/G"; 
                }
            case 'PINT':
            case 'PINTS':
                if ($country == "US") {
                    return round($price/($num*16),3)."/OZ";
                } else {
                    return round($price/($num*473.18),3)."/ML";
                }
            case 'GR':
            case 'GRAM':
            case 'GM':
            case 'GRM':
            case 'G':
                if ($country == "US"){
                    return round($price/($num*0.035),3)."/OZ";
                } else {
                    return round($price/$num,3)."/G";
                }
            case 'LTR':
            case 'L':
                if ($country == "US"){
                    return round($price/($num*33.814),3)."/OZ";
                } else {
                    return round($price/1000,3)."/ML";
                }
            case 'GAL':
                if ($country == "US") {
                    return round($price/($num*128),3)."/OZ";
                } else {
                    return round($price/($num*3785.41),3)."/ML";
                }
            default:
                return round($price/$num,3)."/".$unit;
        }

        return "";
    }
    /*
    this is a kludge but I'm on short notice to get this done
    there is a mass of spagghti code for the price per unit
    because there are three places that it is calucalted for tags.

    One here which is the most widely used, one in the Products Model one inthe BatchTagsmodles
    and one in the tag data sourse.

    I need this to do what I need it to do.

    Idealy at somepoint we should work it out so that price per unit is only calcualted in one spot
    and can be changed modularly because diffrent states and countries have diffrent laws about how
    unit cost needs to be reported.
    */
    public static function FCC_PricePerUnit($dbc, $upc, $price, $sizeStr) {
        $query = "SELECT p.unitofmeasure FROM products p where p.upc = ? GROUP BY p.upc";
        $prep = $dbc->prepare($query);
        $ret = $dbc->execute($prepUnitInfo, array($upc));


        $unitSize = '';
        $packUnit = '';
        $strUnit = '';
        if (!$ret || $dbc->numRows($ret) == 0) {
            //failed to get proper unit info, defualt to old scheme.
            // get the unit info from the FCC Legacy table.
            $queryUnitInfo = "SELECT p.unitStandard, p.size, p.unit FROM prodStandardUnit p WHERE p.upc = ?";
            $prepUnitInfo = $dbc->prepare($queryUnitInfo);
            $resUnitInfo = $dbc->execute($prepUnitInfo, array($upc));
        
            if (!$resUnitInfo || $dbc->numRows($resUnitInfo) == 0) {
                //Legacy method failed use CORE default method
                return PriceLib::pricePerUnit($price,$sizeStr,$upc); //defaults to old method if data is missing.
            }

            $rowUnitInfo = $dbc->fetchRow($resUnitInfo);
            $unitSize = $rowUnitInfo[];
            $packUnit = $rowUnitInfo['unit'];
            $stdUnit = $rowUnitInfo['unitStandard'];
        } else {
            // break up the string
            $strRow = $dbc->fetchRow($ret);
            $str = $strRow[0];
            $strArray = explode('/', $str);
            $unitSize = $strArray[0];
            $packUnit = $strArray[1];
            $stdUnit = $strArray[2];
        }




        //look up the unit conversion.
        $queryConversion = "SELECT c.rate FROM unitConversion c WHERE c.unit_name = ? AND c.unit_std = ?";
        $args = array($packUnit, $stdUnit);
        $prepConversion = $dbc->prepare($queryConversion);
        $resConversion = $dbc->execute($prepConversion, $args);
        if (!$resConversion || $dbc->numRows($resConversion) == 0) {
            return PriceLib::pricePerUnit($price,$sizeStr,$upc); //defaults to old method if data is missing.
        }
        $rowConversion = $dbc->fetchRow($resConversion);

        //get the price.
        $queryPrice = "SELECT p.normal_price FROM products p WHERE p.upc = ?";
        $prepPrice = $dbc->prepare($queryPrice);
        $resPrice = $dbc->execute($prepPrice, array($upc));
        if (!$resPrice || $dbc->numRows($resPrice) == 0) {
            return 'missing price data';
        }
        $rowPrice = $dbc->fetchRow($resPrice);
        $price = $rowPrice['normal_price'];

        //return the unit price.
        $pricePerUnit = $price*($rowConversion['rate']/$unitSize);
        if ($pricePerUnit == 0) {return "Size: ".$packUnit."\n Conversion Factor: ". $rowConversion['rate']; }
        else { return round($pricePerUnit,2); }
    }
}

