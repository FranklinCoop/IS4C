<?php
/*******************************************************************************

    Copyright 2023 Memberpress Sync

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
    include(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

/**
*/
class MemberPressSync extends \COREPOS\Fannie\API\FanniePlugin 
{
    public $plugin_settings = array(
    'mpUrl' => array('default'=>'', 'label'=>'MemberPress API URL',
            'description'=>'URL for the memberPress REST API'), 
    'mpAPIKey' => array('default'=>'', 'label'=>'Member Press API KEY'),
    'mpUser' => array('default'=>'', 'label' => 'User name for functions if they require user authentication.'),
    'mpPassword' => array('default'=>'', 'label'=> 'password for api function that require use authentication.')
    //'WooSecret' => array('default'=>'', 'label'=>'Woo Consumer Secret'),
    );

    public $plugin_description = 'A plugin for syncing member info with MemberPress.';
    public function settingChange(){
        global $FANNIE_PLUGIN_SETTINGS;

        $db_name = 'core_op';
        if (empty($db_name)) return;

        // Creates the database if it doesn't already exist.
        $dbc = FannieDB::get($db_name);
        
        $tables = array(
            'MemberPressMemberMap',
            'MemberPressTransactionMap',
            'MemberPressPaymentPlanMap'
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