<?php
/*******************************************************************************

    Copyright 2021 Franklin Community Co-op

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

class FCC_MemberUpdateTask extends FannieTask
{
	public $name = 'FCC Member Update Task';
	public $description = 'Checks Updates Member status after payments, removes virtual coupons.';

	function run(){
        global $FANNIE_OP_DB;
        $TransDB = $this->config->get('TRANS_DB');
        $OpDB = $FANNIE_OP_DB;
        $dbc = FannieDB::get($OpDB);

        $equityToday = "SELECT card_no FROM core_trans.localtemp";


	}


}
