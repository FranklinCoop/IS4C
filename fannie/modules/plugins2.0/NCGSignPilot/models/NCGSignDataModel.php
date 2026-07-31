<?php
/*******************************************************************************

    Copyright 2026 Franklin Community co-op

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

class NCGSignDataModel extends BasicModel {

    protected $name = 'NCGSignData';
    protected $preferred_db = 'op';

    protected $columns = array(
        'SignSize' => array('type'=>'VARCHAR(30)'),
        'SignType' => array('type'=>'VARCHAR(30)'),
        'start_date' => array('type'=>'DATETIME'),
        'end_date' => array('type'=>'DATETIME'),
        'upc' => array('type'=>'VARCHAR(13)'),
        'sms_upc' => array('type'=>'VARCHAR(13)'),
        'brand' => array('type'=>'VARCHAR(30)'),
        'description' => array('type'=>'VARCHAR(50)'),
        'unitSize' => array('type'=>'VARCHAR(25)'),
        'unitOfMesure' => array('type'=>'VARCHAR(25)'),
        'posPrice' => array('type'=>'MONEY'),
        'signPrice' => array('type'=>'VARCHAR(25)'),
        'priceDevider' => array('type'=>'INT'),
        'multiPrice' => array('type'=>'INT'),
        'unitPrice' => array('type'=>'VARCHAR(25)'),
        'attribute' => array('type' =>'VARCHAR(30)'),
        'id' => array('type'=>'INT','primary_key'=>True,'increment'=>True),

    );
    protected $unique = array('start_date','upc');

}