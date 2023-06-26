<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op.

    This file is part of IT CORE.

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

use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\Search\Products\ProductSearch;

class DepartmentProductSearch extends ProductSearch {
    /* True if this should be the only search module used. */
    public $this_mod_only = 1;

    public function search($str){
        $ret = array();
        $sql = Database::pDataConnect();
        $args = array('%' . $str . '%');
        $string_search = "(description LIKE ?)";
        // new coluumns 16Apr14
        // search in products.brand and products.formatted_name
        // if those columns are available
        if (CoreLocal::get('NoCompat') == 1) {
            $string_search = "(
                                description LIKE ?
                                OR brand LIKE ?
                                OR formatted_name LIKE ?
                              )";
            $args = array(
                '%' . $str . '%',
                '%' . $str . '%',
                '%' . $str . '%',
            );
        } else {
            $table = $sql->tableDefinition('products');
            if (isset($table['brand']) && isset($table['formatted_name'])) {
                $string_search = "(
                                    description LIKE ?
                                    OR brand LIKE ?
                                    OR formatted_name LIKE ?
                                  )";
                $args = array(
                    '%' . $str . '%',
                    '%' . $str . '%',
                    '%' . $str . '%',
                );
            }
        }
        $query = "SELECT p.upc, 
                    p.description, 
                    p.normal_price, 
                    p.special_price,
                    p.scale 
                  FROM products p
                  JOIN produceSearchList s on p.upc = s.upc AND p.store_id = s.store_id
                  WHERE $string_search
                    AND p.upc LIKE '0000000%'
                    AND p.inUse=1
                    AND s.searchable=1
                    AND s.store_id = 1
                  ORDER BY description";
        $prep = $sql->prepare($query);
        $result = $sql->execute($prep, $args);
        while ($row = $sql->fetch_row($result)) {
            $ret[$row['upc']] = $row;
        }

        return $ret;
    }
}

