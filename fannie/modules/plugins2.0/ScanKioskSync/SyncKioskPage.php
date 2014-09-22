<?php
/*******************************************************************************

    Copyright 2014 Franklin Community Co-op

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

	parts of this file was adapted from http://sourceforge.net/projects/mysql2sqlite/

*********************************************************************************/
include(dirname(__FILE__).'/../../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class SyncKioskPage extends FanniePage {

	protected $header = 'Scanner Kiosk Sync';
	protected $title = 'Scanner Kiosk Sync';

    public function body_content()
    {
    	global $FANNIE_PLUGIN_SETTINGS;
		$syncAgent = new $FANNIE_PLUGIN_SETTINGS['KioskModule'];
		return $syncAgent->syncKiosk();
    }
}

FannieDispatch::conditionalExec(false);