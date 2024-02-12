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
class MemberPressPushTask extends FannieTask 
{
    public $name = 'For pushing info into Memberpress';

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
        
        /*
            Pull memberships from memberpress, map them and sort by origin.
        */
        
        /*
            Pull transactions for Member Press members, match them
            with fannie records and map them.
        */
        //echo "\n#*#*#*#* VARDUMP \$initMembers #*#*#*#*\n".var_dump($initMembers)."\n\n#*#*#*#* END VARDUMP #*#*#*#*\n";
  

       /***
        * Pull Memberships from memberpress and try to match them.
        * Add them if they don't match.
        */
        /***
        * Pull Transactions from Member Press and try to match them;
        */
        /***
        * Push Members to MemberPress
        * including secondary members and first transaction.
        */
        /***
        * Push Transactions to MemberPress
        */
    }





    
}
