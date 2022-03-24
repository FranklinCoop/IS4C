<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Coop

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
   Limit equity to non chanarge accounts and print a papaer slip on member sales.
****/

class FCCEquityDept extends SpecialDept 
{
    public $help_summary = 'Require cashier confirmation on equity sale exclude non member numbers and print slip.';
    protected $slip_type = 'eqSlip';

    public function handle($deptID,$amount,$json)
    {
        $nonEquityNumbers = array(8001,8002,8015);
        if ($this->session->get("memberID") == "0" || $this->session->get("memberID") == $this->session->get("defaultNonMem")) {
            $this->session->set('strEntered','');
            $this->session->set('boxMsg',_('Equity requires member.<br />Apply member number first'));
            $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php';

            return $json;
        } else if (in_array($this->session->get("memberID"), $nonEquityNumbers)){
            $this->session->set('strEntered','');
            $this->session->set('boxMsg',_('Equity MUST be applied to a non default number.<br />Run in separate transaction.'));
            $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php';

            return $json;
        }


        if ($this->session->get('msgrepeat') == 0) {
            $this->session->set("boxMsg",_("<b>Equity Sale</b><br>please confirm"));
            $this->session->set('boxMsgButtons', array(
                _('Confirm [enter]') => '$(\'#reginput\').val(\'\');submitWrapper();',
                _('Cancel [clear]') => '$(\'#reginput\').val(\'CL\');submitWrapper();',
            ));
            $json['main_frame'] = MiscLib::base_url().'gui-modules/boxMsg2.php?quiet=1';
        }

        return $json;
    }
}

