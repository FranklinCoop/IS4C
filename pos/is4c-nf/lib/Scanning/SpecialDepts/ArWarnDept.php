<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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
use \CoreLocal;

class ArWarnDept extends SpecialDept 
{

    public $help_summary = 'Require cashier confirmation on AR sale';

    // @hintable
    public function handle($deptID,$amount,$json)
    {
        if (CoreLocal::get('msgrepeat') == 0) {
            CoreLocal::set("boxMsg","<b>A/R Payment Sale</b><br>remember to retain you<br>
                reprinted receipt");
            CoreLocal::set('boxMsgButtons', array(
                'Confirm [enter]' => '$(\'#reginput\').val(\'\');submitWrapper();',
                'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
            ));
            $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php?quiet=1';
        }

        return $json;
    }

}

