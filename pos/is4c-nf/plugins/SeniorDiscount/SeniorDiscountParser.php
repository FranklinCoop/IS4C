<?php
/*******************************************************************************

    Copyright 2017 Franklin Community Coop

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

use COREPOS\pos\lib\DiscountModule;
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\parser\Parser;
use COREPOS\pos\lib\TransRecord;

class SeniorDiscountParser extends Parser 
{
    function check($str)
    {
        if (substr($str,-2) == "OD" || $str==="OD"){
            $strl = substr($str,0,strlen($str)-2);
            if (substr($str,0,2) == "VD") {
                return true;
            } elseif (!is_numeric($strl)) {
                return false;
            } elseif ($this->session->get("tenderTotal") != 0) {
                $this->ret['output'] = DisplayLib::boxMsg(
                    _("discount not applicable after tender"),
                    '',
                    false,
                    DisplayLib::standardClearButton()
                );
            } elseif ($strl > 50) {
                $this->ret['output'] = DisplayLib::boxMsg(
                    _("discount exceeds maximum"),
                    '',
                    false,
                    DisplayLib::standardClearButton()
                );
            } elseif ($strl < 0) {
                $this->ret['output'] = DisplayLib::boxMsg(
                    _("discount cannot be negative"),
                    '',
                    false,
                    DisplayLib::standardClearButton()
                );
            } elseif ($strl <= 50 and $strl > 0) {
                return true;
            } else {
                return false;
            }
            return true;
        } elseif ($str === "OD") {
            return true;
        }
        return false;
    }

    function parse($str)
    {
        /**
            Applies or removes senior discount.
            Adds a comment string to the trans record stating what action has been taken.
            Then returns to the last page.
        */
        $ret = $this->default_json();
        $discount = substr($str,0,strlen($str)-2);
        $description = $discount.'% Seinor Discount Applied';
        if (CoreLocal::get('SeniorDiscountFlag')==1) {
            $discount = 0;
            $description = 'Senior Discount Removed';
            CoreLocal::set('SeniorDiscountFlag', 0);
        } else if ($str === "OD") {
            $discount = CoreLocal::get('seniorDiscountPercent') * 100;
            $description = $discount.'% Seinor Discount Applied';
            CoreLocal::set('SeniorDiscountFlag',1);
        } else {
            CoreLocal::set('SeniorDiscountFlag',1);
        }
        CoreLocal::set('SeniorDiscountAmt',$discount);
        //update discount.
        DiscountModule::updateDiscount(new DiscountModule($discount, 'SeniorDiscount',True));
        //adds record of the action.
        TransRecord::addRecord(array(
            'description' => $description,
            'trans_type' => '0',
            'trans_status' => 'D',
            'voided' => 4,
        ));
        $ret['output'] = DisplayLib::lastpage();
        $ret['redraw_footer'] = true;
        return $ret;
    }
}

