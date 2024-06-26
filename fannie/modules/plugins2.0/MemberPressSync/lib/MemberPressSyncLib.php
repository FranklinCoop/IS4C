<?php
/*******************************************************************************

    Copyright 2023 Franklin Community co-op

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

class MemberPressSyncLib {
#
#   Mapping functions to track what member press id's got to what core accounts.
#    
    /**
     * Maps a CORE cardNo with a MemberPress ID.
     */
    public static function mapMember($connection, $mapInfo) 
    {//$memberPressID, $cardNo,$lastPullDate,$lastPushDate, $origin) {
        //$mapID = MemberPressSyncLib::getMemberMapID($connection, $memberPressID, $cardNo);
        $mapQ = 'INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, lastPullDate, lastPushDate, origin)
            VALUES(?,?,?,?,?) as mapInfo
            ON DUPLICATE KEY UPDATE lastPullDate = mapInfo.lastPullDate, lastPushDate = mapInfo.lastPushDate, origin = mapInfo.origin';
        $prep = $connection->prepare($mapQ);
        $row = $connection->execute($prep, $mapInfo);
        if($row === false) {
            return false;
        }
        return true;
    }
    /**
     * Map a transaction so we konw if it has been synced
     */
    public static function mapTrans($connection, $transInfo) {
        $mapQ = "INSERT INTO core_op.MemberPressTransactionMap (mp_trans_num, core_trans_num, lastSyncDate, origin, mpID, cardNo, mpMemberID)
                 VALUES(?,?,?,?,?,?,?) as transInfo
                 ON DUPLICATE KEY UPDATE lastSyncDate = transInfo.lastSyncDate"; 
        $prep = $connection->prepare($mapQ);
        $row = $connection->execute($prep, $transInfo);
        if($row === false) {
            return false;
        }
        return true;
    }
    
    /**
     * check if the member has been mapped before, if it has it has been synced before.
     */
    public static function isMapped($connection, $memberPressID,$cardNo) {
        $memberPressIDStr = ($memberPressID ==1) ? '1 = ?' : 'memberPressID = ?' ;
        $cardNoStr = ($cardNo ==1) ? '1 = ?' : 'cardNo = ?';
        $mapQ = "SELECT cardNo FROM core_op.MemberPressMemberMap WHERE {$cardNoStr} AND {$memberPressIDStr}";
        $prep = $connection->prepare($mapQ);
        $row = $connection->getRow($prep, array($cardNo, $memberPressID));
        if($row === false) {
            #echo "false!".$mapQ.", ".$cardNo.", ".$memberPressID."\n";
            return false;
        }
        #echo "TRUE!\n";
        return true;
    }
    /**
     * check if the transaction has been synced before.
     */
    public static function transIsMapped($connection, $transNoMemberPress, $transNoCORE) {
        $core_trans_num = ($transNoCORE ==1) ? '1 = ?' : 'core_trans_num = ?' ;
        $mp_trans_num = ($transNoMemberPress ==1) ? '1 = ?' : 'mp_trans_num = ?' ;
        $mapQ = "SELECT * FROM core_op.MemberPressTransactionMap WHERE {$mp_trans_num} AND {$core_trans_num}";
        #echo "URL:".$mapQ."\n";
        $prep = $connection->prepare($mapQ);
        $row = $connection->getRow($prep, array($transNoMemberPress,$transNoCORE));
        if($row === false) {
            #echo $row."\n";
            return false;
        }
        #echo "TRUE!\n";
        return true;
    }
    /**
     * gets a single MP member or a list of mpMembers.
     */
    public static function retriveMPMembers($connection, $memberID, $cardNo) {
        $memberPressIDStr = ($memberID ==='') ? '' : "AND m.id = {$memberID}" ;
        $cardNoStr = ($cardNo ==='') ? '' : "AND m.cardNo = ?";

        $getQ = "SELECT * FROM core_op.MemberPressMembers AS mem
            LEFT JOIN core_op.MemberPressMemberMap AS map ON mem.id = map.memberPressID
            WHERE map.origin is null {$cardNoStr} {$memberPressIDStr}";
        $getP = $connection->prepare($getQ);
        $getR = $connection->execute($getP, array());
        $ret = array();
        while ($row = $connection->fetch_row($getR)) {
            $memberInfo = array('id' => $row[0], 'email' =>$row[1], 'username'=>$row[2], 'nicename'=>$row[3], 'url'=>$row[4], 'message'=>$row[5], 'registered_at'=>$row[6], 'first_name'=>$row[7], 'last_name'=>$row[8], 'display_name'=>$row[9], 'active_memberships'=>$row[10], 
            'active_txn_count'=>$row[11],'expired_txn_count'=>$row[12], 'trial_txn_count'=>$row[13], 'sub_count'=>$row[14], 'login_count'=>$row[15], 'first_txn'=>$row[16], 'latest_txn'=>$row[17], 'mepr-address-one'=>$row[18], 'mepr-address-two'=>$row[19], 'mepr-address-city'=>$row[20],
            'mepr-address-state'=>$row[21], 'mepr-address-zip'=>$row[22], 'mepr-address-country'=>$row[23], 'mepr_phone'=>$row[24], 'mepr_email'=>$row[25], 'mepr_how_would_you_like_to_receive_information_about_the_co_op'=>$row[26], 
            'mepr_adult_one'=>$row[27], 'mepr_adult_two'=>$row[28], 'mepr_adult_three'=>$row[29],'recent_transactions'=>$row[30], 'recent_subscriptions'=>$row[31], 'origin'=>$row[32], 'cardNo'=>$row[33]);
            $ret[] = $memberInfo;
        }
        return $ret;
    }

        /**
     * gets a single MP member or a list of mpMembers.
     */
    public static function retriveMPTransactions($connection) {
        //$memberPressIDStr = ($memberID ==='') ? '' : "AND m.id = {$memberID}" ;
        //$cardNoStr = ($cardNo ==='') ? '' : "AND m.cardNo = ?";

        $getQ = "SELECT * FROM core_op.MemberPressTransactions t
            LEFT JOIN core_op.MemberPressTransactionMap m on t.id = m.mpID
            WHERE lastSyncDate IS NULL";
        $getP = $connection->prepare($getQ);
        $getR = $connection->execute($getP, array());
        $ret = array();
        while ($row = $connection->fetch_row($getR)) {
            $transInfo = array(
                'membership' => $row[0], 
                'member'=>$row[1], 
                'coupon'=>$row[2], 
                'subscription'=>$row[3], 
                'id'=>$row[4], 
                'amount'=>$row[5], 
                'total'=>$row[6], 
                'tax_amount'=>$row[7], 
                'tax_reversal_amount' =>$row[8], 
                'tax_rate'=>$row[9],
                'tax_desc'=>$row[10], 
                'tax_class'=>$row[11], 
                'trans_num'=>$row[12], 
                'status'=>$row[13],
                'txn_type'=>$row[14], 
                'gateway'=>$row[15], 
                'prorated'=>$row[16], 
                'created_at'=>$row[17], 
                'expires_at'=>$row[18], 
                'corporate_account_id'=>$row[19],
                'parent_transaction_id'=>$row[20], 
                'order_id'=>$row[21], 
                'tax_compound'=>$row[22], 
                'tax_shipping'=>$row[23], 
                'response'=>$row[24], 
                'rebill'=>$row[25],
                'subscription_payment_index'=>$row[26], 
                'core_trans_num'=>$row[27],
                'origin'=>$row[28]);
    
            $ret[] = $transInfo;
        }
        return $ret;
    }

    public static function getCardNo($connection, $mpMemberID) {
        $mapQ = "SELECT cardNo FROM core_op.MemberPressMemberMap WHERE memberPressID = {$mpMemberID} ";
        $prep = $connection->prepare($mapQ);
        $row = $connection->getRow($prep, array());
        if($row === false) {
            #echo "false!".$mapQ.", ".$cardNo.", ".$memberPressID."\n";
            return 0;
        }
        #echo "TRUE!\n";
        return $row[0];
    }
    //$config = FannieConfig::factory();

    /**
     * Looks up the member press membership ID by Core card number.
     */
    public static function paymentPlanToMemberShipID($connection, $cardNo) {
        $ret = 'gabagooo';
        $cardNoString = "cardNo = {$cardNo}";
        if (!$cardNo) {
            $cardNoString = "1=1";
        }
        $membershipIDQ = "SELECT mp_membership_id FROM core_op.MemberPressPaymentPlanMap 
        WHERE paymentPlanID in (SELECT equityPaymentPlanID FROM core_op.EquityPaymentPlanAccounts WHERE {$cardNoString})";
        $prep = $connection->prepare($membershipIDQ);
        $row = $connection->getRow($prep, array());
        if ($row === false) {
            $ret = 18924;
        } else {
            $ret = $row[0];
        }
        return $ret;
    }
    /**
     * get what a member's discount should be based on thier membeship type in member press.
     */
    public static function memberExistsMP($connection, $email) {
        $query = "SELECT `id` FROM core_op.MemberPressMembers WHERE email = '{$email}'";
        $prep = $connection->prepare($query);
        $row = $connection->getRow($prep, array());
        if ($row === false) {
            return False;
        }
        else {
            return $row[0];
        }
        return False;
    }

    /**
     * get what a member's discount should be based on thier membeship type in member press.
     */
    public static function memberShipID_to_memType($connection, $membershipID){
        $query = "SELECT paymentPlanID FROM core_op.MemberPressPaymentPlanMap WHERE mp_membership_id = {$membershipID}";
        $prep = $connection->prepare($query);
        $row = $connection->getRow($prep, array());
        if ($row === false) {
            return 1;
        }
        switch ($row[0]) {
            case 3: //FFA plan, return the ffa discount.
                return 6;
                break;
            case 2: //staff plan, return the staff discount.
                return 8;
            default: // otherwise return standard member.
                return 1;
                break;
        }
    }
        
    
    /**
     * is the payment plan mapped>
     */
    public static function paymentPlanIsMapped($connection, $mpPlan, $corePlan) {
        $ret = true;
        $core_plan = ($corePlan ==1) ? '1 = ?' : 'paymentPlanID = ?' ;
        $mp_plan = ($mpPlan ==1) ? '1 = ?' : 'mp_membership_id = ?' ;
        $membershipIDQ = "SELECT mp_membership_id FROM core_op.MemberPressPaymentPlanMap 
        WHERE {$core_plan} AND {$mp_plan}";
        $prep = $connection->prepare($membershipIDQ);
        $row = $connection->getRow($prep, array($corePlan, $mpPlan));
        if ($row === false) {
            $ret = false;
        } 
        return $ret;
    }

    /**
     * pull the active membershipID out of active_memberships
     */
    public static function getActiveMembershipID($active_memberships){
        $active_membershipID = '';
        if($active_memberships) {
            $active_membership = $active_memberships[0];
            if ($active_membership) {
                $active_membershipID = $active_membership['id'];
            }
        }
        return $active_membershipID;
    }

    public static function findTransactionsForRefund($connection, $cardNo, $tdate, $amount){
        $query = "SELECT p.trans_num, p.stockPurchase, m.mpID, tdate as created_at
            FROM core_trans.stockpurchases p 
            LEFT JOIN core_op.MemberPressTransactionMap m on CONCAT(p.card_no,'-',p.trans_num,'-',p.tdate) = m.core_trans_num
                WHERE p.card_no = ? and p.tdate < ? and p.stockPurchase > 0";
        $prep = $connection->prepare($query);
        $results = $connection->execute($prep, array($cardNo, $tdate));
        $returnTrans = array();
        $refundTotal = 0;
        while ($row = $connection->fetch_row($results)){
            if($refundTotal > $amount) {
                $refundTotal += -$row[1];
                $returnTrans[] = $row[2]; 
            }
        }
        return $returnTrans;
    }
    /**
SELECT * FROM core_trans.stockpurchases p
LEFT JOIN core_op.MemberPressMemberMap m on p.card_no = m.cardNo
LEFT JOIN core_op.MemberPressTransactions t on m.memberPressID = t.`member`
LEFT JOIN core_trans.equity_history_sum h ON p.card_no = h.card_no
WHERE p.tdate >= h.startdate AND p.stockPurchase > 0 AND m.memberPressID AND m.origin != 'IGNORE'
     */
    public static function getNewCoreTrans($connection) {
        $transQ = "SELECT CONCAT(p.card_no,'-',p.trans_num,'-',p.tdate) as trans_num, p.stockPurchase as amount, p.stockPurchase as total,
            0.00 as tax_amount, 0.00 as tax_rate, 'standard' as tax_desc, m.memberPressID as member, pm.mp_membership_id as membership,
            0 as coupon, 'complete' as status, 'manual' as gateway, 0 as subscription, p.tdate as created_at,
            CASE WHEN s.payments = 175.00 THEN '' ELSE DATE_ADD(p.tdate, INTERVAL 1 MONTH) END as expires_at
            FROM core_trans.stockpurchases p
            LEFT JOIN core_op.MemberPressMemberMap m on p.card_no = m.cardNo
            LEFT JOIN core_op.MemberPressTransactions t on m.memberPressID = t.`member`
            LEFT JOIN core_trans.equity_history_sum h ON p.card_no = h.card_no
            LEFT JOIN core_op.EquityPaymentPlanAccounts a ON p.card_no = a.cardNo
            LEFT JOIN core_op.MemberPressPaymentPlanMap pm ON a.equityPaymentPlanID = pm.paymentPlanID
            LEFT JOIN core_trans.equity_history_sum s ON p.card_no = s.card_no
            WHERE p.tdate >= h.startdate AND p.stockPurchase > 0 AND m.memberPressID AND m.origin != 'IGNORE'";
        $prep = $connection->prepare($transQ);
        $result = $connection->execute($prep, array());
        $ret = array();
        //$equityTotal =0;
        while ($row = $connection->fetch_row($result)) {
            $trans = array(
                'trans_num'=>'',
                'amount' => 0.00,
                'total' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.000,
                'tax_desc' => 'standard',
                'member' => 0, //required wordpress user id
                'membership' => 0, //the member ship id associated with this transaction
                'coupon' => 0, //the coupon id associated with this transaction.
                'status' => 'complete', //Can be "pending", "complete", "failed", or "refunded". Must be set to "complete" for the member to be considered active on the Membership.
                //'response' => '',
                'gateway' => 'manual',
                'subscription' => 0,
                'created_at' => 'YYYY-mm-dd hh:mm:ss',
                'expires_at' => '0000-00-00 00:00:00',
                'send_welcome_email' => false,
                'send_receipt_email' => false,
            );
            //$transKeys = array_keys($trans);
            foreach ($row as $key => $value) {
                //$index = $transKeys[$key];
                $trans[$key] = $value;
            }
            //echo "\n".var_dump($ret)."\n";
            $ret[] = $trans;
        }
        return $ret;
    }

    /**
    * Get a transaction from core formated to create in memberPress
    */
    public static function getCORETransForMember($connection, $cardNo, $activeMembershipID) {
        $transQ = "SELECT p.trans_num, p.stockPurchase, m.memberPressID, tdate AS created_at FROM core_trans.stockpurchases p
        LEFT JOIN core_op.MemberPressMemberMap m  ON p.card_no = m.cardNo
        LEFT JOIN core_trans.equity_history_sum h ON p.card_no = h.card_no
        WHERE p.card_no = ? AND p.tdate >= h.startdate AND p.stockPurchase > 0";
        $prep = $connection->prepare($transQ);
        $result = $connection->execute($prep, array($cardNo));
        $ret = array();
        //$equityTotal =0;
        while ($row = $connection->fetch_row($result)) {
            $trans = array(
                'trans_num'=>'',
                'amount' => 0.00,
                'total' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.000,
                'tax_desc' => 'standard',
                'member' => 0, //required wordpress user id
                'membership' => 0, //the member ship id associated with this transaction
                'coupon' => 0, //the coupon id associated with this transaction.
                'status' => 'complete', //Can be "pending", "complete", "failed", or "refunded". Must be set to "complete" for the member to be considered active on the Membership.
                //'response' => '',
                'gateway' => 'manual',
                'subscription' => 0,
                'created_at' => 'YYYY-mm-dd hh:mm:ss',
                'expires_at' => '0000-00-00 00:00:00',
                'send_welcome_email' => false,
                'send_receipt_email' => false,
            );
            //$transNo = $row[0];
            //if the trans_num is not uninque make it unique with member number and date.
            $trans_num  = $cardNo.'-'.$row[0].'-'.$row[3];
            if ($equityTotal <= 175.00) {
                $equityTotal += $row[1];
            }
            //if (!preg_match("\d{1,4}(?:-\d{1,4})(?:-\d{1,4})", $row[0])) {
                
            //}
            //$expires_at = date("Y-m-d", strtotime("+1 month", $row[3]));
            $expires_at = new DateTime($row[3]);
            $expires_at->modify('+1 month');
            $expDateStr = $expires_at->format('Y-m-d H:i:s');
            if ($equityTotal >= 175.00) {
                $expires_at = '';
            }

            $trans['trans_num'] = $trans_num;
            $trans['amount'] = $row[1];
            $trans['total'] = $row[1];
            $trans['member'] = $row[2];
            $trans['membership'] = $activeMembershipID;
            $trans['created_at'] = $row[3];
            $trans['expires_at'] = $expDateStr;
            $ret[] = $trans;
        }
        return $ret;
    }
    public static function getFirstCORETrans($connection, $cardNo, $activeMembershipID) {
        $transQ = "SELECT p.trans_num, p.stockPurchase, tdate AS created_at FROM core_trans.stockpurchases p
        LEFT JOIN core_trans.equity_history_sum h ON p.card_no = h.card_no
        WHERE p.card_no = ? AND p.tdate >= h.startdate AND p.stockPurchase > 0 LIMIT 1";
        $prep = $connection->prepare($transQ);
        $result = $connection->execute($prep, array($cardNo));
        $ret = array();

        while ($row = $connection->fetch_row($result)) {
            $trans = array(
                'trans_num'=>'',
                'amount' => 0.00,
                'total' => 0.00,
                'tax_amount' => 0.00,
                'tax_rate' => 0.000,
                'tax_desc' => 'standard',
                'member' => 0, //required wordpress user id
                'membership' => 0, //the member ship id associated with this transaction
                'coupon' => 0, //the coupon id associated with this transaction.
                'status' => 'complete', //Can be "pending", "complete", "failed", or "refunded". Must be set to "complete" for the member to be considered active on the Membership.
                //'response' => '',
                'gateway' => 'manual',
                'subscription' => 0,
                'created_at' => 'YYYY-mm-dd hh:mm:ss',
                'expires_at' => '0000-00-00 00:00:00',
                'send_welcome_email' => false,
                'send_receipt_email' => false,
            );
            //$transNo = $row[0];
            //if the trans_num is not uninque make it unique with member number and date.
            //if (!preg_match("\d{1,4}(?:-\d{1,4})(?:-\d{1,4})", $row[0])) {
                $trans_num  = $cardNo.'-'.$row[0].'-'.$row[2];
            //}
            //$expires_at = date("Y-m-d", strtotime("+1 month", $row[3]));
            $expires_at = new DateTime($row[2]);
            $expires_at->modify('+1 month');

            $trans['trans_num'] = $trans_num;
            $trans['amount'] = $row[1];
            $trans['total'] = $row[1];
            $trans['member'] = '';
            $trans['membership'] = $activeMembershipID;
            $trans['created_at'] = $row[2];
            $trans['expires_at'] = $expires_at->format('Y-m-d H:i:s');
            $ret = $trans;
        }
        return $ret;
    }
    /**
     * associate unmaped member press transactions with thier core counterparts if they have been added.
     */
    public static function getCOREMemberTransaction($connection, $cardNo, $transDate) {
        $ret = false;
        $transQ = "SELECT CONCAT(card_no, '-', emp_no, '-', register_no, '-', trans_no, '-', `datetime`) as trans_num
            FROM core_trans.transarchive WHERE card_no = ? AND department = 992 AND `datetime` BETWEEN ? AND ? + INTERVAL 2 WEEK";
        $prep = $connection->prepare($transQ);
        $row = $connection->getRow($prep, array($cardNo, $transDate, $transDate));
        if($row === false) {
            return $ret;
        }
        $ret = $row[0];
        return $ret;
    }

        /**
     * adds a raw member press transaction to core
     */
    public static function addMPTransaction($connection, $transInfo) {
        $addQ = "INSERT INTO core_op.MemberPressTransactions (membership, member, coupon, subscription, id, amount, total, tax_amount, tax_reversal_amount, tax_rate,tax_desc, tax_class, trans_num, `status`,
            txn_type, gateway, prorated, created_at, expires_at, corporate_account_id,parent_transaction_id, order_id, tax_compound, tax_shipping, response, rebill, subscription_payment_index, core_trans_num, origin)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) as newTrans
            ON DUPLICATE KEY UPDATE core_trans_num = newTrans.core_trans_num, origin = newTrans.origin";
        $prep = $connection->prepare($addQ);
        $row = $connection->execute($prep, $transInfo);
        if($row === false) {
            return false;
        }
        return true;
    }

    /**
     * adds a raw member press member to core
     */
    public static function addMPMember($connection, $memberInfo) {
        $addQ = "INSERT INTO core_op.MemberPressMembers (id, email, username, nicename, `url`, `message`, registered_at, first_name, last_name, display_name, active_memberships, active_txn_count,
            expired_txn_count, trial_txn_count, sub_count, login_count, first_txn, latest_txn, `mepr-address-one`, `mepr-address-two`, `mepr-address-city`, 
            `mepr-address-state`, `mepr-address-zip`, `mepr-address-country`, mepr_phone, mepr_email, mepr_how_would_you_like_to_receive_information_about_the_co_op, 
            mepr_adult_one, mepr_adult_two, mepr_adult_three,recent_transactions, recent_subscriptions, origin, cardNo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) as memInfo
            ON DUPLICATE KEY UPDATE email = memInfo.email, nicename = memInfo.nicename, first_name = memInfo.first_name, last_name = memInfo.last_name, display_name = memInfo.display_name,
            active_memberships = memInfo.active_memberships, active_txn_count = memInfo.active_txn_count, expired_txn_count =memInfo.expired_txn_count, trial_txn_count = memInfo.trial_txn_count,
            sub_count = memInfo.sub_count, login_count = memInfo.login_count, first_txn = memInfo.first_txn, latest_txn = memInfo.latest_txn, `mepr-address-one` = memInfo.`mepr-address-one`,
            `mepr-address-two` = memInfo.`mepr-address-two`, `mepr-address-city` = memInfo.`mepr-address-city`, `mepr-address-state` = memInfo.`mepr-address-state`, 
            `mepr-address-zip` = memInfo.`mepr-address-zip`, `mepr-address-country` = memInfo.`mepr-address-country`, mepr_phone = memInfo.mepr_phone, mepr_email = memInfo.mepr_email, 
            mepr_how_would_you_like_to_receive_information_about_the_co_op = memInfo.mepr_how_would_you_like_to_receive_information_about_the_co_op, 
            mepr_adult_one = memInfo.mepr_adult_one , mepr_adult_two = memInfo.mepr_adult_two, mepr_adult_three = memInfo.mepr_adult_three, origin = memInfo.origin, cardNo = memInfo.cardNo";
        $prep = $connection->prepare($addQ);
        $result = $connection->execute($prep, $memberInfo);
        if($result === false) {
            return false;
        }
        return true;
    }


    /**
     * create a member number for new memberpress members.
     */
    public static function createCOREMember($connection, $memberInfo) {
        
        $newCardNo = MemberPressSyncLib::getFirstUnusedCardNo($connection);
        $memberShipID = MemberPressSyncLib::getActiveMembershipID($memberInfo['active_memberships']);
        $memType = 12;
        if (!is_null($memberShipID) && $memberShipID != 0) {
            $memType = MemberPressSyncLib::memberShipID_to_memType($connection, $memberShipID);
        }
        $discount = MemberPressSyncLib::getDiscount($connection, $memType);
        $person_num = 1;
        $custData = new CustdataModel($connection);
        $custData->personNum($person_num);
        $custData->LastName($memberInfo['last_name']);
        $custData->FirstName($memberInfo['first_name']);
        $custData->CashBack(999.99);
        $custData->Balance(0);
        $custData->memCoupons(0);
        $custData->Discount($discount);
        $custData->Type('PC');
        $custData->staff(0);
        $custData->SSI(0);
        $custData->memType($memType);
        $custData->CardNo($newCardNo);
        $custData->blueLine($newCardNo.' '.$memberInfo['last_name']);
        $saved = $custData->save();
        $person_num++;
        
        
        //$profile = $memberInfo['profile'];
        $otherAdults = array();
        if (!is_null($memberInfo['mepr_adult_one']) && $memberInfo['mepr_adult_one'] != '') {
            $otherAdults[] = $memberInfo['mepr_adult_one'];
        }
        if (!is_null($memberInfo['mepr_adult_two'])  && $memberInfo['mepr_adult_two'] != '') {
            $otherAdults[] = $memberInfo['mepr_adult_two'];
        }
        if (!is_null($memberInfo['mepr_adult_three']) && $memberInfo['mepr_adult_three'] != '') {
            $otherAdults[] = $memberInfo['mepr_adult_three'];
        }
        foreach ($otherAdults as $adult) {
            $names = explode(' ',$adult);
            $first_name ='';
            $last_name = '';
            for ($i=0; $i <  count($names)-1; $i++) { 
                 if($i ===0 ){
                    $first_name = $names[$i];
                 } else {
                    $last_name .= $names[$i];
                 }
            }
            $custData = new CustdataModel($connection);
            $custData->personNum($person_num);
            $custData->LastName($last_name);
            $custData->FirstName($first_name);
            $custData->CashBack(999.99);
            $custData->Balance(0);
            $custData->memCoupons(0);
            $custData->Discount($discount);
            $custData->Type('PC');
            $custData->staff(0);
            $custData->SSI(0);
            $custData->memType($memType);
            $custData->CardNo($newCardNo);
            $custData->blueLine($newCardNo.' '.$memberInfo['last_name']);
            $custData->save();
            $person_num++;
        }
        /*            $memberInfo = array('id' => $row[0], 'email' =>$row[1], 'username'=>$row[2], 'nicename'=>$row[3], 'url'=>$row[4], 'message'=>$row[5], 'registered_at'=>$row[6], 'first_name'=>$row[7], 'last_name'=>$row[8], 'display_name'=>$row[9], 'active_memberships'=>$row[10], 
            'active_txn_count'=>$row[11],'expired_txn_count'=>$row[12], 'trial_txn_count'=>$row[13], 'sub_count'=>$row[14], 'login_count'=>$row[15], 'first_txn'=>$row[16], 'latest_txn'=>$row[17], 'mepr-address-one'=>$row[18], 'mepr-address-two'=>$row[19], 'mepr-address-city'=>$row[20],
            'mepr-address-state'=>$row[21], 'mepr-address-zip'=>$row[22], 'mepr-address-country'=>$row[23], 'mepr_phone'=>$row[24], 'mepr_email'=>$row[25], 'mepr_how_would_you_like_to_receive_information_about_the_co_op'=>$row[26], 
            'mepr_adult_one'=>$row[27], 'mepr_adult_two'=>$row[28], 'mepr_adult_three'=>$row[29],'recent_transactions'=>$row[30], 'recent_subscriptions'=>$row[31], 'origin'=>$row[32], 'cardNo'=>$row[33]);
            $ret[] = $memberInfo;*/
        //$address = $memberInfo['address'];
        $meminfo = new MeminfoModel($connection);
        $meminfo->card_no($newCardNo);
        $meminfo->last_nane($memberInfo['last_name']);
        $meminfo->first_name($memberInfo['first_name']);
        $meminfo->street($memberInfo['mepr-address-one']." ".$memberInfo['mepr-address-two']);
        $meminfo->city($memberInfo['mepr-address-city']);
        $meminfo->state($memberInfo['mepr-address-state']);
        $meminfo->zip($memberInfo['mepr-address-zip']);
        $meminfo->phone($profile['mepr_phone']);
        $meminfo->email_1($memberInfo['email']);
        $meminfo->modified(date('Y-m-d H:i:s'));
        $meminfo->save();

        $mapInfo = array('memberPressID'=>$memberInfo['id'], 'cardNo'=>$newCardNo, 'lastPullDate'=>'', 
        'lastPushDate'=> date('Y-m-d H:i:s'), 'origin'=>'MemberPress');
        MemberPressSyncLib::mapMember($connection, $mapInfo);
        echo "New Member: ".$newCardNo." MemberPressID".$memberInfo['id']."\n";
        if($saved) {
            return $newCardNo;
        } 
        return false;
    }

        /**
     * create a member number for new memberpress members.
     */
    public static function updateCOREmeminfo($connection, $memberInfo) {
        $meminfo = new MeminfoModel($connection);
        $meminfo->card_no($memberInfo['cardNo']);
        $meminfo->street($memberInfo['street']);
        $meminfo->city($memberInfo['city']);
        $meminfo->state($memberInfo['state']);
        $meminfo->zip($memberInfo['zip']);
        $meminfo->phone($memberInfo['phone']);
        $meminfo->email_1($memberInfo['email']);
        $meminfo->modified(date('Y-m-d H:i:s'));
        $meminfo->save();
    }

    /**
     * update member info in core
     */
    public static function updateCOREcustdata($connection, $member) {
        $cardNo = $member['cardNo'];
        $memberShipID = MemberPressSyncLib::getActiveMembershipID($memberInfo['active_memberships']);
        $memType = MemberPressSyncLib::memberShipID_to_memType($connection, $memberShipID);
        $discount = MemberPressSyncLib::getDiscount($connection, $memType);
        $person_num = 1;
        $custData = new CustdataModel($connection);
        $custData->CardNo($cardNo);
        $custData->personNum($person_num);
        $exists = $custData->load();
        $custData->LastName($memberInfo['last_name']);
        $custData->FirstName($memberInfo['first_name']);
        $custData->CashBack(999.99);
        $custData->Balance(0);
        $custData->memCoupons(0);
        $custData->Discount($discount);
        $custData->Type('PC');
        $custData->staff(0);
        $custData->SSI(0);
        $custData->memType($memType);
        $custData->CardNo($cardNo);
        $custData->blueLine($cardNo.' '.$memberInfo['last_name']);
        $saved = $custData->save();
        $person_num++;

        $otherAdults = array($member['mepr_adult_one'], $member['mepr_adult_two'], $member['mepr_adult_three']);
        foreach ($otherAdults as $adult) {
            $names = explode(' ',$adult);
            $first_name ='';
            $last_name = '';
            for ($i=0; $i <  count($names)-1; $i++) { 
                 if($i ===0 ){
                    $first_name = $names[$i];
                 } else {
                    $last_name .= $names[$i];
                 }
            }
            $custData = new CustdataModel($connection);
            $custData->personNum($person_num);
            $custData->LastName($last_name);
            $custData->FirstName($first_name);
            $custData->CashBack(999.99);
            $custData->Balance(0);
            $custData->memCoupons(0);
            $custData->Discount($discount);
            $custData->Type('PC');
            $custData->staff(0);
            $custData->SSI(0);
            $custData->memType($memType);
            $custData->CardNo($cardNo);
            $custData->blueLine($cardNo.' '.$memberInfo['last_name']);
            $custData->save();
            $person_num++;
        }

        $address = $memberInfo['address'];
        $meminfo = new MeminfoModel($connection);
        $meminfo->card_no($cardNo);
        $exists = $custData->load();
        $meminfo->street($memberInfo['street']);
        $meminfo->city($memberInfo['city']);
        $meminfo->state($memberInfo['state']);
        $meminfo->zip($memberInfo['zip']);
        $meminfo->phone($memberInfo['phone']);
        $meminfo->email_1($memberInfo['email']);
        $meminfo->modified(date('Y-m-d H:i:s'));
        $meminfo->save();

        $mapInfo = array('memberPressID'=>$memberInfo['id'], 'cardNo'=>$newCardNo, 'lastPullDate'=>'', 
        'lastPushDate'=> date('Y-m-d H:i:s'), 'origin'=>'MemberPress');
        MemberPressSyncLib::mapMember($connection, $mapInfo);
        echo "Updating Member ".$newCardNo." MemberPressID".$memberInfo['id']."\n";
        
    }
    /**
   "address": {
    "mepr-address-one": "",
    "mepr-address-two": "",
    "mepr-address-city": "",
    "mepr-address-state": "",
    "mepr-address-zip": "",
    "mepr-address-country": ""
  },
  "profile": {
    "mepr_phone": "",
    "mepr_email": "",
    "mepr_how_would_you_like_to_receive_information_about_the_co_op": "email",
    "mepr_adult_one": "",
    "mepr_adult_two": "",
    "mepr_adult_three": "",
    "mepr_name_of_co_op_member_who_referred_you_if_applicable": "",
    "mepr_why_are_you_joining_the_franklin_community_co_op": "",
    "mepr_food_for_all_agreements": [],
    "mepr_documentation": [],
    "mepr_supporting_document": "",
    "mepr_rights_and_responsibilities_agreement": [],
    "mepr_cardno": "",
    "mepr_origin": "CORE"
  },
     */
    public static function getDiscount($connection, $memType) {
        $query = "SELECT discount FROM core_op.memtype WHERE memtype = {$memType}";
        $prep = $connection->prepare($query);
        $row = $connection->getRow($prep, array());
        if ($row === false) {
            return 0;
        }
        return $row[0];
    }

    public static function getFirstUnusedCardNo($connection) {
        $unusedQ = "SELECT MIN(z.expected) as cardNo
                    FROM ( SELECT
                        @rownum:=@rownum+1 AS expected,
                        IF(@rownum=CardNo, 0, @rownum:=CardNo) AS got
                        FROM (SELECT @rownum:=0) AS a
                        JOIN core_op.custdata where PersonNum=1 AND FirstName !=''
                        ORDER BY CardNo ) AS z
                    WHERE z.got!=0 AND z.expected > 1000";
        $prep = $connection->prepare($unusedQ);
        $row = $connection->getRow($prep, array());
        if($row === false) {
            return false;
        }
        return $row[0];
    }
    /**
     * Add a transaction to core, it will auto propigate into the payments table.
     */
    public static function addTransactionCORE($connection, $transaction, $cardNo) {
        global $FANNIE_OP_DB;
        global $FANNIE_EMP_NO, $FANNIE_REGISTER_NO;
        $emp_no = 1001;
        $register_no = 30;
        if (is_numeric($FANNIE_EMP_NO)) {
            $emp_no = $FANNIE_EMP_NO;
        }
        if (is_numeric($FANNIE_REGISTER_NO)) {
            $register_no = $FANNIE_REGISTER_NO;
        }
        $trans_no = DTrans::getTransNo($connection, $emp_no, $register_no);
        $upc = '0000000001480';
        
        //add the memberpayment.
        $params = array(
            'upc' => $upc,
            'description' => 'OnlineEquity#'.$transaction['trans_num'],
            'trans_type' => 'I',
            'trans_subtype' => '',
            'card_no' => $cardNo,
            'register_no' => $register_no,
            'emp_no' => $emp_no,
            'department'=>992,
            'quantity'=>1,
            'unitPrice'=>$transaction['amount'],
            'total'=>$transaction['amount'],
            'regPrice'=>$transaction['amount'],
        );
        DTrans::addItem($connection, $trans_no, $params);
        // add Square tender
        $params = array(
            'upc' => 0,
            'description' => 'Square',
            'trans_type' => 'T',
            'trans_subtype' => 'SQ',
            'card_no' => $cardNo,
            'register_no' => $register_no,
            'emp_no' => $emp_no,
            'department'=>0,
            'quantity'=>1,
            'unitPrice'=>0.00,
            'total'=>-$transaction['amount'],
            'regPrice'=>0.00,
        );
        DTrans::addItem($connection, $trans_no, $params);
        //add change
        $params = array(
            'upc' => 0,
            'description' => 'Change',
            'trans_type' => 'T',
            'trans_subtype' => 'CA',
            'card_no' => $cardNo,
            'register_no' => $register_no,
            'emp_no' => $emp_no,
            'department'=>0,
            'quantity'=>1,
            'unitPrice'=>0.00,
            'total'=>0.00,
            'regPrice'=>0.00,
        );
        DTrans::addItem($connection, $trans_no, $params);

        $now = date('Y-m-d');
        $tdate = date('Y-m-d',strtotime($transaction['created_at']));
        echo "Now:".$now."  tdate: ".$tdate." Trans: ".$transaction['created_at']."\n";
        
        // update the date to be right.
        $updateQ = "UPDATE core_trans.dtransactions SET `datetime` = '{$transaction['created_at']}'
        WHERE emp_no = {$emp_no} AND register_no = {$register_no} AND trans_no = {$trans_no} AND DATE(`datetime`) = '{$now}'";
        $prep = $connection->prepare($updateQ);
        $row = $connection->execute($prep, array());
        if ($row === false) {
                
        } else {
                
        }
        
        $COREmapID = $cardNo.'-'.$emp_no.'-'.$register_no.'-'.$trans_no.'-'.$transaction['created_at'];
        $mpMember = $transaction['member'];
        $mapInfo = array('mp_trans_num'=>$transaction['trans_num'], 'core_trans_num'=>$COREmapID, 'lastSyncDate'=>date('Y-m-d H:i:s'), 
                         'origin'=>'MemberPress', 'mpID'=>$transaction['id'], 'cardNo'=>$cardNo, 'mpMemberID'=>$mpMember['id']);
        MemberPressSyncLib::mapTrans($connection,$mapInfo);
    }

    /**
     * Find a member in the core database by accoication, used for initlizing after words member should beable to be looked up based solely on the map.
     */
    public static function coreMemberByName($connection, $mpID, $FirstName, $LastName, $email) {
        $ret =  array('cardNo'=>'','FirstName'=>'','LastName'=>'','street'=>'', 'city'=>'','state'=>'','zip'=>'', 'email'=>'', 'mepr_adult_one'=>'','mepr_adult_two'=>'','mepr_adult_three'=>'');
        $row = MemberPressSyncLib::isMapped($connection,$mpID,1);
        $memberQ = "SELECT c.cardNo, c.FirstName, c.LastName, i.street, i.city, i.state, i.zip, i.email_1 FROM core_op.custdata c,
            CONCAT(c2.FirstName, ' ', c2.LastName) as  mepr_adult_one ,CONCAT(c3.FirstName, ' ', c3.LastName) as  mepr_adult_two,
            CONCAT(c4.FirstName, ' ', c4.LastName) as  mepr_adult_three
            LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
            LEFT JOIN (select CardNo, LastName, FirstName FROM core_op.custdata WHERE personNum  = 2) c2 on c.cardNo = c2.CardNo
            LEFT JOIN (select CardNo, LastName, FirstName FROM core_op.custdata WHERE personNum  = 3) c3 on c.cardNo = c3.CardNo
            LEFT JOIN (select CardNo, LastName, FirstName FROM core_op.custdata WHERE personNum  = 4) c4 on c.cardNo = c4.CardNo";
        if ($row === false) {
            //if the member is not mapped try and find by name.
            $memberQ .= " WHERE (UPPER(c.LastName) = ? OR UPPER(i.last_name) = ?) AND (UPPER(c.FirstName) =? OR UPPER(i.first_name) = ?) and c.personNum=1";
            $prep = $connection->prepare($memberQ);
            $row = $connection->getRow($prep, array(strtoupper($LastName), strtoupper($LastName), strtoupper($FirstName), strtoupper($FirstName)));
            if ($row === false) {
                //find my email
                $memberQ .= " WHERE UPPER(i.email_1) = ? and c.personNum=1";
                $prep = $connection->prepare($memberQ);
                $row = $connection->getRow($prep, array(strtoupper($email)));
                if ($row === false) {
                    // add member to core if they have a subscriptions.
                    $ret =  array('cardNo'=>'','FirstName'=>'','LastName'=>'','street'=>'', 'city'=>'','state'=>'','zip'=>'', 'email'=>'', 'mepr_adult_one'=>'','mepr_adult_two'=>'','mepr_adult_three'=>'');
                } else {
                    $ret = MemberPressSyncLib::getCOREMemberReturnArray($row);
                }
            } else {
                $ret = MemberPressSyncLib::getCOREMemberReturnArray($row);
            }
        } else {
            //the member is already mapped, update member.
            $memberQ = " WHERE c.cardNo IN( SELECT cardNo FROM core_op.MemberPressMemberMap WHERE memberPressID =? )";
            $prep = $connection->prepare($memberQ);
            $newRow = $connection->getRow($prep, array($mpID));
            if ($newRow === false) {
                $ret =  array('cardNo'=>'MapError','FirstName'=>'MapError','LastName'=>'MapError','street'=>'MapError', 'city'=>'MapError','state'=>'MapError','zip'=>'MapError', 'email'=>'MapError', 'mepr_adult_one'=>'MapError','mepr_adult_two'=>'MapError','mepr_adult_three'=>'MapError');
            } else {
                $ret = MemberPressSyncLib::getCOREMemberReturnArray($newRow);
            }
        }
        //echo '<div><p>Fall Through Error<p></div>';
        return $ret;
    }
    private static function getCOREMemberReturnArray($row){
        $ret = array('cardNo'=>$row[0],'FirstName'=>$row[1],'LastName'=>$row[2],
                     'street'=>$row[3], 'city'=>$row[4],'state'=>$row[5], 'zip'=>$row[6],
                     'email'=>$row[7], 'mepr_adult_one'=>$row[8],'mepr_adult_two'=>$row[9],'mepr_adult_three'=>$row[10]);
        return $ret;
    } 
    public static function getNewCOREMembers($connection){
        //$ret =  array('cardNo'=>'','FirstName'=>'','LastName'=>'','street'=>'', 'city'=>'','state'=>'','zip'=>'', 'email'=>'');
        $getQ = "SELECT c.cardNo, c.FirstName, c.LastName, i.street, i.city, i.state, i.zip, i.email_1, CONCAT(c.cardNo,c.LastName) as username  FROM core_op.custdata c
            LEFT JOIN (SELECT p.card_no, SUM(p.stockPurchase) AS equity
            FROM core_trans.stockpurchases p GROUP BY p.card_no) AS p on c.cardNo = p.card_no
            LEFT JOIN core_op.MemberPressMemberMap m ON c.cardNo = m.cardNo
            LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no         
            WHERE personNum = 1 AND p.equity > 0 AND memberPressID IS NULL AND i.email_1 != '' AND i.email_1 IS NOT NULL";
        $prep = $connection->prepare($getQ);
        $results = $connection->execute($prep, array());
        $ret = array();
        while ($row = $connection->fetch_row($results)){
            $ret[] = MemberPressSyncLib::getCORENewMemberReturnArray($row);
        }
        return $ret;
    }
    private static function getCORENewMemberReturnArray($row){
        $ret = array('cardNo'=>$row[0],'FirstName'=>$row[1],'LastName'=>$row[2],
                            'street'=>$row[3], 'city'=>$row[4],'state'=>$row[5],'zip'=>$row[6], 'email'=>$row[7], 'username' => $row[8]);
        return $ret;
    } 

