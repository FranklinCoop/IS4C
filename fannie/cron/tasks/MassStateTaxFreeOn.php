<?php
/*******************************************************************************

    Copyright 2014 Franklin Community Co-op

    This file is part of Fannie.

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
  @class FannieTask

  Base class for scheduled tasks
*/
class MassStateTaxFreeOn extends FannieTask 
{
    public $name = 'Mass State Tax Free On';

    public $description = 'Used for tax free holidays. Switches the sales tax rate to 0 on each lane. 
Server side tax table remains the same. So that it can be used to reset the tax when the holiday is over.
Make sure to set MassStateTaxFreeOff to run to reenable taxes.';    

    public $default_schedule = array(
        'min' => 0,
        'hour' => 0,
        'day' => 1,
        'month' => 1,
        'weekday' => '*',
    );

    protected $error_threshold  = 99;

    const TASK_NO_ERROR         = 0;
    const TASK_TRIVIAL_ERROR    = 1;
    const TASK_SMALL_ERROR      = 2;
    const TASK_MEDIUM_ERROR     = 3;
    const TASK_LARGE_ERROR      = 4;
    const TASK_WORST_ERROR      = 5;

    public function setThreshold($t)
    {
        $this->error_threshold = $t;
    }

    /**
      Implement task functionality here
    */
    public function run()
    {
		global $FANNIE_OP_DB, $FANNIE_TRANS_DB;
		$FANNIE_LANES = FannieConfig::config('LANES');
        $dbc = FannieDB::get($FANNIE_TRANS_DB);
		//tax off server
        $query = 'UPDATE core_op.taxrates SET rate = ? WHERE id = ?';
        $args = array(0.0, 1);//asummes sales tax is id 1 change as needed
        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep,$args,$lane['trans']);
        
        for ($i = 0; $i < count($FANNIE_LANES); $i++) {
            if (isset($FANNIE_LANES[$i]['offline']) && $FANNIE_LANES[$i]['offline']) {
                continue;
            }
            $lane = $FANNIE_LANES[$i];
		    $dbc->addConnection($lane['host'],$lane['type'],$lane['trans'],$lane['user'],$lane['pw']);
		    if ($dbc->connections[$lane['trans']] === False){
		        echo cron_msg('Cannot connect to '.$lane['host']);
				echo 'Cannot connect to '.$lane['host'].'\n';
		        continue;
		    }
			
		    if (!$dbc->table_exists('taxrates', $lane['trans'])) {
		        echo cron_msg('No tacrates table on: '.$lane['trans']);
                echo 'No Tax Rates Table on:  '.$lane['trans'].'\n';
                continue;
		    }
			
			$query = 'UPDATE taxrates SET rate = ? WHERE id = ?';
			
			$prep = $dbc->prepare($query);
			$result = $dbc->execute($prep,$args,$lane['trans']);
			
			
		}
    }

    /**
      Format message with date information
      and task's class name
      @param $str message string
      @param $severity [optional, default zero] message importance
      @return formatted string
    */
    public function cronMsg($str, $severity=0)
    {
        $info = new ReflectionClass($this);
        $msg = date('r').': '.$info->getName().': '.$str."\n";

        // raise message into stderr
        if ($severity >= $this->error_threshold) {
            file_put_contents('php://stderr', $msg, FILE_APPEND);
        }

        return $msg;
    }
}

if (php_sapi_name() === 'cli' && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {

    if ($argc < 2) {
        echo "Usage: php FannieTask.php <Task Class Name>\n";    
        exit;
    }

    include(dirname(__FILE__).'/../config.php');
    include(dirname(__FILE__).'/FannieAPI.php');

    // prepopulate autoloader
    $preload = FannieAPI::listModules('FannieTask');

    $class = $argv[1];
    if (!class_exists($class)) {
        echo "Error: class '$class' does not exist\n";
        exit;
    }

    $obj = new $class();
    if (!is_a($obj, 'FannieTask')) {
        echo "Error: invalid class. Must be subclass of FannieTask\n";
        exit;
    }

    if (isset($FANNIE_TASK_THRESHOLD) && is_numeric($FANNIE_TASK_THRESHOLD)) {
        $obj->setThreshold($FANNIE_TASK_THRESHOLD);
    }

    $obj->run();
}

