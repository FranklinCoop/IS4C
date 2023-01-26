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

namespace COREPOS\pos\lib\TotalActions;
use \CoreLocal;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\TransRecord;
use COREPOS\pos\lib\MiscLib;
/**
  @class MemTotalAction
  Define an action that is called when a member
  number is applied
*/
class MemTotalAlertAction extends TotalAction
{
    protected $card_no = false;
    protected $message = false;

    public function setMember($id)
    {
        $this->card_no = $id;
    }

    public function setMessage($msg)
    {
        $this->message = $msg;
    }

    /**
      Apply action
      @return [boolean] true if the action
        completes successfully (or is not
        necessary at all) or [string] url
        to redirect to another page for
        further decisions/input.
    */
    public function apply()
    {
                echo("<script>console.log('memberAction');</script>");
                //MemberLib::chargeOk();
        //if (CoreLocal::get("balance") < CoreLocal::get("memChargeTotal") && CoreLocal::get("memChargeTotal") > 0) {
            //if (CoreLocal::get('msgrepeat') == 0) {
                CoreLocal::set("boxMsg",sprintf("<b>".$this->message."</b><br />
                    Total AR payments $%.2f exceeds AR balance %.2f<br />",
                    CoreLocal::get("memChargeTotal"),
                    CoreLocal::get("balance")));
                CoreLocal::set('boxMsgButtons', array(
                    'Confirm [enter]' => '$(\'#reginput\').val(\'VC\');submitWrapper();',
                    'Cancel [clear]' => '$(\'#reginput\').val(\'CL\');submitWrapper();',
                ));
                CoreLocal::set("strEntered","VC");
                return MiscLib::baseURL()."gui-modules/boxMsg2.php";
            //}
        //}

       // return true;   
    }
}

