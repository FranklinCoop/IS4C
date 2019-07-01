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

	function calcualtePayments() {
		global $FANNIE_OP_DB;
		$TransDB = $this->config->get('TRANS_DB');
		$OpDB = $FANNIE_OP_DB;
		$dbc = FannieDB::get($TransDB);
		$query = "select e.card_no, e.payments,d.start_date, (case when p.nextPaymentDate is null then e.mostRecent else p.nextPaymentDate end) as mostRecent, c.LastName, c.FirstName, c.memType,c.blueLine,c.id,p.equityPaymentPlanID, p.nextPaymentAmount
					from ".$TransDB.".equity_history_sum e
					left join ".$OpDB.".custdata c on e.card_no=c.CardNo 
					left join ".$OpDB.".memDates d on e.card_no=d.card_no 
					left join ".$OpDB.".EquityPaymentPlanAccounts p on e.card_no=p.cardNo 
					where e.card_no < 8000 AND e.payments < 175";
		$prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
		$blueLines = array();
		//Setting the times on these date times is important otherwise we get super wonky intervals.
		$now = new DateTime();
		$now->modify('first day of this month')->setTime(23,59,59);
		while ($row = $dbc->fetch_row($results)) {
			$mostRecentDate = new DateTime($row['mostRecent']);
			$mostRecentDate->modify('first day of this month')->setTime(0,0,0);
			$interval = $now->diff($mostRecentDate);
			$months = $interval->m;
			$days = $interval->d;
			$blueLine = $row['blueLine'];
			$memType = $row['memType'];
			$paid = $row['payments'];
			$paymentDue = $row['nextPaymentAmount'];
			if($days==0 || $paymentDue > 3){
				$remainAmt = 175 - $paid;
				$blueLine = sprintf("%s %s. %s %d/%d",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName'],$remainAmt,$paymentDue); //$row['card_no'].' '.substr($row['FirstName'], 0, 1).'. '.$row['LastName'].' '.$remainAmt.'/'.$paymentDue;
				if ($days >= 60 && $memType == 1) {
					//deactivate member.
					$memType = 12;
				} else if ($memType == 12 && $days < 60) {
					$memType = 1;
				}
			} elseif ($paymentDue == 3.00) {
				$blueLine = sprintf("%s %s. %s",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName']);
			}

			if ($row['nextPaymentAmount'] > 0) {
				$opDBC = FannieDB::get($OpDB);
				$updateQ = 'UPDATE '.$OpDB.'.custdata c set blueLine="'.$blueLine.'",memType ='.$memType.' where c.CardNo='.$row['card_no'].' AND c.id='.$row['id'];
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,array());
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$paid.' start_date. '.$days);
			}
		}
	}

}