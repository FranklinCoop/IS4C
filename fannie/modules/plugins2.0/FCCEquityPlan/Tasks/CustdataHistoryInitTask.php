<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op

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

class CustdataHistoryInitTask extends FannieTask
{
	public $name = 'FCC Custdata Histroy Init';
	public $description = 'Initizlize the FCC dailt custdata histroy tabel';

    public $default_schedule = array(
        'min' => 30,
        'hour' => 1,
        'day' => '1',
        'month' => '*',
        'weekday' => '*',
        );                       
	function run(){
		global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $data = array();
        
        
        $start_date = new DateTime('2023-10-31 00:00:00');
        $stringDate = $start_date->format('Y-m-d H:i:s');
        $updateQ = "INSERT INTO core_op.custdataHistory (CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`,
                                                         memType, staff, SSI, Purchases, NumberOfChecks, memCoupons, blueLine, Shown, LastChange, histDate)
                    SELECT CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`, memType, staff, SSI, Purchases,
                    NumberOfChecks, memCoupons, blueLine, Shown, LastChange, '{$stringDate}' FROM core_bak.custdata20231031 WHERE personNum = 1";
        //$prep = $dbc->prepare($updateQ);
        //$row = $dbc->execute($prep,array());

        $start_date = new DateTime('2023-11-01 00:00:00');
        $end_date = new DateTime('2023-12-01 00:00:00');
        $stringDate = $start_date->format('Y-m-d H:i:s');
        while($start_date->format('Y-m-d') != $end_date->format('Y-m-d')) {
            $updateQ = "INSERT INTO core_op.custdataHistory (CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`,
            memType, staff, SSI, Purchases, NumberOfChecks, memCoupons, blueLine, Shown, LastChange, histDate)
            SELECT CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`, memType, staff, SSI, Purchases,
            NumberOfChecks, memCoupons, blueLine, Shown, LastChange, '{$stringDate}' FROM core_bak.custdata2023111 WHERE personNum = 1";
            $prep = $dbc->prepare($updateQ);
            $dbc->execute($prep,array());
            $start_date->modify('+1 day');
            $stringDate = $start_date->format('Y-m-d H:i:s');
            //echo "date: ".$stringDate."\n";
        }

        $start_date = new DateTime('2023-12-01 00:00:00');
        $end_date = new DateTime('2023-12-30 00:00:00');
        $end_date->modify('-1 day');
        $stringDate = $start_date->format('Y-m-d H:i:s');

        while($start_date->format('Y-m-d') != $end_date->format('Y-m-d')) {
            $updateQ = "INSERT INTO core_op.custdataHistory (CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`,
            memType, staff, SSI, Purchases, NumberOfChecks, memCoupons, blueLine, Shown, LastChange, histDate)
            SELECT CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`, memType, staff, SSI, Purchases,
            NumberOfChecks, memCoupons, blueLine, Shown, LastChange, '{$stringDate}' FROM core_bak.custdata231201 WHERE personNum = 1";
            $prep = $dbc->prepare($updateQ);
            $dbc->execute($prep,array());
            $start_date->modify('+1 day');
            $stringDate = $start_date->format('Y-m-d H:i:s');
            //echo "date: ".$stringDate."\n";
        }


        $checkQ = "SELECT DATE(MAX(histDate)) FROM core_op.custdataHistory";
        $prep = $dbc->prepare($checkQ);
		$row = $dbc->getRow($prep,array());
        if ($row === False) {
            return 'error';
        }

        //$today = new DateTime(date('Y-m-d'));
        $todayStr = date('Y-m-d').' 00:00:00';

        echo $todayStr;
        if ($row[0] !== date('Y-m-d')) {
            $updateQ = "INSERT INTO core_op.custdataHistory (CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`,
                                                memType, staff, SSI, Purchases, NumberOfChecks, memCoupons, blueLine, Shown, LastChange, histDate)
            SELECT CardNo, personNum, LastName, FirstName, CashBack, Balance, Discount, MemDiscountLimit, ChargeLimit, ChargeOk, WriteChecks, StoreCoupons, `Type`, memType, staff, SSI, Purchases,
            NumberOfChecks, memCoupons, blueLine, Shown, LastChange, '{$todayStr}' FROM core_op.custdata WHERE personNum = 1";
            $prep = $dbc->prepare($updateQ);
            $row = $dbc->execute($prep,array());
        }
	}
    //(select custdata20231031.*, '2023-10-31 00:00:00' as histDate from core_bak.custdata20231031;
//select custdata23111.*, '2023-10-31 00:00:00' as histDate from core_bak.custdata23111;
//select custdata231201.*, '2023-10-31 00:00:00' as histDate from core_bak.custdata231201;)
}