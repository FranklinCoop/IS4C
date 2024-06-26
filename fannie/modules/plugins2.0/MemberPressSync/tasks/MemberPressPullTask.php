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
class MemberPressPullTask extends FannieTask 
{
    public $name = 'For pulling info from Memberpress';

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
            Update payments subtable for unique identifcation for memberpress.
        */
        $this->updateCorePayments($dbc);
        
        /*
            Pull memberships from memberpress and puts them in a interum table.
            Creates or updates members in CORE based on the most recent memberpress pull
            The processes is fast and less likely to fail if we pull all the data and save it to the database before
            we work with it.
        */
        $this->pullMembers($dbc, $mpURL, $mpKey);
        $this->syncMembers($dbc, $mpURL, $mpKey);
        
        /*
            Pull transactions for Member Press members, match them
            with fannie records and map them.
            The processes is fast and less likely to fail if we pull all the data and save it to the database before
            we work with it.
        */
        $this->pullTransactions($dbc, $mpURL, $mpKey);
        $this->syncTransactions($dbc, $mpURL, $mpKey);

    }

    /*
    *   Creates unique id's for every payment in core, currently the id's are not unique to the day.
    */
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

    /*
    *   Pulls members from memberpress
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
            
            
            /** check if the first transaction exisists then if it is formated as a transaction from CORE **/
            /** Set the oragin of the member according to where the first transaction is from, or default to CORE. **/
            $firstTrans = (array_key_exists('first_txn', $member)) ? $member['first_txn'] : null ;
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
    /*
    *   Adds or updates CORE members based on data from MemberPress
    */
    private function syncMembers($connection, $mpURL, $mpKey) {
        $msg = "\n########### Sync Members Start ###########\n";
        echo $this->cronMsg($msg);
        $members = MemberPressSyncLib::retriveMPMembers($connection,'','');
        foreach ($members as $member) {
            echo "Member: ".var_dump($member)."\n";
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
                //member press member is in CORE, update.
                $this->updateCOREMember($connection, $member, $core_member);
            }
            $isMapped = MemberPressSyncLib::isMapped($connection, $member['id'], $core_member['cardNo']);
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
    }
    private function updateCOREMember($connection, $member, $core_member) {
        $memberInfo = array('email' => $core_member['email'],
            'street' => $core_member['street'],
            'city' => $core_member['city'],
            'state' => $core_member['state'],
            'zip' => $core_member['zip']
        );
        //$memberInfo = array('id' => $row[0], 'email' =>$row[1], 'username'=>$row[2], 'nicename'=>$row[3], 'url'=>$row[4], 'message'=>$row[5], 'registered_at'=>$row[6], 'first_name'=>$row[7], 'last_name'=>$row[8], 'display_name'=>$row[9], 'active_memberships'=>$row[10], 
        //    'active_txn_count'=>$row[11],'expired_txn_count'=>$row[12], 'trial_txn_count'=>$row[13], 'sub_count'=>$row[14], 'login_count'=>$row[15], 'first_txn'=>$row[16], 'latest_txn'=>$row[17], 'mepr-address-one'=>$row[18], 'mepr-address-two'=>$row[19], 'mepr-address-city'=>$row[20],
        //    'mepr-address-state'=>$row[21], 'mepr-address-zip'=>$row[22], 'mepr-address-country'=>$row[23], 'mepr_phone'=>$row[24], 'mepr_email'=>$row[25], 'mepr_how_would_you_like_to_receive_information_about_the_co_op'=>$row[26], 
        //    'mepr_adult_one'=>$row[27], 'mepr_adult_two'=>$row[28], 'mepr_adult_three'=>$row[29],'recent_transactions'=>$row[30], 'recent_subscriptions'=>$row[31], 'origin'=>$row[32], 'cardNo'=>$row[33]);
        $other_names = array($member['mepr_adult_one'], $member['mepr_adult_two'], $member['mepr_adult_three']);

        $memberInfo['cardNo'] = $core_member['cardNo'];
        $memberInfo['id'] = $member['id'];
        $memberInfo['email'] = ($core_member['email'] === '') ? $member['email'] : $core_member['email'];
        $memberInfo['first_name'] = ($core_member['FirstName'] === '') ? $member['first_name'] : $core_member['FirstName'];
        $memberInfo['last_name'] = ($core_member['LastName'] === '') ? $member['last_name'] : $core_member['LastName'];
        $memberInfo['street'] = ($core_member['street'] === '') ? $member['mepr-address-one'].' '.$member['mepr-address-two']  : $core_member['street'];
        $memberInfo['city'] = ($core_member['city'] === '') ? $member['mepr-address-city'] : $core_member['city'];
        $memberInfo['state'] = ($core_member['state'] === '') ? $member['mepr-address-state'] : $core_member['state'];
        $memberInfo['zip'] = ($core_member['zip'] === '') ? $member['mepr-address-zip'] : $core_member['zip'];
        $memberInfo['pnone'] = ($core_member['phone'] === '') ? $member['mepr_phone'] : $core_member['phone'];
        $memberInfo['mepr_adult_one'] = ($core_member['mepr_adult_one'] === '') ? $member['mepr_adult_one'] : $core_member['mepr_adult_one'];
        $memberInfo['mepr_adult_one'] = ($core_member['mepr_adult_two'] === '') ? $member['mepr_adult_two'] : $core_member['mepr_adult_two'];
        $memberInfo['mepr_adult_one'] = ($core_member['mepr_adult_three'] === '') ? $member['mepr_adult_three'] : $core_member['mepr_adult_three'];

        $runUpdate = False;

        foreach ($memberInfo as $key => $value) {
            if ($value != $core_member[$key]) {
                $runUpdate = True;
                $memberInfo['cardNo'] = $core_member['cardNo'];
                break;
            }
        }
        if ($runUpdate === True) {
            //MemberPressSyncLib::updateCOREmeminfo($connection, $memberInfo);
            MemberPressSyncLib::updateCOREcustdata($connection, $member);
        }


    }

    /*
    *   Pull memberpress transactions and saves them to a temporary table.
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
            //Find Origin.
            $regEx = '/\d{1,4}(?:-[^0-9]{3})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(\s)(\d{2})(?::\d{2})(?::\d{2})|\d{1,4}(?:-\d{1,4})(?:-\d{1,4})(?:-\d{1,4})(?:-\d{4})(?:-\d{2})(?:-\d{2})(\s)(\d{2})(?::\d{2})(?::\d{2})/';
            if ( preg_match($regEx, $transaction['trans_num']) ){
                $transInfo['origin'] = 'CORE';
                $transInfo['core_trans_num'] = $transInfo['trans_num'];
                //echo "Origin: ".$transInfo['origin']."  trans_num: ".$transInfo['trans_num']."\n";
            } else {
                $transInfo['origin'] = 'MemberPress';
                //echo "Origin: ".$transInfo['origin']."  trans_num: ".$transInfo['trans_num']."\n";
            }
            //$this->echoTransaction($transInfo);
            /** Add Transaction to the database **/
            if ($transInfo['status'] !== 'Failed') {
                MemberPressSyncLib::addMPTransaction($connection, $transInfo) ;
            }

        }
        $msg = "\n########### Pull Transactions End ###########\n";
        echo $this->cronMsg($msg);
    }
    
}
