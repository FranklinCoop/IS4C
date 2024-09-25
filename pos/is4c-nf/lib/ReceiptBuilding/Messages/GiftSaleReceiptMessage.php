<?php
/*******************************************************************************

    Copyright 2024 Whole Foods Co-op

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

namespace COREPOS\pos\lib\ReceiptBuilding\Messages;
use COREPOS\pos\lib\MemberLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\ReceiptLib;
use COREPOS\pos\lib\Database;
use \CoreLocal;

/**
  @class GiftSaleReceiptMessage
*/
class GiftSaleReceiptMessage extends ReceiptMessage {

    public function select_condition(){
        $paycardConf = new PaycardConf();
        $dept = $paycardConf->get('PaycardDepartmentGift');
        if (count($dept) == 0) {
            return "SUM(CASE WHEN department=902 THEN total ELSE 0 END)";
        }
        $equityString = implode(',', $dept);
        return "SUM( CASE WHEN department IN (" . $equityString . ") THEN 1 ELSE 0 END) ";  
    }

    public function message($val, $ref, $reprint=False)
    {
        return '';
    }

    public function standalone_receipt($ref, $reprint=false)
    {
	    $date = ReceiptLib::build_time(time());
        list($emp, $reg, $trans) = ReceiptLib::parseRef($ref);
        $slip = '';

        $giftString = array();
        $paycardConf = new PaycardConf();
        $giftDept = $paycardConf->get('PaycardDepartmentGift');
        if (count($giftDept) == 0) {
            $giftString[] = 902;
        } else {
            $giftString = implode(',', $giftDept);
        }
        // query database for gc receipt info 
        $dbc = Database::tDataConnect();
        if ($reprint) {
            $dbc = Database::mDataConnect();
            if ($dbc === false) {
                return '';
            }
        }

        $sql = "SELECT 1*SUM(Total) AS amount, 
                `datetime` AS datetime,
                card_no
                FROM localtranstoday 
                WHERE department IN (" . $giftString . ")
                   AND emp_no=".$emp."
                   AND register_no = ".$reg."
                   AND trans_no = ".$trans."
                   AND datetime >= ".$dbc->curdate();
        $result = $dbc->query($sql);
        $num = $dbc->numRows($result);
        while($row = $dbc->fetchRow($result)){
            $slip .= "\n\n\n";
            $slip .= ReceiptLib::centerString("................................................")."\n";
            // store header
            for ($i=1; $i<= CoreLocal::get('chargeSlipCount'); $i++) {
                $slip .= ReceiptLib::centerString(CoreLocal::get("chargeSlip" . $i))."\n";
            }
            $slip .= "\n";
            $slip .= ReceiptLib::boldFont()."GIFT CARD SALE\n";
            $slip .= "Date: ".date('m/d/y h:i a', strtotime($row['datetime']))."\n";
            $slip .= "REFERNCE #: ".$emp."-".$reg."-".$trans."\n";
            $slip .= "Member Account:".$row['card_no']."\n\n";
            $slip .= ReceiptLib::boldFont().ReceiptLib::centerString(" S T O R E   C O P Y ").ReceiptLib::normalFont()."\n";
            $slip .= ReceiptLib::boldFont().ReceiptLib::centerString("K E E P  I N  D R A W E R\n").ReceiptLib::normalFont()."\n";
            $slip .= "\n\n";
            $slip .= ReceiptLib::boldFont()."Amount: ".$row['amount'].ReceiptLib::normalFont()."\n"; // bold ttls apbw 11/3/07
            $slip .= ReceiptLib::centerString("................................................")."\n";
        }

        return $slip;
    }

    public $standalone_receipt_type = 'gsSlip';
}

