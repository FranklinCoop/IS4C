<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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
  @class StoreChargeMessage

  This replaces a pair of receipt methods that were/are
  hardcoded into ReceiptLib. Every receipt that has charge
  activity includes the current balance as a footer. That's
  the primary message provided by this class. A transaction
  that includes a charge may also trigger a signature slip
  if paper signature slips are being used. The signature
  slip is provided by standalone receipt.
*/
class PayPalReceiptMessage extends ReceiptMessage 
{
    /**
      This message has to be printed on paper
    */
    public $paper_only = false;

    public function select_condition()
    {
        //$arDepts = MiscLib::getNumbers(CoreLocal::get('ArDepartments'));
        //if (count($arDepts) == 0) {
        //    return ' CASE WHEN trans_subtype=\'MI\' THEN 1 ELSE 0 END ';
        //}

        return "SUM(CASE WHEN trans_subtype='PY' THEN 1 ELSE 0 END)";
    }

    /**
      Generate the message
      @param $val the value returned by the object's select_condition()
      @param $ref a transaction reference (emp-lane-trans)
      @param $reprint boolean
      @return [string] message to print on receipt
    */
    public function message($val, $ref, $reprint=false)
    {
        return "TEST REMOVE LATER\n\n\n";

    }

    public function standalone_receipt($ref, $reprint=false)
    {
	      $date = ReceiptLib::build_time(time());
        list($emp, $reg, $trans) = ReceiptLib::parseRef($ref);
        $slip = '';

        // query database for gc receipt info 
        $dbc = Database::tDataConnect();
        if ($reprint) {
            $dbc = Database::mDataConnect();
            if ($dbc === false) {
                return '';
            }
        }

        $sql = "SELECT -1*SUM(Total) AS amount, 
                `datetime` AS datetime
                FROM localtranstoday 
                WHERE trans_subtype = 'PY'
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
            $slip .= "PAYPAL CHARGE\n";
            $slip .= "Date: ".date('m/d/y h:i a', strtotime($row['datetime']))."\n";
            $slip .= "REFERNCE #: ".$emp."-".$reg."-".$trans."\n\n";
            $slip .= ReceiptLib::boldFont().ReceiptLib::centerString(" S T O R E   C O P Y ").ReceiptLib::normalFont()."\n";
            $slip .= ReceiptLib::boldFont().ReceiptLib::centerString("K E E P  I N  D R A W E R\n").ReceiptLib::normalFont()."\n";
            $slip .= "\n\n";
            $slip .= ReceiptLib::boldFont()."Amount: ".$row['amount'].ReceiptLib::normalFont()."\n"; // bold ttls apbw 11/3/07
            $slip .= ReceiptLib::centerString("................................................")."\n";
        }

        return $slip;
    }

    public $standalone_receipt_type = 'pySlip';
}

