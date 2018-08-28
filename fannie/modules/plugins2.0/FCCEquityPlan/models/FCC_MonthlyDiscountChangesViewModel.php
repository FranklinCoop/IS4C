<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

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
  @class ArHistoryTodayModel
*/
class FCC_MonthlyDiscountChangesViewModel extends ViewModel
{

    protected $name = "FCC_MonthlyDiscountChangesView";
    protected $preferred_db = 'op';

    protected $columns = array(
    'changeID' => array('type'=>'INT'),
    'month' => array('type'=>'DATE'),
    'card_no' => array('type'=>'INT'),
    'LastName' => array('type'=>'VARCHAR(30)'),
    'FirstName' => array('type'=>'VARCHAR(30)'),
    'oldMemType' => array('type'=>'INT'),
    'newMemType' => array('type'=>'INT'),
    );

    public function definition()
    {

        return '
            SELECT m.changeID, m.card_no, c.LastName, c.FirstName, c.memType as oldMemType, m.newMemType
                  FROM FCC_MonthlyDiscountChanges m left join custdata c on m.card_no = c.CardNo
                  WHERE c.personNum =1 order by c.LastName,c.FirstName';
    }

    public function doc()
    {
        return '
Depends on:
* FCC_MonthyDiscountChanges
* custdata.

Use:
  display data for fcc monthy member dicsount editor.
        ';
    }
}

