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
                s.unit AS units,  
                s.size AS vi_size
            FROM products AS p
                LEFT JOIN prodExtra AS x ON p.upc=x.upc
                LEFT JOIN vendors AS v ON p.default_vendor_id=v.vendorID
                LEFT JOIN vendorItems AS i ON p.upc=i.upc AND v.vendorID=i.vendorID
                LEFT JOIN prodStandardUnit s ON p.upc=s.upc
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
        $ret['units'] = $row['vi_size'];

        if ($price !== false) {
            $ret['normal_price'] = $price;
        } else {
            $ret['normal_price'] = $row['normal_price'];
        }

        $ret['size'] = $row['units'];

        $ret['unitStandard'] = explode('/', $row['unitofmeasure'])[2];

        //            $strRow = $dbc->fetchRow($ret);
        //    $str = $strRow[0];
        //    $strArray = explode('/', $str);

        $ret['pricePerUnit'] = \COREPOS\Fannie\API\lib\PriceLib::FCC_PricePerUnit($dbc, $upc, $row['normal_price'], $row['p_size']);

        return $ret;
    }

}