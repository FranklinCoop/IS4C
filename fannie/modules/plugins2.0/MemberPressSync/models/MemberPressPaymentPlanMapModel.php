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

class MemberPressPaymentPlanMapModel extends BasicModel {

    protected $name = 'MemberPressPaymentPlanMap';
    protected $preferred_db = 'op';

    protected $columns = array(
    'mp_membership_id' => array('type'=>'INT'),
    'paymentPlanID' => array('type'=>'INT', 'primary_key'=>True),
    'mp_name' => array('type'=>'VARCHAR(255)'),
    'paymentPlanName' => array('type'=>'VARCHAR(255)'),
    );

    protected $unique = array('mp_membership_id','paymentPlanID');
}

