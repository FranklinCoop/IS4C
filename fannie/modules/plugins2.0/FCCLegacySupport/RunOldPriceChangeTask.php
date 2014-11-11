<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

class RunOldPriceChangeTask extends FannieTask
{

    public $name = 'Run Old Price Change Once Task';

    public $description = 'A run once taks to run all of the price change batches that havent been run.';

    public $default_schedule = array(
        'min' => 10,
        'hour' => 2,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    public function run()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        //$now = date('Y-m-d 00:00:00');
        $sale_upcs = array();

        $likeP = $dbc->prepare('SELECT u.upc 
                                FROM upcLike AS u
                                    INNER JOIN products AS p ON u.upc=p.upc
                                WHERE likeCode=?');
        $product = new ProductsModel($dbc);

        // lookup all price change batches
        $query = 'SELECT l.upc, l.batchID, l.pricemethod, l.salePrice, l.quantity,
                        b.startDate, b.endDate, b.discounttype
                  FROM batches AS b
                    INNER JOIN batchList AS l ON b.batchID = l.batchID
                  WHERE b.discounttype =0';
        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep, array($now, $now));
        while($row = $dbc->fetch_row($result)) {
            // all items affected by this bathcList record
            // could be more than one in the case of likecodes
            $item_upcs = array();

            // use products column names for readability below
            $normal_price = $row['salePrice'];
            $specialpricemethod = $row['pricemethod'];
            $specialgroupprice = abs($row['salePrice']);
            $specialquantity = $row['quantity'];
            $start_date = $row['startDate'];
            $end_date = $row['endDate'];
            $discounttype = $row['discounttype'];

            // pricemethod 3 and 4 (AB pricing, typically)
            // has some overly complicated rules
            $mixmatch = false;
            if ($specialpricemethod == 3 || $specialpricemethod==4) {
                if ($normal_price >= 0) {
                    $mixmatch = $row['batchID'];
                } else {
                    $mixmatch = -1 * $row['batchID'];
                }
            }

            // unpack likecodes, if needed
            if (substr($row['upc'], 0, 2) == 'LC') {
                $likeCode = substr($row['upc'], 2);
                $likeR = $dbc->execute($likeP, array($likeCode));
                while ($likeW = $dbc->fetch_row($likeR)) {
                    $item_upcs[] = $likeW['upc'];
                    if ($mixmatch !== false) {
                        $mixmatch = $likeCode + 500;
                    }
                }
            } else {
                $item_upcs[] = $row['upc'];
            }

            // check each item to see if it is on
            // sale with the correct parameters
            foreach($item_upcs as $upc) {
                $product->reset();
                $product->upc($upc);
                echo $this->cronMsg('Checking item ' . $upc);
                if (!$product->load()) {
                    echo $this->cronMsg("\tError: item does not exist in products");
                    continue;
                }
                // list of UPCs that should be on sale
                $sale_upcs[] = $upc;

                $changed = false;
                ob_start();
                if ($product->normal_price() == $normal_price) {
                    echo $this->cronMsg("\tnormal_price is correct");
                } else {
                    echo $this->cronMsg("\tnormal_price will be updated");
                    $changed = true;
                    $product->normal_price($normal_price);
                }

                if ($changed) {
                    ob_end_flush(); // report what is changing
                    $product->save();
                } else {
                    ob_end_clean();
                    echo $this->cronMsg("\tAll settings correct");
                }
            } // end loop on batchList record items
        } // end loop on batchList records

    }
}

