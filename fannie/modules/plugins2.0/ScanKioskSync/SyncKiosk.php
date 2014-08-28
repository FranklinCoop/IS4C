<?php
/*******************************************************************************

    Copyright 2014 Franklin Community Co-op

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

	parts of this file was adapted from http://sourceforge.net/projects/mysql2sqlite/

*********************************************************************************/
include(dirname(__FILE__).'/../../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class SyncKiosk extends FanniePage {

	protected $header = 'Oncue Scanner Sync';
	protected $title = 'Oncue Scanner Sync';

    public function body_content()
    {
    	return $this->syncKiosk();
    }
	
	public function syncKiosk() {
		global $FANNIE_ROOT;
		$pdoLi = new PDO('sqlite:'.$FANNIE_ROOT.'modules/plugins2.0/db/items.db') or die("can't connect to $liDb");
		$this->makeSQLiteTable($pdoLi);
		$retString = $this->insertTableData($pdoLi);

		//$retString ='';
		$scanners =array('192.168.2.224');
		foreach ($scanners as $scanner) {
			$connection = ftp_connect($scanner);
			if ($connection) {
				$retString .= "Connected to: ".$scanner."\n";
				$login = ftp_login($connection, "admin", "mbtech");
				if ($login) {
					$retString .= "Logged in to: ".$scanner."\n";
					if (ftp_put($connection, "/PermStorage/FranklinCommunity/includes/items.db", $FANNIE_ROOT.'modules/plugins2.0/PriceCheckKiosk/db/items.db',  FTP_BINARY)){
						$retString .= "Price scanner at ". $scanner." synced.\n";
					} else {
						$retString .= "FTP upload to ".$scanner."failed!\n";
		 			}
				ftp_close($connection);
				} else {
					$retString .= "Connection attempt to ".$scanner." failed!\n";
				}
			} else {
				$retString .= "Login attempt to ".$scanner." failed!\n"; 
			}
		}
		
		return $retString;
	}

	function insertTableData($pdoLi) {
		global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $table_data = $this->getTableData();
		
		$retString = '';
		if ($table_data) {
        	while($row = $dbc->fetch_row($table_data)) {
        		$insertQuery = "INSERT INTO items (upc,desc,price,memprice,brand,discounttype)
        		VALUES ('".$row[1]."','".$row[2]."','".$row[3]."','".$row[4]."','".$row[5]."',".$row[6].");";
				//$retString .= $insertQuery."\n";
        		//$retString .= $this->query($insertQuery,$pdoLi);
        	}
		}
		return $retString;
	}

	function makeSQLiteTable($pdoLi) {
		$createQuery = "DROP TABLE items;";
		$createQuery .= "CREATE TABLE items (id INTEGER PRIMARY KEY, upc TEXT, desc TEXT, price TEXT, memprice TEXT, brand TEXT, discounttype INTEGER);";
		$this->query($createQuery, $pdoLi);
	}

	function getTableData() {
		global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
		$q ="SELECT NULL as id, upc, description as `desc`, 
			CASE discounttype 
				WHEN 1 THEN
					IF(scale=1,CONCAT('$',FORMAT(special_price,2),'/lb'),CONCAT('$',FORMAT(special_price,2))) 
				WHEN 2 THEN 
					IF(scale=1,CONCAT('$',FORMAT(normal_price,2),'/lb'), CONCAT('$',FORMAT(normal_price,2))) 
				ELSE 
					IF(scale=1,CONCAT('$',FORMAT(normal_price,2),'/lb'), CONCAT('$',FORMAT(normal_price,2)))  
			END as `price`, 
			CASE discounttype 
					WHEN 1 THEN 
						IF(scale=1,CONCAT('$',FORMAT(special_price,2),'/lb'),CONCAT('$',FORMAT(special_price,2))) 
					WHEN 2 THEN 
						IF(scale=1,CONCAT('$',FORMAT(special_price,2),'/lb'),CONCAT('$',FORMAT(special_price,2))) 
					ELSE 
						IF(scale=1,CONCAT('$',FORMAT(normal_price,2),'/lb'), CONCAT('$',FORMAT(normal_price,2))) 
			END as `memprice`, 
			'' as brand, discounttype FROM core_op.products;";

		$prep = $dbc->prepare_statement($q);
		return $dbc->exec_statement($prep,array());
	}

	function query($qs,$pdo){
		$retString ='';
		if($pdo){
			if(is_array($qs))$lock=1;
			else{$lock=0;$qs=array($qs);}
			if($lock){
				$pdo->exec('PRAGMA synchronous = 0;');
				$pdo->exec('PRAGMA journal_mode = OFF;');
				$pdo->exec('BEGIN;');
			}
			foreach($qs as $q)
				$pdo->exec($q);
			if($lock){
				$pdo->exec('COMMIT;');
				$pdo->exec('PRAGMA synchronous = FULL;');
				$pdo->exec('PRAGMA journal_mode = DELETE;');
			}
			$err=$pdo->errorInfo();
			if(intval($err[0])){
				$retString .= "\nError: \n";
				$retString .= var_dump($q,$err);
				#die;
			}
		}
		return $retString;
	}
}

FannieDispatch::conditionalExec(false);