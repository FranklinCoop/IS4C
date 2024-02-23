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

class MemberPressTransactionsModel extends BasicModel {

    protected $name = 'MemberPressTransactions';
    protected $preferred_db = 'op';

    protected $columns = array(
    'membership' => array('type'=>'INT'),
    'member' => array('type'=>'INT'),
    'coupon' => array('type'=>'INT'),
    'subscription' => array('type'=>'INT'),
    'id' => array('type'=>'INT', 'primary_key'=>true,),
    'amount' => array('type'=>'MONEY'),
    'total' => array('type'=>'MONEY'),
    'tax_amount' => array('type'=>'MONEY'),
    'tax_reversal_amount' => array('type'=>'MONEY'),
    'tax_rate' => array('type'=>'FLOAT'),
    'tax_desc' => array('type'=>'VARCHAR(55)'),
    'tax_class' => array('type'=>'VARCHAR(55)'),
    'trans_num' => array('type'=>'VARCHAR(100)'),
    'status' => array('type'=>'VARCHAR(55)'),
    'txn_type' => array('type'=>'VARCHAR(55)'),
    'gateway' => array('type'=>'VARCHAR(55)'),
    'prorated' => array('type'=>'INT'),
    'created_at' => array('type'=>'DATETIME'),
    'expires_at' => array('type'=>'DATETIME'),
    'corporate_account_id' => array('type'=>'INT'),
    'parent_transaction_id' => array('type'=>'INT'),
    'order_id' => array('type'=>'INT'),
    'tax_compound' => array('type'=>'INT'),
    'tax_shipping' => array('type'=>'INT'),
    'response' => array('type'=>'VARCHAR(255)'),
    'rebill' => array('type'=>'TINYINT'),
    'subscription_payment_index' => array('type'=>'TINYINT'),
    'core_trans_num' => array('type'=>'VARCHAR(50)'),
    'origin' => array('type'=>'VARCHAR(55)'),
    
    );
    protected $unique = array('id','trans_num');
/**
  "membership": {},
  "member": {},
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


    
}

