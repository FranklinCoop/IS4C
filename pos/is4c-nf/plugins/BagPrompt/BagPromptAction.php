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

//use \CoreLocal;
use COREPOS\pos\lib\MemberLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TotalActions\TotalAction;

/**
  @class BagPromptAction  extends TotalAction
  Ask the cashier to enter the number of bags used
  so that we can change people the Greenfield Exeuptive
  function tax.
*/
class BagPromptAction  extends TotalAction
{
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
        //if (CoreLocal::get('bagProptAsked')) {return true;}
        //echo("<script>console.log('inside bag prompt action');</script>");
        if ( CoreLocal::get('bagProptAsked') === 0 ) {
            return MiscLib::baseURL()."plugins/BagPrompt/BagPromptPage.php?item=TL";
        } else {
            return true;
        }
        //return true;
        
    }
}

