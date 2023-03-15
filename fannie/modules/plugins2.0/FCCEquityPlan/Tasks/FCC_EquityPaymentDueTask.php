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
		$query = "SELECT e.card_no, e.payments,d.start_date, 
				(case when s.lastPaymentDate is null then d.start_date else s.lastPaymentDate end) as mostRecent,
				 c.LastName, c.FirstName, c.memType,c.blueLine,c.id,p.equityPaymentPlanID, p.nextPaymentAmount,c.`Type`
				from {$TransDB}.equity_history_sum e
				left join {$OpDB}.custdata c on e.card_no=c.CardNo AND c.PersonNum = 1
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
				where e.card_no between 10 and 99998 AND (e.payments < 175 OR c.blueLine LIKE '%/%') and c.memType NOT IN (11) and c.cardNo != 8001";
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
			$years = $interval->y;
			$months = $interval->m;
			$days = $interval->d;
			$months = $years*12+$months; //It wraps around so we need to track if it's been more than a year;
			$blueLine = $row['blueLine'];
			$newLine = '';
			$memType = $row['memType'];
			$type = $row['Type'];
			$paid = $row['payments'];
			$paymentDue = 3*$months;
			//this will make the payment due correct nomater what had been paid before, also sets it to zero if 175 is reached.
			if (($paid + $paymentDue) >= 175 ) {
				$paymentDue = $paymentDue - (($paid+$paymentDue) - 175);
			}
			
			$updateAccount = false;
			if($months >= 1 && $row['equityPaymentPlanID'] == 1 && $row['payments'] < 175 && $memType != 0){
				$remainAmt = 175 - $paid;
				$newLine = sprintf("%s %s. %s %d/%d",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName'],$remainAmt,$paymentDue); //$row['card_no'].' '.substr($row['FirstName'], 0, 1).'. '.$row['LastName'].' '.$remainAmt.'/'.$paymentDue;
			} else if($memType == 0) {
				$newLine = sprintf("%s %s. %s %d/%d",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName'],175,3);
			} else {
				$newLine = sprintf("%s %s. %s",$row['card_no'],substr($row['FirstName'], 0, 1),$row['LastName']);
			}
			// update account blueline if information has changed.
			if ($blueLine != $newLine) {
				$blueLine = $newLine;
				$updateAccount = true;
			}
			// reactivate closed accounts if they have paid any money.
			if ($memType == 0 && $paid > 0) {
				echo $this->cronMsg("REACTIVATION \n Blue Line: ".$blueLine.'  mostRecent:'.$row['mostRecent']. "   Now:".$now->format('Y-m-d')."  Months/Days:".$months.'/'.$days);
				$memType = 12; //set to inactive, the next if will reactivate if payments are upto date.
				$type ='REG';
				$updateAccount = true;
			}
			// check for member deactivation
			if ($memType == 1 && $months > 2 && $paymentDue > 6) {
				//deactivate member.
				echo $this->cronMsg("SET STANDING BAD:\nBlue Line: ".$blueLine.'  mostRecent:'.$row['mostRecent']. "   Now:".$now->format('Y-m-d')."  Months/Days:".$months.'/'.$days);
				$memType = 12;
				$type = 'REG';
				$updateAccount = true;
			} else if ($memType == 12 && $paymentDue <= 6) {
				//ractivate member.
				echo $this->cronMsg("SET STANDING GOOD:\nBlue Line: ".$blueLine.'  mostRecent:'.$row['mostRecent']. "   Now:".$now->format('Y-m-d')."  Months/Days:".$months.'/'.$days);
				$memType = 1;
				$type = 'PC';
				$updateAccount = true;
			}
			//$updateAccount = true;
			if ($updateAccount) {
				$memNo = $row['card_no'];
				$opDBC = FannieDB::get($OpDB);
				$args = array($blueLine,$memType,$type,$row['card_no']);
				$updateQ = "UPDATE {$OpDB}.custdata c 
							SET blueLine=? ,memType = ?, `type` = ?
							WHERE c.CardNo=?";
				$updateP = $opDBC->prepare($updateQ);
				$updateR = $opDBC->execute($updateP,$args);
				echo $this->cronMsg("Blue Line: ".$blueLine.'  '.$newLine.' start_date. '.$row['mostRecent']);
				
				/*****
				Update EquityPaymentPlans Table;
				*****/
				//find the next payment date
				$mostRecentDate->modify('next month');
				$nextPaymentDate = $mostRecentDate->format('Y-m-d').' 00:00:00';
				
				//find the last payment using the StockpurchasesModel model doesn't work so I am
				//writing the sql.
				$lastQ = "SELECT card_no, SUM(stockPurchase) as stockPurchase, MAX(tdate) as tdate
						  FROM {$TransDB}.stockpurchases 
						  WHERE card_no = ? AND tdate = ?
						  GROUP BY card_no
						";
				$lastP = $dbc->prepare($lastQ);
				$lastR = $dbc->execute($lastP, array($memNo,$row['mostRecent']));
				$lastPayment = $dbc->fetch_row($results);

				$lastPaymentDate = null;
				$lastPaymentAmount = 0;
				if ($lastPayment) {
					//$lastPayment = $objs[0];
					$lastPaymentDate = $lastPayment[2];
					$lastPaymentAmount = $lastPayment[1];
				}

				$plan = new EquityPaymentPlanAccountsModel($opDBC);
				$plan->cardNo($memNo,'=');

				if (!$plan->find()) {
					//if plan is new
					$plan->cardNo($memNo);
					$plan->equityPaymentPlanID(1);
					$plan->lastPaymentDate($lastPaymentDate);
					$plan->lastPaymentAmount($lastPaymentAmount);
					$plan->nextPaymentDate($nextPaymentDate);
					$plan->nextPaymentAmount($paymentDue);
					$saved = $plan->save();
				}else{
					$objs = $plan->find();
					$ojb = $objs[0];
					$ojb->lastPaymentDate($lastPaymentDate);
					$ojb->lastPaymentAmount($lastPaymentAmount);
					$ojb->nextPaymentDate($nextPaymentDate);
					$ojb->nextPaymentAmount($paymentDue);
					$saved = $ojb->save();
				}
				// update the plan

				

			}
		}//end while loop
	}

}