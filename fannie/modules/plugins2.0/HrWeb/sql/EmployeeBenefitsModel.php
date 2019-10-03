<?php

/*******************************************************************************

    Copyright 2017 Whole Foods Co-op

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
        
namespace COREPOS\Fannie\Plugin\HrWeb\sql;
use \BasicModel;

/**
  @class EmployeeBenefitsModel
*/
class EmployeeBenefitsModel extends BasicModel
{

    protected $name = "EmployeeBenefits";

    protected $columns = array(
    'employeeBenefitID' => array('type'=>'INT', 'primary_key'=>true, 'increment'=>true),
    'employeeStatusID' => array('type'=>'INT', 'index'=>true),
    'dateEligibleMedicalDental' => array('type'=>'DATETIME'),
    'dateEligibleRetirement' => array('type'=>'DATETIME'),
    'medicalDentalStatusID' => array('type'=>'INT', 'index'=>true),
    'retirementStatusID' => array('type'=>'INT', 'index'=>true),
    );

}

