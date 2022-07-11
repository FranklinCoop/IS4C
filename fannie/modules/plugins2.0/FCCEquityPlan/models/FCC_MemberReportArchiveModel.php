<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Coop

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
CREATE TABLE FCC_MonthlyDiscountChanges(
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    `month` date,
    card_no INT(11) NOT NULL,
    oldMemType tinyint(4),
    newMemType tinyint(4)
);

*********************************************************************************/

/**
  @class BatchesModel
*/
class FCC_MemberReportArchiveModel extends BasicModel 
{

    protected $name = "FCC_MemberReportArchive";
    protected $preferred_db = 'op';

    protected $columns = array(
    'monthID' => array('type'=>'INT', 'primary_key'=>True, 'increment'=>True),
    'month' => array('type'=>'DATE'),
    'members_good_standing' => array('type'=>'INT', 'default'=>0),
    'members_not_good_standing' => array('type'=>'INT', 'default'=>0),
    'members_total' => array('type'=>'INT', 'default'=>0),
    'reachable_good_standing' => array('type'=>'INT', 'default'=>0),
    'reachable_not_good_standing' => array('type'=>'INT', 'default'=>0),
    'reachable_total' => array('type'=>'INT', 'default'=>0),
    'unreachable_good_standing' => array('type'=>'INT', 'default'=>0),
    'unreachable_not_good_standing' => array('type'=>'INT', 'default'=>0),
    'unreachable_total' => array('type'=>'INT', 'default'=>0),
    );

    /*
    CREATE TABLE IF NOT EXISTS core_op.FCC_MemberReportArchive (
        monthID INT AUTO_INCREMENT PRIMARY KEY,
        month DATE NOT NULL,
        members_good_standing INT DEFAULT 0,
        members_not_good_standing INT DEFAULT 0,
        members_total INT DEFAULT 0,
        reachable_good_standing INT DEFAULT 0,
        reachable_not_good_standing INT DEFAULT 0,
        reachable_total INT DEFAULT 0,
        unreachable_good_standing INT DEFAULT 0,
        unreachable_not_good_standing INT DEFAULT 0,
        unreachable_total INT DEFAULT 0
    )  ENGINE=INNODB;

    */


    public function doc()
    {
        return '
Depends on:
Nothing

Use:
This table stores archive data for the member report for the bod.
        ';
    }
}

