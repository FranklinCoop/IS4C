<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Coop

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
CREATE TABLE FCC_MonthlyDiscountChanges(
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    `month` date,
    card_no INT(11) NOT NULL,
    oldMemType tinyint(4),
    newMemType tinyint(4)
);

*********************************************************************************/

/**
  @class BatchesModel
*/
use COREPOS\pos\lib\models\BasicModel;

class FoodBankCouponModel extends BasicModel 
{

    protected $name = "FoodBankCoupon";
    protected $preferred_db = 'op';

    protected $columns = array(
    'id' => array('type'=>'INT', 'primary_key'=>True, 'increment'=>True),
    'item_upc' => array('type'=>'VARCHAR(13)'),
    'coupon_upc' => array('type'=>'VARCHAR(13)'),
    'sale_plu' => array('type'=>'VARCHAR(13)'),
    );

    protected $unique = array('id');


    public function doc()
    {
        return '
Depends on:
Nothing

Use:
This table stores a map of coupons to upcs to sales plus for a suspended items program
The upc is the item, the sales plu is applied when a customer wants to buy a coupon for
the food bank, and the coupons are given out by the food bank and can be redeemed for an
item from the same category.
        ';
    }
}

