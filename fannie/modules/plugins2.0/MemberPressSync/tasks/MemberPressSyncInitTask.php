<?php
/*******************************************************************************

    Copyright 2023-12-06 Franklin Community Coop

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

use COREPOS\Fannie\API\item\ItemText;

include(dirname(__FILE__).'/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}
include_once(dirname(__FILE__).'/../lib/MemberPressSyncLib.php');
/**
*/
class MemberPressSyncInitTask extends FannieTask 
{
    public $name = 'Initilize Member Press Sync Tables';

    public $description = 'Initilize the mapping tables for member press and core to start syncing ';

    public $default_schedule = array(
        'min' => 0,
        'hour' => 1,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    private $mpURL ='';
    private $mpKey = '';
    

    
    public function run()
    {
        $conf = FannieConfig::factory();
        $settings = $conf->get('PLUGIN_SETTINGS');
        $mpURL = $settings['mpUrl'];
        $mpKey = $settings['mpAPIKey'];

        global $FANNIE_OP_DB;
        $OpDB = $FANNIE_OP_DB;
		$dbc = FannieDB::get($OpDB);

        /*
            Hand Mapped members who weren't matchable by name or email.
        */
        //echo MemberPressSyncLib::getMember($mpURL,$mpKey,643);
        $this->initUnmapable($dbc);
        $this->initPaymentPlanMap($dbc);
        $this->updateCorePayments($dbc);
        $this->pullMembers($dbc, $mpURL, $mpKey);
        $this->syncMembers($dbc, $mpURL, $mpKey);
        
        $this->pullTransactions($dbc, $mpURL, $mpKey);
        $this->syncTransactions($dbc, $mpURL, $mpKey);
        
        $this->pushMembers($dbc, $mpURL, $mpKey);
        $this->pushTransactions($dbc, $mpURL, $mpKey);
        //643
    }
    private function updateCOREPayments($connection) {
        $query = "INSERT INTO core_op.MemberPressCOREPayments (card_no, stockPurchase, tdate, trans_num, trans_id, dept, id) 
			SELECT p.card_no, p.stockPurchase, p.tdate, CONCAT(p.card_no,'-',p.trans_num,'-',p.tdate,'-',@rownum:=@rownum+1) as trans_num,p.trans_id,
            p.dept, @rownum:=@rownum as `id`
            FROM (SELECT @rownum:=0) as r
            JOIN core_trans.stockpurchases p
            WHERE p.trans_num != ''
            ON DUPLICATE KEY UPDATE dept = p.dept";
        $prep = $connection->prepare($query);
        $result = $connection->execute($prep, array());
    }

    private function syncTransactions($connection, $mpURL, $mpKey) {
        $msg = "\n########### syncTransactions Start ###########\n";
        echo $this->cronMsg($msg);
        $transactions = MemberPressSyncLib::retriveMPTransactions($connection);

        foreach ($transactions as $transaction) {
            
            if ($transaction['origin'] === 'CORE'){
                $cardNo = MemberPressSyncLib::getCardNo($connection, $transaction['member']);
                $mapInfo = array('mp_trans_num'=>$transaction['trans_num'], 
                    'core_trans_num'=>$transaction['trans_num'],
                    'lastSyncDate'=>date('Y-m-d H:i:s'),
                    'origin'=>'CORE',
                    'mpID'=>$transaction['id'],
                    'cardNo'=>$cardNo,
                    'mpMemberID'=>$transaction['member']);
                MemberPressSyncLib::mapTrans($connection,$mapInfo);
            } else {
                //$this->echoTransaction($transaction);
                $cardNo = MemberPressSyncLib::getCardNo($connection, $transaction['member']);
                $coreTransNo = MemberPressSyncLib::getCOREMemberTransaction($connection, $cardNo, $transaction['created_at']); 
                if ($coreTransNo === false && $transaction['gateway'] != 'manual') {
                    //Create transaction in CORE 
                    MemberPressSyncLib::addTransactionCORE($connection, $transaction, $cardNo);
                    $msg = "Importing MP Transaction:".$transaction['trans_num']." Amount: ".$transaction['amount']."Date: ".$transaction['created_at']." Is Mapped: ".$isMapped."\n";
                    echo $this->cronMsg($msg);
                } else {
                    // the core transaction has already been added map it.
                    $mapInfo = array('mp_trans_num'=>$transaction['trans_num'], 
                        'core_trans_num'=>$coreTransNo,
                        'lastSyncDate'=>date('Y-m-d H:i:s'),
                        'origin'=>'MemberPress',
                        'mpID'=>$transaction['id'],
                        'cardNo'=>$cardNo,
                        'mpMemberID'=>$transaction['member']);
                    MemberPressSyncLib::mapTrans($connection,$mapInfo);
                    $msg = "Mapping MP Transaction:".$transaction['trans_num']." Amount: ".$transaction['amount']."Date: ".$transaction['created_at']." CORE Trans_num: ".$coreTransNo."\n";
                    echo $this->cronMsg($msg);
                }
            }
            //$cardNo = MemberPressSyncLib::getCardNo($connection, $transaction['member']);
            //transIsMapped($connection, $transNoMemberPress, $transNoCORE)
            //$coreTransNo = MemberPressSyncLib::getCOREMemberTransaction($connection, $core_member['cardNo'], $transaction['created_at']);            

        }

        $msg = "########### syncTransactions Compleate ###########\n";
        echo $this->cronMsg($msg);
    }

    private function pushMembers($connection, $mpURL, $mpKey){
        $msg = "########### Push Members Start ###########\n";
        echo $this->cronMsg($msg);
        $newMembers = MemberPressSyncLib::getNewCOREMembers($connection);
        $pushCount = 0;
        foreach ($newMembers as $member) {
            $this->newCoreMember($connection,$mpURL,$mpKey, $member);
            //$msg = $this->loopArray($member);
            //echo $this->cronMsg($msg);
            $pushCount++;
        }
        $msg = "Pushed: ".$pushCount." Members\n";
        echo $this->cronMsg($msg);
        $msg = "########### Push Members Compleate ###########\n";
        echo $this->cronMsg($msg);
    }
    private function newCoreMember($connection, $mpURL, $mpKey, $core_member) {
        
        $activeMembershipID = MemberPressSyncLib::paymentPlanToMemberShipID($connection, $core_member['cardNo']);
        
        
            $firstTrans = MemberPressSyncLib::getFirstCORETrans($connection, $core_member['cardNo'], $activeMembershipID);
            $memberInfo = array('email' => $core_member['email'],
                'username' => $core_member['username'],
                'first_name' => $core_member['FirstName'],
                'last_name' => $core_member['LastName'],
                'transaction' => $firstTrans,
                'send_welcome_email' => false,
                'send_password_email' => false,
                'address1' => $core_member['street'],
                'address2' => '',
                'city' => $core_member['city'],
                'state' => $core_member['state'],
                'zip' => $core_member['zip'],
                'country' => ''
            );
            $memberID = MemberPressSyncLib::memberExistsMP($connection, $core_member['email']);
            if ($memberID === False) {
                //$member = array('id'=>'');
                $member = MemberPressSyncLib::createMember($connection, $memberInfo,$mpURL, $mpKey, $core_member['cardNo']);
                //$msg = "New Member: ".$member['id']."-".$core_member['cardNo']." Added\n";
                //echo $this->cronMsg($msg);
            } else {
                MemberPressSyncLib::updateMember($memberInfo,$memberID, $mpURL, $mpKey);
                //$msg = "New Member: ".$memberID."-".$core_member['cardNo']." Updated\n";
                //echo $this->cronMsg($msg);
            }

            

            /*
            $transactions = MemberPressSyncLib::getCORETrans($connection, $core_member['cardNo'], $activeMembershipID);
            $totalEquity = 0;
            foreach ($transactions as $transaction) {
                $isMapped = MemberPressSyncLib::transIsMapped($connection,1,$transaction['trans_num']);
                if($isMapped===false){
                    if ($totalEquity <= 175.00) {
                        $transaction['expires_at'] = '';//'expires_at' => '0000-00-00 00:00:00'
                        $totalEquity += $transaction['total'];
                    }
                    $this->echoTransaction($transaction);

                        $msg = "Adding CORE transaction {$transaction['trans_num']} for {$core_member['cardNo']}\n";
                        echo $this->cronMsg($msg);
                        MemberPressSyncLib::createTransaction($connection, $transaction, $mpURL, $mpKey,$core_member['cardNo']);
                    
                }
            }
            */
    }

    private function pushTransactions($connection, $mpURL, $mpKey) {
        $msg = "########### Push Transactions Start ###########\n";
        echo $this->cronMsg($msg);
        $transactions = MemberPressSyncLib::getNewCoreTrans($connection);
        $pushCount = 0;
        foreach ($transactions as $transInfo) {
            //$msg = $this->echoTransaction($transInfo);
            //echo $this->cronMsg($msg);
            $cardNo = 0;
            MemberPressSyncLib::createTransaction($connection, $transInfo ,$mpURL, $mpKey, $cardNo);
            $pushCount++;
        }
        $msg = "Pushed: ".$pushCount." Transactions\n";
        echo $this->cronMsg($msg);
        $msg = "########### Push Transactions Compleate ###########\n";
        echo $this->cronMsg($msg);
    }

    /*
      $ret = array('cardNo'=>$row[0],'FirstName'=>$row[1],'LastName'=>$row[2],
                            'street'=>$row[3], 'city'=>$row[4],'state'=>$row[5],'zip'=>$row[6], 'email'=>$row[7], 'username' => $row[8]);
        return $ret;
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

    private function pullTransactions($connection, $mpURL, $mpKey) {
        $msg = "\n########### Pull Transactions Start ###########\n";
        echo $this->cronMsg($msg);
        $mpTransactions = MemberPressSyncLib::getTransactions('', $mpURL, $mpKey);
        foreach ($mpTransactions as $transaction) {
            $transInfo = array(
                'membership' => '', 
                'member'=>'', 
                'coupon'=>'', 
                'subscription'=>'', 
                'id'=>0, 
                'amount'=>0.00, 
                'total'=>0.00, 
                'tax_amount'=>0.00, 
                'tax_reversal_amount' =>0.00, 
                'tax_rate'=>0.000,
                'tax_desc'=>'', 
                'tax_class'=>'', 
                'trans_num'=>'', 
                'status'=>'',
                'txn_type'=>'', 
                'gateway'=>'', 
                'prorated'=>0, 
                'created_at'=>'', 
                'expires_at'=>'', 
                'corporate_account_id'=>0,
                'parent_transaction_id'=>0, 
                'order_id'=>'', 
                'tax_compound'=>0, 
                'tax_shipping'=>0, 
                'response'=>null, 
                'rebill'=>false,
                'subscription_payment_index'=>false, 
                'core_trans_num'=>'',
                'origin'=>'');
            foreach ($transaction as $key => $value){
                switch ($key) {
                    case 'membership':
                    case 'member':
                    case 'subscription':
                    case 'coupon':
                        $transInfo[$key] = (empty($value)) ? 0 : $value['id'] ;
                        break;
                    default:
                        $transInfo[$key] = $value;
                        break;
                }
            }
            //\d{1,4}(?:-[^0-9]{3})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(\s)(\d{2})(?::\d{2})(?::\d{2})
            $regEx = '/\d{1,4}(?:-[^0-9]{3})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(\s)(\d{2})(?::\d{2})(?::\d{2})|\d{1,4}(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{4})(?:-\d{2})(?:-\d{2})(\s)(\d{2})(?::\d{2})(?::\d{2})/';
            //echo "PregMatch: ".preg_match($regEx, $transaction['trans_num'])." ";
            if ( preg_match($regEx, $transaction['trans_num']) ){
                $transInfo['origin'] = 'CORE';
                $transInfo['core_trans_num'] = $transInfo['trans_num'];
                //echo "Origin: ".$transInfo['origin']."  trans_num: ".$transInfo['trans_num']."\n";
            } else {
                $transInfo['origin'] = 'MemberPress';
                //echo "Origin: ".$transInfo['origin']."  trans_num: ".$transInfo['trans_num']."\n";
            }
            //$this->echoTransaction($transInfo);
            if ($transInfo['status' !== 'Failed']) {
                MemberPressSyncLib::addMPTransaction($connection, $transInfo) ;
            }

        }
        $msg = "\n########### Pull Transactions End ###########\n";
        echo $this->cronMsg($msg);
    }

    /**
     * {
  "membership": {"id": 18924,},
  "member": {"id": 645,},
  "coupon": "0",
  "subscription": "0",
  "id": "2670",
  "amount": "125.00",
  "total": "125.00",
  "tax_amount": "0.00",
  "tax_reversal_amount": "0.00",
  "tax_rate": "0.000",
  "tax_desc": "standard",
  "tax_class": "standard",
  "trans_num": "1284-gfm-1996-02-14 00:00:00",
  "status": "complete",
  "txn_type": "payment",
  "gateway": "manual",
  "prorated": "0",
  "created_at": "1996-02-14 00:00:00",
  "expires_at": "1996-03-14 23:59:59",
  "corporate_account_id": "0",
  "parent_transaction_id": "0",
  "order_id": "0",
  "tax_compound": "0",
  "tax_shipping": "1",
  "response": null,
  "rebill": false,
  "subscription_payment_index": false
}
     */

    private function pullMembers($connection, $mpURL, $mpKey) {
        $msg = "\n########### Pull Members Start ###########\n";
        echo $this->cronMsg($msg);
        $mpMembers = MemberPressSyncLib::getMembers($mpURL, $mpKey);
        foreach ($mpMembers as $member) {
            $memberInfo = array('id' => 0, 'email' =>'', 'username'=>'', 'nicename'=>'', 'url'=>'', 'message'=>'', 'registered_at'=>'', 'first_name'=>'', 'last_name'=>'', 'display_name'=>'', 'active_memberships'=>'', 
            'active_txn_count'=>0,'expired_txn_count'=>0, 'trial_txn_count'=>0, 'sub_count'=>0, 'login_count'=>0, 'first_txn'=>'', 'latest_txn'=>'', 'mepr-address-one'=>'', 'mepr-address-two'=>'', 'mepr-address-city'=>'',
            'mepr-address-state'=>'', 'mepr-address-zip'=>'', 'mepr-address-country'=>'', 'mepr_phone'=>'', 'mepr_email'=>'', 'mepr_how_would_you_like_to_receive_information_about_the_co_op'=>'', 
            'mepr_adult_one'=>'', 'mepr_adult_two'=>'', 'mepr_adult_three'=>'','recent_transactions'=>'', 'recent_subscriptions'=>'', 'origin'=>'Someplace', 'cardNo'=>0);

            foreach ($member as $key => $value) {
                switch ($key) {
                    case 'first_txn':
                        $memberInfo[$key] = (empty($value)) ? 0 : $value['id'] ;
                        break;
                    case 'latest_txn':
                        $memberInfo[$key] = (empty($value)) ? 0 : $value['id'] ;
                        break;
                    case 'address':
                        $memberInfo['mepr-address-one'] = $value['mepr-address-one'];
                        $memberInfo['mepr-address-two'] = $value['mepr-address-two'];
                        $memberInfo['mepr-address-city'] = $value['mepr-address-city'];
                        $memberInfo['mepr-address-state'] = $value['mepr-address-state'];
                        $memberInfo['mepr-address-zip'] = $value['mepr-address-zip'];
                        $memberInfo['mepr-address-country'] = $value['mepr-address-country'];
                        break;
                    case 'profile':
                        $memberInfo['mepr_phone'] = $value['mepr_phone'];
                        $memberInfo['mepr_email'] = $value['mepr_email'];
                        $memberInfo['mepr_how_would_you_like_to_receive_information_about_the_co_op'] = $value['mepr_how_would_you_like_to_receive_information_about_the_co_op'];
                        $memberInfo['mepr_adult_one'] = $value['mepr_adult_one'];
                        $memberInfo['mepr_adult_two'] = $value['mepr_adult_two'];
                        $memberInfo['mepr_adult_three'] = $value['mepr_adult_three'];
                        break;
                    case 'active_memberships':
                    case 'recent_transactions':
                    case 'recent_subscriptions':
                        $memberInfo[$key] = (array_key_exists('id',$value)) ? $value['id'] : 0;
                        break;
                    default:
                        $memberInfo[$key] = $value;
                        break;
                }
            } 
            //$firstTrans = null;
            $firstTrans = (array_key_exists('first_txn', $member)) ? $member['first_txn'] : null ;
            //$firstTrans = $member['first_txn'];
            if(is_array($firstTrans)) {
                $regEx = '/\d{1,4}(?:-[^0-9]{3})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})\s{1}\d{2}(?::\d{2})(?::\d{2})(?:-\d{1,9})|\d{1,4}(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{4})(?:-\d{2})(?:-\d{2})\s{1}\d[^0-24](?::\d{2})(?::\d{2})(?:-\d{1,9})/';
                if (key_exists('trans_num', $firstTrans)){
                    $memberInfo['origin'] = ( preg_match($regEx, $firstTrans['trans_num']) ) ? 'CORE' : 'MemberPress' ;
                }
            } else {
                $memberInfo['origin'] = 'CORE';
            }
            //echo "Member Origin:".$memberInfo['origin']." ".$memberInfo['first_name']."  ".$memberInfo['id']."\n";
            

            //$memberInfo['cardNo'] = ($core_member['cardNo'] !== '' && $core_member !== null) ? $core_member['cardNo'] : '0' ;
            //$msg = "\n########### ".$this->loopArray($memberInfo)." ###########\n";
            //echo $this->cronMsg($msg);
            MemberPressSyncLib::addMPMember($connection, $memberInfo);
            
        }
        $msg = "\n########### Pull Members End ###########\n";
        echo $this->cronMsg($msg);
    }
    private function syncMembers($connection, $mpURL, $mpKey) {
        $msg = "\n########### Sync Members Start ###########\n";
        echo $this->cronMsg($msg);
        $members = MemberPressSyncLib::retriveMPMembers($connection,'','');
        foreach ($members as $member) {
            $core_member = MemberPressSyncLib::coreMemberByName($connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);
            if ($core_member['cardNo']===''){
                //Member PRess member not in core, create a new one.
                $cardNo = MemberPressSyncLib::createCOREMember($connection, $member);
                if ($cardNo === false) {
                    $mapInfo = array('memberPressID'=>$member['id'], 'cardNo'=>$cardNo, 'lastPullDate'=>'', 
                    'lastPushDate'=> date('Y-m-d H:i:s'), 'origin'=>$member['origin']);
                    MemberPressSyncLib::mapMember($connection, $mapInfo);
                }
                continue;
            } else {
                $this->updateCOREMember($connection, $member, $core_member);
            }
            $isMapped = MemberPressSyncLib::isMapped($connection,$member['id'], $core_member['cardNo']);
            //echo "Is Mapped: ".$isMapped." Member: ".$core_member."\n";
            if ($isMapped == false) {
                //member press member is not mapped map and move on.
                $mapInfo = array('memberPressID'=>$member['id'], 'cardNo'=>$core_member['cardNo'], 'lastPullDate'=>'', 
                'lastPushDate'=> date('Y-m-d H:i:s'), 'origin'=>$member['origin']);
                MemberPressSyncLib::mapMember($connection, $mapInfo);
                continue;
            }
        }

        $msg = "\n########### Sync Members End ###########\n";
        echo $this->cronMsg($msg);
        /*
            $isMapped = MemberPressSyncLib::isMapped($connection,$member['id'], $core_member['cardNo']);
        if($isMapped === false) {

        }
        
        $core_member = MemberPressSyncLib::coreMemberByName($connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);

*/
    }
    
    private function updateCOREMember($connection, $member, $core_member) {
        $memberInfo = array('email' => $core_member['email'],
            'street' => $core_member['street'],
            'city' => $core_member['city'],
            'state' => $core_member['state'],
            'zip' => $core_member['zip']
        );


        $memberInfo['email'] = ($core_member['email'] === '') ? $member['email'] : $core_member['email'];
        $memberInfo['street'] = ($core_member['street'] === '') ? $member['mepr-address-one'].' '.$address['mepr-address-two']  : $core_member['street'];
        $memberInfo['city'] = ($core_member['city'] === '') ? $member['mepr-address-city'] : $core_member['city'];
        $memberInfo['state'] = ($core_member['state'] === '') ? $member['mepr-address-state'] : $core_member['state'];
        $memberInfo['zip'] = ($core_member['zip'] === '') ? $member['mepr-address-zip'] : $core_member['zip'];
        //$memberInfo['pnone'] = ($core_member['phone'] === '') ? $profile['mepr_phone'] : $core_member['phone'];

        $runUpdate = False;

        foreach ($memberInfo as $key => $value) {
            if ($value != $core_member[$key]) {
                $runUpdate = True;
                $memberInfo['cardNo'] = $core_member['cardNo'];
                break;
            }
        }
        if ($runUpdate === True) {
            MemberPressSyncLib::updateCOREmeminfo($connection, $memberInfo);
        }


    }


    private function initPaymentPlanMap($connection){
        $queries = array(
            "INSERT INTO core_op.MemberPressPaymentPlanMap (mp_membership_id, paymentPlanID, mp_name, paymentPlanName) VALUES(18924, 1, 'Pay $3 / Month','Member Plan')",
            "INSERT INTO core_op.MemberPressPaymentPlanMap (mp_membership_id, paymentPlanID, mp_name, paymentPlanName) VALUES(18925, 5, 'Pay $5 / Month','Member $5 Plan')",
            "INSERT INTO core_op.MemberPressPaymentPlanMap (mp_membership_id, paymentPlanID, mp_name, paymentPlanName) VALUES(18629, 3, 'Food For All','Scholarship Plan')",
            "INSERT INTO core_op.MemberPressPaymentPlanMap (mp_membership_id, paymentPlanID, mp_name, paymentPlanName) VALUES(18923, 4, 'FCC member application to pay in full','Paid in full')",
            "INSERT INTO core_op.MemberPressPaymentPlanMap (mp_membership_id, paymentPlanID, mp_name, paymentPlanName) VALUES(21875, 2, 'Staff Payment Plan', 'Staff Payment Plan')"
        );
        foreach ($queries as $key => $query) {
            $idLength = 5;
            //if (strlen($query) <= 95) $idLength = 2;
            $mpPlan = substr($query, 114, $idLength);
            $msg = "mpPlan:".$mpPlan."\n";
            echo $this->cronMsg($msg);
            $isMapped = MemberPressSyncLib::paymentPlanIsMapped($connection,$mpPlan, 1);
            $msg = "mpID: ".$mpPlan." is mapped:".$isMapped."\n";
            echo $this->cronMsg($msg);
            if ($isMapped === false) {
                //map it.
                $prep = $connection->prepare($query);
                $row = $connection->execute($query, array());
                if ($row !== false) {
                    $msg = "mpID: ".$mpPlan." Mapped!\n";
                    echo $this->cronMsg($msg);
                } else {
                    $msg = "mpID: ".$mpPlan." Map FAILD!\n";
                    echo $this->cronMsg($msg);
                }
            } else {
                $msg = "Already Mapped!";
                echo $this->cronMsg($msg);
            }
        }
    }

    private function initUnmapable($connection) {
        $queries = array(
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(79,    1914,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(27,    3529,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(46,    5558,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(98,    3964,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(101,	2955,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(109,	5366,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(125,	4700,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(184,	1431,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(221,	4116,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(231,	2689,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(234,	6843,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(263,	2392,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(281,	6754,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(284,	3192,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(311,	3015,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(312,	1444,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(316,	2621,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(350,	3429,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(368,	2653,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(380,	2016,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(386,	3637,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(402,	6691,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(408,	4579,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(419,	3841,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(431,	4052,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(434,	3136,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(440,	2041,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(442,	4576,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(447,	3110,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(448,	3862,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(472,	1008,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(506,	5397,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(508,	2308,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(574,	7670,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(643,	3363,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(645,   1284,'CORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(637,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(596,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(582,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(567,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(559,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(558,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(557,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(546,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(545,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(535,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(534,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(525,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(522,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(512,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(509,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(505,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(475,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(435,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(428,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(424,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(423,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(413,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(412,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(406,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(404,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(384,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(381,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(379,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(373,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(370,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(367,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(362,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(357,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(353,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(348,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(343,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(337,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(330,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(329,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(326,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(324,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(321,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(320,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(314,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(313,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(310,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(309,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(300,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(298,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(297,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(294,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(287,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(280,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(268,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(266,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(240,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(238,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(218,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(215,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(213,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(211,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(209,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(204,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(203,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(195,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(185,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(181,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(135,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(89,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(44,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(32,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(1,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(2,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(3,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(4,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(5,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(6,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(14,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(25,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(74,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(201,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(419,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(528,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(530,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(550,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(590,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(603,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(617,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(612,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(630,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(631,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(15,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(17,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(56,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(76,   99999,'IGNORE')",
            "INSERT INTO core_op.MemberPressMemberMap (memberPressID, cardNo, origin) VALUES(84,   99999,'IGNORE')",
//612,630,631            

        );

        /**
         * id
          */
        foreach ($queries as $key => $query) {
            $idLength = 3;
            if (strlen($query) <= 95) $idLength = 2;
            $mpID = substr($query, 80, $idLength);
            
            $isMapped = MemberPressSyncLib::isMapped($connection,$mpID, 1);
            //$msg = "mpID: ".$mpID." is mapped:".$isMapped."\n";
            //echo $this->cronMsg($msg);
            if ($isMapped === false) {
                //map it.
                $prep = $connection->prepare($query);
                $row = $connection->getRow($query, array());
                if ($row !== false) {
                    $msg = "mpID: ".$mpID." Mapped!\n";
                } else {
                    $msg = "mpID: ".$mpID." Map FAILD!\n";
                }
            } else {
                #$msg = "Already Mapped!";
                //echo $this->cronMsg($msg);
            }
        }
        $msg = "########### initUnmapable Complate ###########\n";
        echo $this->cronMsg($msg);
    }

    private function echoTransaction($trans) {
        $msg = $this->loopArray($trans);
        echo $this->cronMsg($msg);
        $msg = "\n";
        echo $this->cronMsg($msg);
    }

    private function loopArray($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= $key.': ';
            $ret .= $value;
            $ret .= '  ';
        }
        $ret .="\n";
        return $ret;
    }
    
}

