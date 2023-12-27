<?php
/*******************************************************************************

    Copyright 2023 Franklin Community coop

    This file is part of CORE-POS.

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

/**
  @class CustdataModel

*/

class custdataHistoryModel extends BasicModel 
{

    protected $name = 'custdataHistory';

    protected $preferred_db = 'op';

    protected $columns = array(
    'CardNo' => array('type'=>'INT','index'=>True),
    'personNum' => array('type'=>'TINYINT'),
    'LastName' => array('type'=>'VARCHAR(30)','index'=>True),
    'FirstName' => array('type'=>'VARCHAR(30)'),
    'CashBack' => array('type'=>'MONEY'),
    'Balance' => array('type'=>'MONEY'),
    'Discount' => array('type'=>'SMALLINT'),
    'MemDiscountLimit' => array('type'=>'MONEY','default'=>0),
    'ChargeLimit' => array('type'=>'MONEY','default'=>0),
    'ChargeOk' => array('type'=>'TINYINT','default'=>0),
    'WriteChecks' => array('type'=>'TINYINT','default'=>1),
    'StoreCoupons' => array('type'=>'TINYINT','default'=>1),
    'Type' => array('type'=>'VARCHAR(10)','default'=>"'PC'"),
    'memType' => array('type'=>'TINYINT'),
    'staff' => array('type'=>'TINYINT','default'=>0),
    'SSI' => array('type'=>'TINYINT','default'=>0),
    'Purchases' => array('type'=>'MONEY','default'=>0),
    'NumberOfChecks' => array('type'=>'SMALLINT','default'=>0),
    'memCoupons' => array('type'=>'INT','default'=>1),
    'blueLine' => array('type'=>'VARCHAR(50)'),
    'Shown' => array('type'=>'TINYINT','default'=>1),
    'LastChange' => array('type'=>'DATETIME'),
    'id' => array('type'=>'INT','primary_key'=>True,'default'=>0,'increment'=>True),
    'histDate' => array('type'=>'DATETIME', 'index'=>True)
    );

    /**
      Use this instead of primary key for identifying
      records
    */
    protected $unique = array('CardNo','personNum');

    public function doc()
    {
        return '
Use:
Keepying a daily history of what  is in custdata.
        ';
    }
}

