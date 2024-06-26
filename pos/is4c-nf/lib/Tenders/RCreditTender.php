<?php
/*******************************************************************************

    Copyright 2012 Whole Foods Co-op

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

namespace COREPOS\pos\lib\Tenders;
use COREPOS\pos\lib\DisplayLib;
use \CoreLocal;

/**
  @class GiftCardTender
  Tender module for credit cards
*/
class RCreditTender extends TenderModule 
{

    protected $slip_type = 'rcSlip';
    /**
      Check for errors
      @return True or an error message string
    */
    public function errorCheck()
    {
        if (($this->amount > (CoreLocal::get("amtdue") + 0.005)) && CoreLocal::get("amtdue") >= 0){  
            return DisplayLib::xboxMsg(
                _("tender cannot exceed purchase amount"),
                DisplayLib::standardClearButton()
            );
        }

        $parentCheck = TenderModule::errorCheck();

        return true;
    }
    
    /**
      Set up state and redirect if needed
      @return True or a URL to redirect
    */
    public function preReqCheck()
    {
        return true;
    }

}

