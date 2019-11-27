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
		$query = "SELECT e.card_no, e.payments,d.start_date, (case when s.lastPaymentDate is null then d.start_date else s.lastPaymentDate end) as mostRecent, c.LastName, c.FirstName, c.memType,c.blueLine,c.id,p.equityPaymentPlanID, p.nextPaymentAmount
				from {$TransDB}.equity_history_sum e
				left join {$OpDB}.custdata c on e.card_no=c.CardNo 
				left join {$OpDB}.memDates d on e.card_no=d.card_no 
				left join {$OpDB}.EquityPaymentPlanAccounts p on e.card_no=p.cardNo
                left join (
                    SELECT a.card_no, SUM(a.stockPurchase) as total, MAX(a.tdate)  as lastPaymentDate
					FROM {$TransDB}.stockpurchases AS a
					JOIN (
						SELECT card_no, SUM(stockPurchase), MAX(tdate) as max_tdate, sum(stockPurchase) as total
						FROM {$TransDB}.stockpurchases
						GROUP BY card_no
					) as b
					WHERE a.card_no = b.card_no
    				AND a.tdate = b.max_tdate
    				GROUP BY a.card_no) s on c.cardNo = s.card_no
				where e.card_no between 10 and 8000 AND (e.payments < 175 OR c.blueLine LIKE '%/%')";
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
			$type = 'PC';
			$paid = $row['payments'];
			$paymentDue = 3*$months;
			//this will make the payment due correct nomater what had been paid before, also sets it to zero if 175 is reached.
			if (($paid + $paymentDue) >= 175 ) {
				$paymentDue = $paymentDue - (($paid+$paymentDue) - 175);
			}
			
			$updateAccount = false;
			if($months >= 1 && $row['equityPaymentPlanID'] == 1 && $row['payments'] < 175){
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
			if ($memType == 1 && $months > 2 && $paymentDue > 6) {
				//deactivate member.
				echo $this->cronMsg("Blue Line: ".$blueLine.'  mostRecent:'.$row['mostRecent']. "   Now:".$now->format('Y-m-d')."  Months/Days:".$months.'/'.$days);
				$memType = 12;
				$type = 'REG';
				$updateAccount = true;
			} else if ($memType == 12 && $paymentDue <= 6) {
				//ractivate member.
				echo $this->cronMsg("Blue Line: ".$blueLine.'  mostRecent:'.$row['mostRecent']. "   Now:".$now->format('Y-m-d')."  Months/Days:".$months.'/'.$days);
				$memType = 1;
				$type = 'PC';
				$updateAccount = true;
			}
			//$updateAccount = true;
			if ($updateAccount) {
				$opDBC = FannieDB::get($OpDB);
				$args = array($blueLine,$memType,$type,$row['card_no']);
				$updateQ = "UPDATE {$OpDB}.custdata c 
							SET blueLine=? ,memType = ?, `type` = ?
							WHERE c.CardNo=?";
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,$args);
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$newLine.' start_date. '.$row['mostRecent']);
			}
		}
	}

}