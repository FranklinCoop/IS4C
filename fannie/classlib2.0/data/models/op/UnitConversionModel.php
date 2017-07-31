<?php
/*******************************************************************************

    Copyright 2017 Franklin Community Coop

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

class UnitConversionModel extends BasicModel {

    protected $name = 'UnitConversion';
    protected $preferred_db = 'op';
    
    protected $columns = array(
        'id' => array('type'=>'INT(11)','primary_key'=>true),
        'unit_name' => array('type'=>'VARCHAR(10)', 'default' => NULL),
        'unit_std' => array('type'=>'VARCHAR(10)', 'default' => NULL),
        'rate' => array('type' => 'DOUBLE', 'default' => NULL),
    );

        public function doc()
    {
        return '
Depends on:
*Nothing

Use:
For quickly switching converting between units for shelf tag generation.
        ';
    }
}


/*
$CREATE['op.unitConversion'] = "
    CREATE TABLE batchBarcodes (
        `id` int(11) NOT NULL auto_increment,
        `unit_name` varchar(10) default NULL,
        `unit_std` varchar(10) default NULL,
        `rate` double default NULL,
        PRIMARY KEY (`id`)
    )
";

if ($dbms == "MSSQL"){
    $CREATE['op.unitConversion'] = "
        CREATE TABLE [unitConversion] (
            [id] IDENTITY (1, 1) NOT NULL,
            [unit_name] [varchar] (10) COLLATE SQL_Latin1_General_CP1_CI_AS NULL ,
            [unit_std] [varchar] (10) COLLATE SQL_Latin1_General_CP1_CI_AS NULL ,
            [rate] [double] NULL,
            PRIMARY KEY ([id])
        )
    ";
}

*/