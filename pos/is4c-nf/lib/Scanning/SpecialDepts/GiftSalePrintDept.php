<?php
/*******************************************************************************

    Copyright 2024 Franklin Community Coop

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
namespace COREPOS\pos\lib\Scanning\SpecialDepts;
use COREPOS\pos\lib\Scanning\SpecialDept;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\Database;

/****
   Print a slip for giftcard sales;
****/

class GiftSalePrintDept extends SpecialDept 
{
    public $help_summary = 'Printing a slip for giftcard sales instead of a full duplicate receipt';
    protected $slip_type = 'gsSlip';

    public function handle($deptID,$amount,$json)
    {
        //$this->session->set('autoReprint',1);

        return $json;
    }
}