#
#   MEMBER PRESS API ACCESS FUNCTIONS START HERE.
#

    /**
     * MemberPress REST API Access to update a member account.
     */
    public static function updateMember($memberInfo,$memberID, $mpURL, $mpKey) {
        $url = $mpURL . "members/".$memberID;
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url."\n";
        $ch = curl_init($url);

        $data_string = json_encode($memberInfo);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey;// Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        $header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }

        //echo $response;
        
        curl_close($ch);
    }

    /**
     * MemberPress REST API Access to Create a transaciont
     *         # curl -X POST "https://www.franklincommunity.coop/wp-json/mp/v1/transactions" \
     *   #-H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7" \
     *   #-d amount="3.00" \
     *   #-d total="3.00" \
     *   #-d tax_amount="0.00" \
     *   #-d tax_rate="0.000" \
     *   #-d trans_num="mp-txn-656f71e357f1a" \
     *   #-d status=complete \
     *   #-d gateway=manual \
     *   #-d created_at="2023-12-05 18:54:27" \
     *   #-d expires_at="2024-01-05 23:59:59" 
     */
    public static function createTransaction($connection, $transInfo ,$mpURL, $mpKey, $cardNo) {
        $url = $mpURL . "transactions/";
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url."\n";
        $ch = curl_init($url);

        $data_string = json_encode($transInfo);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        $header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        $transaction = json_decode($response, true);
        curl_close($ch);
        //echo "%%%%%%%%%%%%%%%%%%%%CREATE TRANS RESONCE%%%%%%%%%%%%%%%%%%%%%%/n".$response."\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%END%%%%%%%%%%%%%%%%%%%%%%%%%%\n";
        if ($transaction['id']) {
            //$cardNo = MemberPressSyncLib::getCardNo($connection, $transInfo['member']);
            $mapInfo = array('mp_trans_num'=>$transInfo['trans_num'], 'core_trans_num'=>$transInfo['trans_num'], 'lastSyncDate'=>date('Y-m-d H:i:s'), 
            'origin'=>'CORE', 'mpID'=>$transaction['id'], 'cardNo'=>$cardNo, 'mpMemberID'=>$transInfo['member']);
            MemberPressSyncLib::mapTrans($connection,$mapInfo);
        } else {
            echo "%%%%%%%%%%%%%%%%%%%%CREATE TRANS RESONCE%%%%%%%%%%%%%%%%%%%%%%/n".$response."\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%END%%%%%%%%%%%%%%%%%%%%%%%%%%\n";
        }
        

     }

     public static function refundTransaction($connection, $transID ,$mpURL, $mpKey, $cardNo, $mpMemberID) {
        $url = $mpURL . "transactions/".$transID."/refund";
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url."\n";
        $ch = curl_init($url);

        //$data_string = json_encode($transInfo);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        //curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        //$header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        $transaction = json_decode($response, true);
        curl_close($ch);

        if ($transaction['id']) {
            $mapInfo = array('mp_trans_num'=>$transInfo['trans_num'], 'core_trans_num'=>$transInfo['trans_num'], 'lastSyncDate'=>date('Y-m-d H:i:s'), 
            'origin'=>'CORE', 'mpID'=>$transaction['id'], 'cardNo'=>$cardNo, 'mpMemberID'=>$transInfo['member']);
            MemberPressSyncLib::mapTrans($connection,$mapInfo);
        } else {
            echo "%%%%%%%%%%%%%%%%%%%%CREATE TRANS RESONCE%%%%%%%%%%%%%%%%%%%%%%/n".$response."\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%END%%%%%%%%%%%%%%%%%%%%%%%%%%\n";
        }
        

     }


         /*
     array('email' => '',
           'username' => '',
           'first_name' => '',
           'last_name' => '',
           'transaction => array(),
           'send_welcome_email => false,
           'send_password_email => false,
           'address1' => '',
           'address2' => '',
           'city' => '',
           'state' => '',
           'zip' => '',
           'country' => ''
           )
    */
    public static function createMember($connection, $memberInfo,$mpURL, $mpKey, $cardNo) {
        $url = $mpURL . "members/";
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url."\n";
        $ch = curl_init($url);

        $data_string = json_encode($memberInfo);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        $header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        $member = json_decode($response, true);
        curl_close($ch);

        if ($member['id']) {
            $mapInfo = array('memberPressID'=>$member['id'], 'cardNo'=>$cardNo, 'lastPullDate'=>'', 
                             'lastPushDate'=> date('Y-m-d H:i:s'), 'origin'=>'CORE');
            MemberPressSyncLib::mapMember($connection, $mapInfo);
            $firstTrans = $member['first_txn'];
            $mpMemberID = $member['id'];
            $mapInfo = array('mp_trans_num'=>$firstTrans['trans_num'], 'core_trans_num'=>$firstTrans['trans_num'], 'lastSyncDate'=>date('Y-m-d H:i:s'), 'origin'=>'CORE', 'mpID'=>$firstTrans['id'], 'cardNo'=>$cardNo, 'mpMemberID'=>$mpMemberID);
            MemberPressSyncLib::mapTrans($connection,$mapInfo);
        } else {
            echo "%%%%%%%%%%%%%%%%%%%%CREATE TRANS RESONCE%%%%%%%%%%%%%%%%%%%%%%/n".$response."\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%END%%%%%%%%%%%%%%%%%%%%%%%%%%\n";
        }

        return $member;
        //echo $response;
        

        curl_close($ch);
      }

    /**
     * MemberPress REST API Access to get all transactions for a member.
     */
    public static function getTransactions($memberID,$mpURL, $mpKey) {
        
        $params = array('page' => 1, 'per_page'=>99999);
        if ($memberID !== '') {
            $params['member'] = $memberID;
        }
        $url = $mpURL . "transactions/" . '?' . http_build_query($params);
        

        echo $url."\n";
        $ch = curl_init($url);

  
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        
        $transactions = json_decode($response, true);
        #echo "%%%%%%%%%%%%%%%%%%%%CREATE TRANS RESONCE%%%%%%%%%%%%%%%%%%%%%%/n".$response."\n%%%%%%%%%%%%%%%%%%%%%%%%%%%%END%%%%%%%%%%%%%%%%%%%%%%%%%%\n";
        curl_close($ch);

        return $transactions;
    }

    public static function getMembers($mpURL, $mpKey) {

        $params = array('page' => 1, 'per_page'=>99999);
        $url = $mpURL . "members" . '?' . http_build_query($params);

        echo $url."\n";
        $ch = curl_init($url);

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; 
        $header[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        //echo $response;
        //$members = json_decode(json_encode($response), true);
        $members = json_decode($response, true);

        curl_close($ch);

        return $members;
    }

    public static function getMember($mpURL, $mpKey,$mpMemberID) {

        $params = array('page' => 1, 'per_page'=>99999);
        $url = $mpURL . "members/".$mpMemberID . '/?' . http_build_query($params);

        echo $url."\n";
        $ch = curl_init($url);

        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; 
        $header[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        //echo $response;
        //$members = json_decode(json_encode($response), true);
        $members = json_decode($response, true);

        curl_close($ch);

        return $members;
    }

}
