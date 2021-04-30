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

class FCC_VirtualCouponTask extends FannieTask
{
	public $name = 'FCC Virtual Coupon Task';
	public $description = 'Caluclates Virtual Coupons';

	function run(){
        global $FANNIE_OP_DB;
        $TransDB = $this->config->get('TRANS_DB');
        $OpDB = $FANNIE_OP_DB;
        $dbc = FannieDB::get($OpDB);

        // delete expired entries
        $expire = $dbc->prepare("
            DELETE FROM core_op.houseVirtualCoupons
            WHERE end_date <= (SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS yesterday_date)"
        );
        $execute = $dbc->execute($expire,array());

        //get coupons ID and UPCs
        $coupQ = "SELECT coupID 
                  FROM {$OpDB}.houseCoupons 
                  WHERE memberOnly =1 AND `limit` = 1 AND startDate <= NOW() 
                  AND endDate >= (SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY) AS yesterday_date)";
        $coupP = $dbc->prepare($coupQ);
        $results = $dbc->execute($coupP,array());
        $couponIDs = '';
        $couponUPCs = '';
        while ($row = $dbc->fetch_row($results)) {
            if($couponIDs != '') {
                $couponIDs .= ',';
                $couponUPCs .= ',';
            }
            $couponIDs .= $row[0];
            $couponUPCs .= '00499999'.str_pad($row[0], 5, '0',STR_PAD_LEFT);
        }

        //find valid coupons
        $query = "SELECT p.cardNo AS card_no, c.coupID, c.description, c.startDate AS start_date, c.endDate AS end_date
                FROM {$OpDB}.custdata p
                JOIN (SELECT * FROM {$OpDB}.houseCoupons WHERE coupID IN (?)) AS c
                WHERE p.memType IN (1,3,5,6,8,9,10) AND p.personNum =1
                AND p.cardNo NOT IN (
                    SELECT card_no FROM {$TransDB}.dlog_90_view 
                    WHERE upc IN (?) AND trans_status not IN ('V','X')
                    GROUP BY card_no
                )";
        $prep = $dbc->prepare($query);
        $results = $dbc->execute($prep,array($couponIDs, $couponUPCs));

        //save the results
        while ($row = $dbc->fetch_row($results)) {
            echo "adding Coupon\n";
            $virtCoup = new HouseVirtualCouponsModel($dbc);
            $virtCoup->card_no($row['card_no'], "=");
            $virtCoup->coupID($row['coupID'], "=");
            echo $row['card_no']." ".$row['coupID'];
            if (!$virtCoup->find()) {
                $virtCoup = new HouseVirtualCouponsModel($dbc);
                $virtCoup->card_no($row['card_no']);
                $virtCoup->coupID($row['coupID']);
                $virtCoup->description($row['description']);
                $virtCoup->start_date($row['start_date']);
                $virtCoup->end_date($row['end_date']);
                $saved = $virtCoup->save();
            }
        }


	}


}
