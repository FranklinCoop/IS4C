<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

class MCC_TransArchiveTask extends FannieTask
{
    public $name = 'MCC Transaction Archiving';

    public $description = 'Archive mcc_trans transaction data so that coupons can\'t double';

    public $default_schedule = array(
        'min' => 30,
        'hour' => 0,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    public function run()
    {
        $getCouponSQL = "SELECT * FROM core_trans.transarchive
                        WHERE upc IN (
                            SELECT concat('00499999', LPAD(coupID,5,0)) AS upc FROM core_op.houseCoupons
                        )";

    }
}

