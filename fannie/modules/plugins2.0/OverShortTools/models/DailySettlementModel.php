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

class DailySettlementModel extends BasicModel {

    protected $name = 'dailySettlement';
    protected $preferred_db = 'plugin:OverShortDatabase';

    protected $columns = array(
    'id' => array('type'=>'INT','primary_key'=>True, 'increment'=>True),
    'date' => array('type'=>'VARCHAR(10)'),
    'lineNo' => array('type'=>'INT'),
    'lineName' => array('type'=>'VARCHAR(50)'),
    'acctNo' => array('type'=>'VARCHAR(30)',),
    'amt' => array('type'=>'MONEY'),
    'count' => array('type'=>'MONEY',),
    'total' => array('type'=>'MONEY',),
    'diff' => array('type'=>'MONEY',),
    'totalRow' => array('type'=>'INT'),
    'diffShow' => array('type'=>'INT'), // the location to display diff
    'diffWith' => array('type'=>'VARCHAR(30'), // the cell to diff with.
    'storeID' => array('type'=>'TINYINT'),
    );

    protected $unique = array('id');
}

