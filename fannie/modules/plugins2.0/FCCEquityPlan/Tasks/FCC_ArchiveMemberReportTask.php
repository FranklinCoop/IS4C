<?php
/*******************************************************************************

    Copyright 2022 Franklin Community Co-op

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

class FCC_ArchiveMemberReportTask extends FannieTask
{
	public $name = 'FCC Archive Monthly Member Report Task';
	public $description = 'Archives a bunch of data on the first that the board wants reported
                            The data they want from the member databases changes as edits are made 
                            so this saves a snap shot in time.';

    public $default_schedule = array(
        'min' => 30,
        'hour' => 1,
        'day' => '1',
        'month' => '*',
        'weekday' => '*',
        );                       
	function run(){
		global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $data = array();

        //members in good standing
        $query = "SELECT count(*) AS gsMembers FROM core_op.custdata WHERE personNum =1 AND memType IN (1,3,5,6,8,9,10)";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];

        //members not in good standing
        $query = "SELECT count(*) AS ngsMembers FROM core_op.custdata WHERE personNum =1 AND memType =12";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];

        //total members
        $query = "SELECT count(*) AS totalMembers FROM core_op.custdata WHERE personNum =1 AND memType IN (1,3,5,6,8,9,10,12)";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //reachable in good stnading
        $query = "SELECT count(*) AS gsReachable FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10) AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //reachable not in good standing
        $query = "SELECT count(*) AS ngsReachable FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType = 12 AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //total reachable
        $query = "SELECT count(*) AS reachableTotal FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10,12) AND (i.street IS NOT NULL AND i.street NOT IN ('','*','.','\n'))";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //unreachable in good standing
        $query = "SELECT count(*) AS gsUnreach FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10) AND ((i.street IN('','*','.','\n') OR i.street IS NULL))";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //unreachable not in good standing
        $query = "SELECT count(*) AS ngsUnreach FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType = 12 AND (i.street IN('','*','.','\n') OR i.street IS NULL)";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];
        //unreachable total
        $query = "SELECT count(*) AS totalUnreach FROM core_op.custdata c
                  LEFT JOIN core_op.meminfo i ON c.cardNo = i.card_no
                  WHERE c.personNum =1 AND c.memType IN (1,3,5,6,8,9,10,12) AND (i.street IN('','*','.','\n') OR i.street is NULL)";
        $prep = $dbc->prepare($query);
		$results = $dbc->execute($prep,array());
        $row = $dbc->fetch_row($results);
        $data[] = $row[0];

        //load load snapshop into table
        $query = "INSERT INTO core_op.FCC_MonthlyMemberReportModel (`month`,members_good_standing,members_not_good_standing,members_total,
                  reachable_good_standing,reachable_not_good_standing,reachable_total,unreachable_good_standing,unreachable_not_good_standing,unreachable_total)
                  VALUES(NOW(),?,?,?,?,?,?,?,?,?);";
        $prep = $dbc->prepare($query);
        $results = $dbc->execute($prep,$data);
        $row = $dbc->fetch_row($results);

	}

}