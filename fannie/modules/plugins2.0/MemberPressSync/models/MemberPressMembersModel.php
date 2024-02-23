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

class MemberPressMembersModel extends BasicModel {

    protected $name = 'MemberPressMembers';
    protected $preferred_db = 'op';

    protected $columns = array(
        'id' => array('type'=>'INT', 'primary_key'=>true,),
        'email' => array('type'=>'VARCHAR(100)'),
        'username' => array('type'=>'VARCHAR(100)'),
        'nicename' => array('type'=>'VARCHAR(100)'),
        'url' => array('type'=>'VARCHAR(100)'),
        'message' => array('type'=>'VARCHAR(100)'),
        'registered_at' => array('type'=>'DATETIME'),
        'first_name' => array('type'=>'VARCHAR(100)'),
        'last_name' => array('type'=>'VARCHAR(100)'),
        'display_name' => array('type'=>'VARCHAR(100)'),
        'active_memberships' => array('type'=>'INT'),
        'active_txn_count' => array('type'=>'INT'),
        'expired_txn_count' => array('type'=>'INT'),
        'trial_txn_count' => array('type'=>'INT'),
        'sub_count' => array('type'=>'INT'),
        'login_count' => array('type'=>'INT'),
        'first_txn' => array('type'=>'INT'),
        'latest_txn' => array('type'=>'INT'),
        'mepr-address-one' => array('type'=>'VARCHAR(55)'),
        'mepr-address-two' => array('type'=>'VARCHAR(55)'),
        'mepr-address-city' => array('type'=>'VARCHAR(55)'),
        'mepr-address-state' => array('type'=>'VARCHAR(55)'),
        'mepr-address-zip' => array('type'=>'VARCHAR(55)'),
        'mepr-address-country' => array('type'=>'VARCHAR(55)'),
        'mepr_phone' => array('type'=>'VARCHAR(55)'),
        'mepr_email' => array('type'=>'VARCHAR(100)'),
        'mepr_how_would_you_like_to_receive_information_about_the_co_op' => array('type'=>'VARCHAR(55)'),
        'mepr_adult_one' => array('type'=>'VARCHAR(55)'),
        'mepr_adult_two' => array('type'=>'VARCHAR(55)'),
        'mepr_adult_three' => array('type'=>'VARCHAR(55)'),
        'recent_transactions' => array('type'=>'VARCHAR(100)'),
        'recent_subscriptions' => array('type'=>'VARCHAR(100)'),
        'origin' => array('type'=>'VARCHAR(55)'),
        'cardNo' => array('type'=>'INT'),
    
    
    );
    protected $unique = array();
/**
{
  "id": 645,
  "email": "wilderbrookfarm399@gmail.com",
  "username": "wilderbrookfarm399@gmail.com",
  "nicename": "wilderbrookfarm399gmail-com",
  "url": "",
  "message": "",
  "registered_at": "2023-11-08 14:27:09",
  "first_name": "JOHN",
  "last_name": "HOFFMAN",
  "display_name": "John Hoffman",
  "active_memberships": [],
  "active_txn_count": "0",
  "expired_txn_count": "4",
  "trial_txn_count": "0",
  "sub_count": null,
  "login_count": "0",
  "first_txn": {},
  "latest_txn": {},
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
  "recent_transactions": [],
  "recent_subscriptions": []
}
 */


    
}

