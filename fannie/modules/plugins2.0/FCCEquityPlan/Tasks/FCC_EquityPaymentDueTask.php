<?php
/*******************************************************************************

    Copyright 2019 Franklin Community Co-op

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

class FCC_EquityPaymentDueTask extends FannieTask
{
	public $name = 'FCC Equity Payment Due Task';
	public $description = 'FCC Payments are 50 dollars for the first year and then 25 until 175 is reached.
							Where we go crazy is a member can choose to pay 10 or 5 dollars for 5 mounths
							until any monthly payment is reached. This task calcualtes what a member owes.';

	function run(){
		$this->updateBlueLine();
	}

	function updateBlueLine() {
		global $FANNIE_OP_DB;
		$TransDB = $this->config->get('TRANS_DB');
		$OpDB = $FANNIE_OP_DB;
		$dbc = FannieDB::get($TransDB);
		$query = "select e.card_no, e.payments,d.start_date, s.lastPaymentDate as mostRecent, c.LastName, c.FirstName, c.memType,c.blueLine,c.id,p.equityPaymentPlanID, p.nextPaymentAmount
				from {$TransDB}.equity_history_sum e
				left join {$OpDB}.custdata c on e.card_no=c.CardNo 
				left join {$OpDB}.memDates d on e.card_no=d.card_no 
				left join {$OpDB}.EquityPaymentPlanAccounts p on e.card_no=p.cardNo
                left join (
                    SELECT a.card_no, a.stockPurchase, MAX(a.tdate)  as lastPaymentDate, b.total
					FROM {$TransDB}.stockpurchases AS a
					JOIN (
						SELECT card_no, stockPurchase, MAX(tdate) as max_tdate, sum(stockPurchase) as total
						FROM {$TransDB}.stockpurchases
						GROUP BY card_no
					) as b
					WHERE a.card_no = b.card_no
    				AND a.tdate = b.max_tdate
    				group by a.card_no) s on c.cardNo = s.card_no
				where e.card_no between 10 and 8000 AND e.payments < 175";
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
			$newLine = '';
			$memType = $row['memType'];
			$paid = $row['payments'];
			$paymentDue = 3*$months;
			$updateAccount = false;
			if($months >= 1 && $row['equityPaymentPlanID'] == 1){
				$remainAmt = 175 - $paid;
				$newLine = sprintf("%s %s. %s %d/%d",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName'],$remainAmt,$paymentDue); //$row['card_no'].' '.substr($row['FirstName'], 0, 1).'. '.$row['LastName'].' '.$remainAmt.'/'.$paymentDue;
			} else {
				$newLine = sprintf("%s %s. %s",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName']);
			}
			// update account blueline if information has changed.
			if ($blueLine != $newLine) {
				$blueLine = $newLine;
				$updateAccount = true;
			}
			// check for member deactivation
			if ($days >= 60 && $memType == 1) {
				//deactivate member.
				$memType = 12;
			} else if ($memType == 12 && $days < 60) {
				//ractivate member.
				$memType = 1;
			}
			//$updateAccount = true;
			if ($updateAccount) {
				$opDBC = FannieDB::get($OpDB);
				$updateQ = 'UPDATE '.$OpDB.'.custdata c set blueLine="'.$blueLine.'",memType ='.$memType.' where c.CardNo='.$row['card_no'].' AND c.id='.$row['id'];
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,array());
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$newLine.' start_date. '.$row['mostRecent']);
			}
		}
	}

}