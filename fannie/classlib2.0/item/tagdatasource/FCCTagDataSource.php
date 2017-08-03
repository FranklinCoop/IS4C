<?php

namespace COREPOS\Fannie\API\item\tagdatasource;

/**
  @class TagDataSource
  This class exists solely as a parent
  class that other modules can implement.
*/
class FCCTagDataSource extends \COREPOS\Fannie\API\item\TagDataSource
{
    /** 
      Get shelf tag fields for a given item
      @param $dbc [SQLManager] database connection object
      @param $upc [string] Item UPC
      @param $price [optional, default false] use a specified price
        rather than the product's current price
      @return [keyed array] of tag data with the following keys:
        - upc
        - description
        - brand
        - normal_price
        - sku
        - size
        - units
        - vendor
        - pricePerUnit
    */
    public function getTagData($dbc, $upc, $price=false)
    {
        $query = '
            SELECT p.upc,
                p.description,
                p.normal_price,
                COALESCE(p.brand, x.manufacturer) AS brand,
                COALESCE(v.vendorName, x.distributor) AS vendor,
                p.size AS p_size,
                p.unitofmeasure,
                i.sku,
                p.unitofmeasure,  
                i.size AS vi_size
            FROM products AS p
                LEFT JOIN prodExtra AS x ON p.upc=x.upc
                LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
                LEFT JOIN vendorItems AS i ON p.upc=i.upc AND v.vendorID=i.vendorID
            WHERE p.upc=?';
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, array($upc));


        $ret = array(
            'upc' => $upc,
            'description' => '',
            'brand' => '',
            'normal_price' => 0.0,
            'sku' => '',
            'size' => '',
            'units' => 0,
            'vendor' => '',
            'pricePerUnit' => '',
        );
        if (!$res || $dbc->numRows($res) == 0) {
            return $ret;
        }


        $row = $dbc->fetchRow($res);
        $ret['description'] = $row['description'];
        $ret['brand'] = $row['brand'];
        //$ret['normal_price'] = $row['normal_price'];
        $ret['vendor'] = $row['vendor'];
        $ret['sku'] = $row['sku'];
        $ret['units'] = $row['units'];

        if ($price !== false) {
            $ret['normal_price'] = $price;
        } else {
            $ret['normal_price'] = $row['normal_price'];
        }

        if (is_numeric($row['p_size']) && !empty($row['p_size']) && !empty($row['unitofmeasure'])) {
            $ret['size'] = $row['p_size'] . ' ' . $row['unitofmeasure'];
        } elseif (!empty($row['p_size'])) {
            $ret['size'] = $row['p_size'];
        } elseif (!empty($row['vi_size'])) {
            $ret['size'] = $row['vi_size'];
        }

        $ret['pricePerUnit'] = $this->getUnitPrice($dbc, $upc);

        return $ret;
    }

    private function getUnitPrice($dbc, $upc) {
        // get the unit info.
        $queryUnitInfo = "SELECT p.unitStandard, p.size, p.unit FROM prodStandardUnit p WHERE p.upc = ?";
        $prepUnitInfo = $dbc->prepare($queryUnitInfo);
        $resUnitInfo = $dbc->execute($prepUnitInfo, array($upc));
        
        if (!$resUnitInfo || $dbc->numRows($resUnitInfo) == 0) {
            return 'missing unit info';
        }
        $rowUnitInfo = $dbc->fetchRow($resUnitInfo);

        //look up the unit conversion.
        $queryConversion = "SELECT c.rate FROM unitConversion c WHERE c.unit_name = ? AND c.unit_std = ?";
        $args = array($rowUnitInfo['unit'], $rowUnitInfo['unitStandard']);
        $prepConversion = $dbc->prepare($queryConversion);
        $resConversion = $dbc->execute($prepConversion, $args);
        if (!$resConversion || $dbc->numRows($resConversion) == 0) {
            return 'missing unit info';
        }
        $rowConversion = $dbc->fetchRow($resConversion);

        //return the unit price.
        $pricePerUnit = $rowUnitInfo['size'] * $rowConversion['rate'];
        if ($pricePerUnit == 0) {return "Size: ".$rowUnitInfo['unit'] ."\n Conversion Factor: ". $rowConversion['rate']; }
        else { return $pricePerUnit; }    }
}