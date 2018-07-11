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


    				$paid = 175;
			$now = new DateTime('2020-12-23');
$now->modify('first day of this month')->setTime(23,59,59);;
			$remain = 175 - $paid;
			$startDate = new DateTime('2018-12-15');
$startDate->modify('first day of this month')->setTime(0,0,0);;
//$startDate->modify('-1 day');
			$lastDate = new DateTime('1995-03-01');
			$interval = $now->diff($startDate);
			$months = $interval->m;
			$years = $interval->y;
			$yearAmt = 0;
			if ($years >= 6) {$yearAmt = 175 - $paid;}
			else if ($years == 0) {$yearAmt = 50 - $paid;}
			else if ($paid < 50+25*$years){$yearAmt = (50+25*$years) -$paid;}
	
			$monthAmt = 0;
			if($years == 0 && $months <5){$monthAmt = 10*($months+1)-$paid;}
			else if ($yearAmt >0 && $months <5) {$monthAmt = $yearAmt + 5*($months+1) -25;}
			else {$monthAmt = $yearAmt;}

			select e.card_no, e.payments, e.startdate, c.FirstName, c.LastName, c.discount
from core_trans.equity_history_sum e 
left join core_op.CustomerAccounts a on e.card_no = a.CardNo 
left join core_op.custdata c on e.card_no = c.CardNo

*********************************************************************************/

class FCC_EquityPaymentDueTask extends FannieTask
{
	public $name = 'FCC Equity Payment Due Task';
	public $description = 'FCC Payments are 50 dollars for the first year and then 25 until 175 is reached.
							Where we go crazy is a member can choose to pay 10 or 5 dollars for 5 mounths
							until any monthly payment is reached. This task calcualtes what a member owes.';

	function run(){
		$this->calcualtePayments();
	}

	function calcualtePayments(){
		global $FANNIE_OP_DB;
		$TransDB = $this->config->get('TRANS_DB');
		$OpDB = $FANNIE_OP_DB;
		$dbc = FannieDB::get($TransDB);
		$query = "select e.card_no, e.payments, e.startdate, e.mostRecent, c.LastName, c.FirstName, c.memType,c.blueLine,c.id
					from ".$TransDB.".equity_history_sum e
					left join ".$OpDB.".custdata c on e.card_no=c.CardNo where e.card_no < 8000";
		$prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
		$blueLines = array();
		//Setting the times on these date times is important otherwise we get super wonky intervals.
		$now = new DateTime();
		$now->modify('first day of this month')->setTime(23,59,59);
		while ($row = $dbc->fetch_row($results)) {
			$paid = $row['payments'];
			$remainAmt = 175 - $paid;
			$startDate = new DateTime($row['startdate']);
			$startDate->modify('first day of this month')->setTime(0,0,0);
			$interval = $now->diff($startDate);

			$months = $interval->m;
			$years = $interval->y;
			$yearAmt = 0;
			// calculate how much is owed for the year
			if ($years >= 6) {$yearAmt = 175 - $paid;} //full term should be paid off but if not a simple differnce.
			else if ($years == 0) {$yearAmt = 50 - $paid;} //first year for 50 dollars also a simple diffrence.
			else if ($paid < 50+25*$years){$yearAmt = (50+25*$years) -$paid;} //this is an if so that it will be zero
																			  //if the years balance is paid instead of a negative.
			// calculate how much is owed for the month
			$monthAmt = 0;
			if($years == 0 && $months <5){$monthAmt = 10*($months+1)-$paid;}//first year monthly payment
			else if ($yearAmt >0 && $months <5) {$monthAmt = $yearAmt + 5*($months+1) -25;}//any other year
			else {$monthAmt = $yearAmt;}//doesn't increment after month 5 so it's the same as for the year.

			$blueLine = $row['card_no'].' '.substr($row['FirstName'], 0, 1).'. '.$row['LastName'].' ';
			if ($yearAmt > 0){ 
				$blueLine .= $monthAmt.'/'.$yearAmt.'/'.$remainAmt;
			} /*else {
				switch ($row['memType']) {
					case 1:
						$blueLine .= 'Member';
						break;
					case 5:
						$blueLine .= '15%';
						break;
					case 6:
						$blueLine .= '10%';
						break;
					case 7:
						$blueLine .= 'Staff';
						break;
					case 8:
						$blueLine .= 'Member Staff';
						break;
					case 9:
						$blueLine .= '23%';
						break;
					default:
						$blueLine .= 'Member';
						break;
				}
			}*/
			if ($blueLine != $row['blueLine']) {
				$opDBC = FannieDB::get($OpDB);
				$updateQ = 'UPDATE '.$OpDB.'.custdata c set blueLine="'.$blueLine.'" where c.CardNo='.$row['card_no'].' AND c.id='.$row['id'];
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,array());
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$yearAmt);
			}
		}

	}
}