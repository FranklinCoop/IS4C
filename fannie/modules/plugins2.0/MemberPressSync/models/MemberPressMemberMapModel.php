<?php
/*******************************************************************************

    Copyright 2023 Franklin Community co-op

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

class MemberPressMemberMapModel extends BasicModel {

    protected $name = 'MemberPressMemberMap';
    protected $preferred_db = 'op';

    protected $columns = array(
    'memberPressID' => array('type'=>'INT', 'primary_key'=>True),
    'cardNo' => array('type'=>'INT', 'primary_key'=>true),
    'lastPullDate' => array('type'=>'DATETIME'), //from member press
    'lastPushDate' => array('type'=>'DATETIME'), //too member press
    'origin' => array('type' => 'VARCHAR(55)'),
    );

    protected $unique = array('memberPressID','coreID');
}

