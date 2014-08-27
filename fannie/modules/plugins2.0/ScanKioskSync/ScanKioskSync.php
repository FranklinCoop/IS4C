<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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
global $FANNIE_ROOT;
if (!class_exists('FannieAPI'))
  include($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class ScanKioskSync extends FanniePlugin 
{

    /**
      Desired settings. These are automatically exposed
      on the 'Plugins' area of the install page and
      written to ini.php
    */
    public $plugin_settings = array();
    //'example1' => array('default'=>'','label'=>'Setting #1',
      //      'description'=>'Text goes here'),
    //'example2' => array('default'=>1,
      //      'options'=>array('Yes'=>1,'No'=>0)
       // )
    //);

    public $plugin_description = 'Plugin to sync customer facing scanners.
	There is likely to be other configureation concerns outside of fannie to get this working.
	For example the oncue sacnners we use need sqllte to be installed on the server and a script to convert
	from mysql to sqlite. And there is also the configuration of the scanners hardware it\'self.';

}
