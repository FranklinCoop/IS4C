<?php
/*******************************************************************************

    Copyright 2023 Franklin Community coop

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

*********************************************************************************/

/**
  @class MeminfoHistory

*/

class MeminfoHistoryModel extends BasicModel 
{

    protected $name = 'MeminfoHistoryModel';

    protected $preferred_db = 'op';

    protected $columns = array(
        'card_no' => array('type'=>'INT','primary_key'=>True,'default'=>0),
        'last_name' => array('type'=>'VARCHAR(30)'),
        'first_name' => array('type'=>'VARCHAR(30)'),
        'othlast_name' => array('type'=>'VARCHAR(30)'),
        'othfirst_name' => array('type'=>'VARCHAR(30)'),
        'street' => array('type'=>'VARCHAR(255)'),
        'city' => array('type'=>'VARCHAR(20)'),
        'state' => array('type'=>'VARCHAR(2)'),
        'zip' => array('type'=>'VARCHAR(10)'),
        'phone' => array('type'=>'VARCHAR(30)'),
        'email_1' => array('type'=>'VARCHAR(100)'),
        'email_2' => array('type'=>'VARCHAR(50)'),
        'ads_OK' => array('type'=>'TINYINT','default'=>1),
        'modified'=>array('type'=>'DATETIME','ignore_updates'=>true),
        'histDate' => array('type'=>'DATETIME', 'index'=>True)
        );

    /**
      Use this instead of primary key for identifying
      records
    */
    protected $unique = array('CardNo','personNum');

    public function doc()
    {
        return '
Use:
Keepying a daily history of what  is in custdata.
        ';
    }
}

