<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

class FCC_DiscountChangeTask extends FannieTask
{
	public $name = 'Changes memType';
	public $description = 'Run on the first of the month.';

	function run(){
		$this->updateCustdata();
	}

	function updateCustdata(){
		$OpDB = $this->config->get('OP_DB');
		$dbc = FannieDB::get($OpDB);
        

		$month = new DateTime('now');
		$month->modify('-1 day')->setTime(0,0,0);
		$month->modify('first day of this month');
		$date = $month->format('Y-m-d');

        $dbc->selectDB($this->config->get('OP_DB'));

        $updateQ = $dbc->prepare('UPDATE custdata d 
                                  LEFT JOIN FCC_MonthlyDiscountChanges c ON d.cardNo = c.card_no
                                  LEFT JOIN memType t ON c.newMemType = t.memtype
                                  SET d.memType = c.newMemType, d.Discount = t.discount, d.Type = t.custdataType, d.staff = t.staff, d.SSI = t.SSI
                                  WHERE d.memType != c.newMemType AND c.month = ?');
        $updateR = $dbc->execute($updateQ,array($date));

        if($updateR) {
        	
        } else {

        }

	}
}