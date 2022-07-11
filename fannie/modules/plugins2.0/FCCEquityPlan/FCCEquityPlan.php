<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

/**
*/
class FCCEquityPlan extends \COREPOS\Fannie\API\FanniePlugin 
{
    public $plugin_settings = array();

    public $plugin_description = 'Franklin Community Co-op\'s bizzaro equity payment system. It had become less bizzar
                                  but this plugin had evolved to do a bunch of member and equity related stuff, manages
                                  account equity due messages, archives member data for bod reports, has tools for monthly
                                  changes for working members.';

    public function settingChange(){
        global $FANNIE_OP_DB;

        // Creates the database if it doesn't already exist.
        $dbc = FannieDB::get($FANNIE_OP_DB);
        
        $tables = array(
            'FCC_MonthlyDiscountChangesModel',
            'FCC_MonthlyDiscountChangesViewModel',
            'FCC_MemberReportArchiveModel'
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

