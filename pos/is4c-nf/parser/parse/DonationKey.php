<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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

namespace COREPOS\pos\parser\parse;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\DeptLib;
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\lib\PrehLib;
use COREPOS\pos\parser\Parser;
use COREPOS\pos\lib\TransRecord;

class DonationKey extends Parser 
{
    function check($str)
    {
        if ($str == "RU" || substr($str,-2)=="RU") {
            return true;
        }

        return false;
    }

    function parse($str)
    {
        $plu = $this->session->get('roundUpPLU');
        $dept = $this->session->get('roundUpDept');
        if ($dept === '') {
            $dept = 701;
        }

        $ret = $this->default_json();

        if ($str == "RU") {
            Database::getsubtotals();
            $ttl = $this->session->get("amtdue");    
            $next = ceil($ttl);
            $amt = sprintf('%.2f',(($ttl == $next) ? 1.00 : ($next - $ttl)));
            $ret = $this->addRoundUp($amt, $plu, $dept);
        } else {
            $amt = substr($str,0,strlen($str)-2)/100;
            $ret = $this->addRoundUp($amt, $plu, $dept);
        }
        PrehLib::ttl();
        $ret['output'] = DisplayLib::lastpage();
        $ret['redraw_footer'] = True;

        return $ret;
    }

    private function addRoundUp($amt, $plu, $dept) {
        //I moved this to it's own function because it would be a duplication of function
        //in parse() in an if/else statement.
        $ret = $this->default_json();
        if ($plu != '') {
            //if the plu is set use the plu
            $upc = str_pad($plu, 13,'0000000000000', STR_PAD_LEFT);
            $row = $this->lookupItem($upc);
            TransRecord::addRecord(array(
                'upc' => $row['upc'],
                'description' => $row['description'],
                'trans_type' => 'I',
                'trans_subtype' => (isset($row['trans_subtype'])) ? $row['trans_subtype'] : '',
                'department' => $row['department'],
                'quantity' => 1,
                'unitPrice' => $amt,
                'total' => $amt,
                'regPrice' => $amt,
                'scale' => $row['scale'],
                'tax' => $row['tax'],
                'foodstamp' => $row['foodstamp'],
                'discount' => 0,
                'memDiscount' => 0,
                'discountable' => $row['discount'],
                'discounttype' => $row['discounttype'],
                'ItemQtty' => 1
            ));
        }  else {
            //if the plu is not set open ring.
            $lib = new DeptLib($this->session);
            $ret = $lib->deptkey($amt*100, $dept.'0', $ret);
        }
        return $ret;
    }

        
    private function lookupItem($upc)
    {
        $dbc = Database::pDataConnect();
        $query = "SELECT inUse,upc,description,normal_price,scale,deposit,
            qttyEnforced,department,local,tax,foodstamp,discount,
            discounttype,specialpricemethod,special_price,groupprice,
            pricemethod,quantity,specialgroupprice,specialquantity,
            mixmatchcode,idEnforced,tareweight,scaleprice";
        $query .= " FROM products WHERE upc = '".$upc."'";
        $result = $dbc->query($query);
        $row = $dbc->fetchRow($result);

        return $row;
    }

    function doc()
    {
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td>DONATE</td>
                <td>
                Round transaction up to next dollar
                with open ring to donation department.
                </td>
            </tr>
            </table>";
    }
}

