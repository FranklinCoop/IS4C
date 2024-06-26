<?php
/*******************************************************************************

    Copyright 2022 Franklin Community Coop

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
 * @class produceSearchListModel
 */
class ProduceSearchListModel extends BasicModel
{

    protected $name = "produceSearchList";
    protected $preferred_db = 'op';

    protected $columns = array(
    'upc' => array('type'=>'VARCHAR(13)', 'primary_key'=>true),
    'store_id' => array('type'=>'INT', 'primary_key'=>true),
    'searchable' => array('type'=>'TINYINT', 'default'=>1),

    );
    
    public function save()
    {
        $stack = debug_backtrace();
        $lane_push = false;
        if (isset($stack[1]) && $stack[1]['function'] == 'pushToLanes') {
            $lane_push = true;
        }

        $saved = parent::save();

        return $saved;
    }

    public function doc()
    {
        return '
Depends on:
* products

Use:
manually assign what lists can show up in department item serach in the pos.

        ';
    }
}

