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

use COREPOS\pos\plugins\Plugin;
use COREPOS\pos\lib\Database;

class FCCFoodBankCoupons extends Plugin 
{

    public $plugin_description = 'allows a customer to buy two of an item to generate a coupon for
                            the food bank to give to those in need.';


    public $plugin_settings = array();
    // create databases from models;
    public function pluginEnable()
    {
        global $OP_DB;
        $dbc = Database::pDataConnect();
        
        $tables = array(
            'FoodBankCoupon'
        );

        foreach($tables as $t){
            $model_class = $t.'Model';
            if (!class_exists($model_class))
                include_once(dirname(__FILE__).'/models/'.$model_class.'.php');
            $instance = new $model_class($dbc);
            $instance->create();        
        }
    }

}

