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
		$this->calcualtePayments();
	}

	function calcualtePayments(){
		global $FANNIE_OP_DB;
		$TransDB = $this->config->get('TRANS_DB');
		$OpDB = $FANNIE_OP_DB;
		$dbc = FannieDB::get($TransDB);
		$query = "select e.card_no, e.payments, d.start_date, e.mostRecent, c.LastName, c.FirstName, c.memType,c.blueLine,c.id
					from ".$TransDB.".equity_history_sum e
					left join ".$OpDB.".custdata c on e.card_no=c.CardNo 
					left join ".$OpDB.".memDates d on e.card_no=d.card_no where e.card_no < 8000";
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
			$memberLevel = $row['memType'];
			if ($yearAmt > 0 && $monthAmt>0){ 
				if ($memberLevel == 1) { $memberLevel = 0; }
				$blueLine .= $monthAmt.'/'.$yearAmt.'/'.$remainAmt;
			} else {
				if ($memberLevel == 0) { $memberLevel = 1; }
				/*switch ($row['memType']) {
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
				}*/
			}
			if ($blueLine != $row['blueLine']) {
				$opDBC = FannieDB::get($OpDB);
				$updateQ = 'UPDATE '.$OpDB.'.custdata c set blueLine="'.$blueLine.'",memType ='.$memberLevel.' where c.CardNo='.$row['card_no'].' AND c.id='.$row['id'];
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,array());
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$yearAmt);
			}
		}

	}
}