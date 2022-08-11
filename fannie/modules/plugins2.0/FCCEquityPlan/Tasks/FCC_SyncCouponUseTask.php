<?php
/*******************************************************************************

    Copyright 2019 Franklin Community Co-op

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

class FCC_SyncCouponUseTask extends FannieTask
{
	public $name = 'FCC Sync Coupons Task';
	public $description = 'Pulls the coupon use data back to MCC transaction DB so that people can\'t reuse them.';

	function run(){
		global $FANNIE_OP_DB, $FANNIE_TRANS_DB;
        $TransDB = $this->config->get('TRANS_DB');
        $OpDB = $FANNIE_OP_DB;
        $dbc = FannieDB::get($TransDB);
        $dbc->addConnection('192.168.3.200','MYSQLI','mcc_trans',
            'root',$this->config->get('FANNIE_SERVER_PW'));

        $turncateQuery = "TRUNCATE TABLE mcc_trans.transarchive";
        $prep = $dbc->prepare($turncateQuery);
        $truncateData = $dbc->execute($prep,array());
        $queryCoupons = "SELECT * FROM core_trans.transarchive 
                  WHERE upc IN ('0049999900246','0049999900249','0049999900261','0049999900245')";
        $insertQuery = "INSERT INTO transarchive ";

        $ret = $dbc->transfer($FANNIE_TRANS_DB, $queryCoupons,'mcc_trans', $insertQuery);
        echo $this->cronMsg("Inserted:".$ret);
        
	}


}
