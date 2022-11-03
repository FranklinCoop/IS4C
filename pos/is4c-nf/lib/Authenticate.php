<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

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

namespace COREPOS\pos\lib;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\CoreState;

/**
  @class Authenticate
  Functions for user authentication
*/
class Authenticate 
{

/**
  Authenticate an employee by password
  @param $password password from employee table
  @return True or False

  If no one is currently logged in, any valid
  password will be accepted. If someone is logged
  in, then only passwords for that user <i>or</i>
  a user with frontendsecurity >= 30 in the
  employee table will be accepted.
*/
static public function checkPassword($password)
{
    $password = strtoupper($password);
    $password = str_replace("'", "", $password);
    $password = str_replace(",", "", $password);
    $paswword = str_replace("+", "", $password);

    if ($password == "TRAINING") {
        $password = 9999; // if password is training, change to '9999'
    }

    $query = "select LoggedIn,CashierNo from globalvalues";
    $dbg = Database::pDataConnect();
    $result = $dbg->query($query);
    $row = $dbg->fetchRow($result);
    $store_id = (CoreLocal::get('store_id'); 

    if ($row["LoggedIn"] == 0) {
        $queryq = '
            SELECT e.emp_no, 
                e.FirstName, 
                e.LastName, '
                . $dbg->yeardiff($dbg->now(),'e.birthdate') . ' AS e.age
            FROM employees e
            JOIN StoreEmployeeMap m on e.emp_no = m.empNo
            WHERE e.EmpActive = 1 
                AND e.CashierPassword = ?
                AND m.storeID = ?';
        $prepq = $dbg->prepare($queryq);
        $resultq = $dbg->execute($prepq, array($password,$store));
        $numRows = $dbg->num_rows($resultq);

        if ($numRows > 0) {
            $rowq = $dbg->fetchRow($resultq);

            Database::loadglobalvalues();

            $transno = Database::gettransno($rowq["emp_no"]);
            $globals = array(
                "CashierNo" => $rowq["emp_no"],
                "Cashier" => $rowq["FirstName"]." ".substr($rowq["LastName"], 0, 1).".",
                "TransNo" => $transno,
                "LoggedIn" => 1
            );
            Database::setglobalvalues($globals);

            CoreState::cashierLogin($transno, $rowq['age']);

        } elseif ($password == 9999) {
            Database::loadglobalvalues();

            $transno = Database::gettransno(9999);
            $globals = array(
                "CashierNo" => 9999,
                "Cashier" => _("Training Mode"),
                "TransNo" => $transno,
                "LoggedIn" => 1
            );
            Database::setglobalvalues($globals);

            CoreState::cashierLogin($transno, 0);
        } else {
            return False;
        }
    } else {
        // longer query but simpler. since someone is logged in already,
        // only accept password from that person OR someone with a high
        // frontendsecurity setting
        $querya = '
            SELECT e.emp_no, 
                e.FirstName, 
                e.LastName, '
                . $dbg->yeardiff($dbg->now(),'e.birthdate') . ' AS age
            FROM employees e
            JOIN StoreEmployeeMap m on e.emp_no = m.empNo
            WHERE e.EmpActive = 1 
                AND (e.frontendsecurity >= 30 OR e.emp_no = ?)
                AND (e.CashierPassword = ? OR e.AdminPassword = ?)
                AND m.storeID = ?';
        $args = array($row['CashierNo'], $password, $password,$store_id);
        $prepa = $dbg->prepare($querya);
        $resulta = $dbg->execute($prepa, $args);

        $numRows = $dbg->num_rows($resulta);

        if ($numRows > 0) {

            Database::loadglobalvalues();
            $rowa = $dbg->fetch_row($resulta);
            CoreState::cashierLogin(False, $rowa['age']);
        } elseif ($row["CashierNo"] == "9999" && $password == "9999") {
            Database::loadglobalvalues();
            CoreState::cashierLogin(False, 0);
        } else {
            return false;
        }
    }

    return true;
}

static public function checkPermission($password, $level)
{
    $emp = self::getEmployeeByPassword($password);
    if ($emp !== false && $emp['frontendsecurity'] >= $level) {
        return true;
    }

    return false;
}

static public function getEmployeeByPassword($password)
{
    $dbc = Database::pDataConnect();
    $store_id = (CoreLocal::get('store_id');
    $prep = $dbc->prepare('
        SELECT *
        FROM employees e
        JOIN StoreEmployeeMap m on e.emp_no = m.empNo
        WHERE e.EmpActive=1 
            AND (e.CashierPassword=? OR e.AdminPassword=?)
            AND m.storeID = ?');
    return $dbc->getRow($prep, array($password, $password,$store));
}

static public function getEmployeeByNumber($emp_no)
{
    $dbc = Database::pDataConnect();
    $prep = $dbc->prepare('
        SELECT *
        FROM employees
        WHERE EmpActive=1 
            AND emp_no=?');
    return $dbc->getRow($prep, array($emp_no));
}

static public function getPermission($emp_no)
{
    $emp = self::getEmployeeByNumber($emp_no);
    if ($emp !== false) {
        return $emp['frontendsecurity'];
    }

    return 0;
}

} // end class Authenticate

