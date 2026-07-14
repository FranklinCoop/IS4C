<?php
/*******************************************************************************

    Copyright 2026 Franklin Community Coop

*********************************************************************************/

namespace COREPOS\Fannie\Plugin\NCGSignPilot  {
use COREPOS\Fannie\API\FanniePlugin;
/**
 * 
*/
class NCGSignPilot extends \COREPOS\Fannie\API\FanniePlugin 
{


    /**
      Desired settings. These are automatically exposed
      on the 'Plugins' area of the install page and
      written to ini.php
    */
    public $plugin_settings = array(
    );

    public $plugin_description = 'Plugin for printing Signs in the NCG Sign Pilot Program';

    public function settingChange(){
        global $FANNIE_PLUGIN_SETTINGS;

        $db_name = 'core_op';
        if (empty($db_name)) return;

        // Creates the database if it doesn't already exist.
        $dbc = FannieDB::get($db_name);
        
        $tables = array(
            'NCGSignData'
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
}
