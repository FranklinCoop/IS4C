<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

require(dirname(__FILE__) . '/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}

class SettlementModule {
    protected $numCols = 0;
    protected $colNames = array('1','2','3');
    protected static $colPrint = array(true,true,false);
    protected $rowFormat = array();
    protected $numRows = 0;
    protected $rowData;

public function getRowFormat(){
    return $this->rowFormat;
}

public static function getColPrint(){
    return static::$colPrint;
}

public function getNumCols() {
    return $this->numCols;
}


public function getNumRows() {
    return $this->numRows;
}

public function getColNames() {
    return $this->colNames;
}

public function getRowData() {
    return $this->rowData;
}

public function setRowData($newData){
    $this->rowData = $newData;
}

}