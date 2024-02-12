<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op

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

	parts of this file was adapted from http://sourceforge.net/projects/mysql2sqlite/

*********************************************************************************/
include(dirname(__FILE__).'/../../../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
include_once(dirname(__FILE__).'/../lib/MemberPressSyncLib.php');

class MemberPressSyncTestPage extends FanniePage {

	protected $header = 'Member Press Test Page';
	protected $title = 'Member Press Test';
    private $mpURL ='';
    private $mpKey = '';
    

    public function __construct(){
        parent::__construct();
        $conf = FannieConfig::factory();
        $settings = $conf->get('PLUGIN_SETTINGS');
        $this->mpURL = $settings['mpUrl'];
        $this->mpKey = $settings['mpAPIKey'];
    }

    public function body_content()
    {
        
        $members = MemberPressSyncLib::getMembers($this->mpURL, $this->mpKey);

        //map the exisiting website members logically.
        $originMP = array();
        $originCORE = array();
        $originNM = array();
        foreach ($members as $member) {

            $active_membershipID =  MemberPressSyncLib::getActiveMembershipID($member['active_memberships']);

            $core_member = MemberPressSyncLib::coreMemberByName($this->connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);

            // inital sort of all members in memberpress.
            if($active_membershipID) {
                $originMP[] = $member; //members from member press.
                if (!MemberPressSyncLib::isMapped($this->connection, $member['id'],$core_member['cardNo'])) {
                    MemberPressSyncLib::mapMember($this->connection, $member['id'],$core_member['cardNo'],'','', 'MemberPress');
                }
                
            } elseif ($core_member['cardNo'] !== '') {
                $originCORE[] = $member; //legacy members who are members in core.
                if (MemberPressSyncLib::isMapped($this->connection, $member['id'],$core_member['cardNo'])) {
                    MemberPressSyncLib::mapMember($this->connection, $member['id'],$core_member['cardNo'],null,null, 'CORE');
                }
            } else {
                $originNM[] =$member; //staff & secondary members with accounts.
            }
        }

        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>Users coming from memberpress</th></tr>';
        echo '<tr><th>"MPID"</th><th>"MP First Name"</th><th>"MP Last Name"</th><th>"MP Emai"</th>';
        echo '<th>"CORE CardNo"</th><th>MembershipID</th></tr>';
        foreach ($originMP as $member) {
            $active_membershipID =  MemberPressSyncLib::getActiveMembershipID($member['active_memberships']);
            $core_member = MemberPressSyncLib::coreMemberByName($this->connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);
            echo '<tr><td>Member:'.$member['id'].'</td><td>'.$member['first_name'].'</td><td>'.$member['last_name'].' </td><td>'.$member['email'].'</td>';
            echo '<td>'.$core_member['cardNo'].'</td><td>'.$active_membershipID.'</td></tr>';
        }
        echo '</table>';

        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>Users Coming from CORE Users</th></tr>';
        echo '<tr><th>"MPID"</th><th>"MP First Name"</th><th>"MP Last Name"</th><th>"MP Emai"</th>';
        echo '<th>"CORE CardNo"</th><th>MembershipID</th></tr>';
        foreach ($originCORE as $member) {
            $active_membershipID =  MemberPressSyncLib::getActiveMembershipID($member['active_memberships']);
            $core_member = MemberPressSyncLib::coreMemberByName($this->connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);
            echo '<tr><td>Member:'.$member['id'].'</td><td>'.$member['first_name'].'</td><td>'.$member['last_name'].' </td><td>'.$member['email'].'</td>';
            
            
            echo '<td>'.$core_member['cardNo'].'</td><td>'.$active_membershipID.'</td></tr>';
            //$this->updateCOREMember($member);
        }
        echo '</table>';

        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>Unknown Users</th></tr>';
        echo '<tr><th>"MPID"</th><th>"MP First Name"</th><th>"MP Last Name"</th><th>"MP Emai"</th>';
        echo '<th>"CORE CardNo"</th><th>MembershipID</th></tr>';
        foreach ($originNM as $member) {
            $active_membershipID = MemberPressSyncLib::getActiveMembershipID($member['active_memberships']);
            $core_member = MemberPressSyncLib::coreMemberByName($this->connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);
            echo '<tr><td>Member:'.$member['id'].'</td><td>'.$member['first_name'].'</td><td>'.$member['last_name'].' </td><td>'.$member['email'].'</td>';
            echo '<td>'.$core_member['cardNo'].'</td><td>'.$active_membershipID.'</td></tr>';
        }
        echo '</table>';
        
        // get members
        //curl "https://www.franklincommunity.coop/wp-json/mp/v1/members?page=2&per_page=10" \
        //-H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7"

        //get transactions
        //curl "https://www.franklincommunity.coop/wp-json/mp/v1/transactions?page=2&per_page=10" \
       //-H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7"
        

       /* create member
        $ curl -X POST "https://www.franklincommunity.coop/wp-json/mp/v1/members" \
       -H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7" \
       -d email="kipperdeutsch@gmail.com" \
       -d username="kipperdeutsch@gmail.com" \
       -d first_name=Kipper \
       -d last_name=Deutsch 
       /*

       /* update member
        -X POST "https://www.franklincommunity.coop/wp-json/mp/v1/members/639" \
       -H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7" \
       -d email="kipperdeutsch@gmail.com" \
       -d username="kipperdeutsch@gmail.com" \
       -d first_name=Kipper \
       -d last_name=Deutsch 
       */
    }

    private function updateCOREMember($member) {
        
        // update transactions
        $core_member = MemberPressSyncLib::coreMemberByName($this->connection, $member['id'], $member['first_name'], $member['last_name'], $member['email']);

        $activeMembershipID = MemberPressSyncLib::paymentPlanToMemberShipID($this->connection, $core_member['cardNo']);
        $transactions = $this->getCORETrans($core_member['cardNo'], $activeMembershipID);
        foreach ($transactions as $transaction) {
            if(!MemberPressSyncLib::transIsMapped($this->connection,0,$transaction['trans_num'])){
                //MemberPressSyncLib::mapTrans($connection,$transaction['trans_num'],$transaction['trans_num'],$this->mpURL, $this->mpKey);
                $this->echoTransaction($transaction);
                //MemberPressSyncLib::createTransaction($transaction, $this->mpURL, $this->mpKey);
            }
        }
        $newEmail = ($member['email'] == '') ? $member['email'] : $core_member['email'] ;
        $newFirstName = ($member['first_name'] == '') ? $member['first_name'] : $core_member['FirstName'] ;
        $newLastName = ($member['last_name'] == '') ? $member['last_name'] : $core_member['LastName'] ;
        $newAddress = ($member['address1'] == '') ? $member['address1'] : $core_member['street'] ;
        $newCity = ($member['city'] == '') ? $member['city'] : $core_member['city'] ;
        $newState = ($member['state'] == '') ? $member['state'] : $core_member['state'] ;
        $newZip = ($member['zip'] == '') ? $member['zip'] : $core_member['zip'] ;
        
        // update member info.
        //'cardNo'=>'','FirstName'=>'','LastName'=>'','street'=>'', 'city'=>'','state'=>'','zip'=>'', 'email'=>''
        $memberInfo = array(
            'email' => $newEmail,
            'username' => $member['username'],
            'first_name' => $newFirstName,
            'last_name' => $newLastName,
            'address1' => $newAddress,
            'address2' => '',
            'city' => $newCity,
            'state' => $newState,
            'zip' => $newZip,
            'country' => ''
        );
        //MemberPressSyncLib::updateMember($memberInfo,$member['id'],$this->mpURL, $this->mpKey);
    }

    
    
        //gets all transactions related to a mebmer from the database.
        private function getCORETrans($cardNo, $activeMembershipID) {
            $mapQ = "SELECT p.trans_num, p.stockPurchase, m.memberPressID, tdate as created_at
            FROM core_trans.stockpurchases p 
            LEFT JOIN core_op.MemberPressMemberMap m on p.card_no = m.cardNo
            WHERE p.card_no = ? and p.stockPurchase != 0";
            $prep = $this->connection->prepare($mapQ);
            $result = $this->connection->execute($prep, array($cardNo));
            $ret = array();
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
                    'gateway' => 'CORE_import',
                    'subscription' => 0,
                    'created_at' => 'YYYY-mm-dd hh:mm:ss',
                    'expires_at' => '0000-00-00 00:00:00',
                    'send_welcome_email' => false,
                    'send_receipt_email' => false,
                );
            while ($row = $this->connection->fetch_row($result)) {
                $transNo = $row[0];
                //if the trans_num is not uninque make it unique with member number and date.
                if (!preg_match("\d{1,4}(?:-\d{1,4})(?:-\d{1,4})", $row[0])) {
                    $row[0]  = $cardNo.'-'.$row[0].'-'.$row[3];
                }
                //$expires_at = date("Y-m-d", strtotime("+1 month", $row[3]));
                $expires_at = new DateTime($row[3]);
                $expires_at->modify('+ month');
    
                $trans['trans_num'] = $row[0];
                $trans['amount'] = $row[1];
                $trans['total'] = $row[1];
                $trans['member'] = $row[2];
                $trans['membership'] = $activeMembershipID;
                $trans['created_at'] = $row[3];
                $trans['expires_at'] = $expires_at->format('Y-m-d H:i:s');
                $ret[] = $trans;
            }
            return $ret;
        }


    private function echoTransaction($trans) {
        echo '<tr><table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>transaction'.$trans['trans_num'].'</th></tr><tr>';
        echo $this->loopArray($trans);
        echo '</tr></table></tr>';
    }

    private function loopArray($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<td>';
            $ret .= $value;
            $ret .= '</td>';
        }
        return $ret;
    }
    private function getMPMemberships () {

    }
}

FannieDispatch::conditionalExec(false);