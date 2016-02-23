<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op

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

/**
  @class PostParser
  The base module for parsing modifying Parser results

  Enabled PostParser modules look at the output
  produced by input Parsers and may modify the result
*/
class SingleAuthTransactionPostParser extends PostParser 
{

    /**
      Re-write the output value
      @param [keyed array] Parser output value
      @return [keyed array] Parser output value

      The output array has the following keys:
       - main_frame If set, change page to this URL
       - output HTML output to be displayed
       - target Javascript selector string describing which
         element should contain the output
       - redraw_footer True or False. Set to True if
         totals have changed.
       - receipt False or string type. Print a receipt with
         the given type.
       - trans_num string current transaction identifier
       - scale Update the scale display and session variables
       - udpmsg False or string. Send a message to hardware
         device(s)
       - retry False or string. Try the input again shortly.
    */
    public function parse($json)
    {
        /*
        if (CoreLocal::get("End") == 1) {
          $json['receipt'] = 'full';
          $json['output'] = '';
          $json = $this->displayTransactionEnd($json);
        }
        */
        return $json;
    }

    public function displayTransactionEnd($json) {
      //error message.
      $in_progress_msg = DisplayLib::boxMsg(
        _("transaction in progress"),
        '',
        true,
        DisplayLib::standardClearButton()
      );


      // sign off and suspend shift are identical except for
      // drawer behavior
      //if (CoreLocal::get("LastID") != 0) {
        //$json['output'] = $in_progress_msg;
      //} else {
        TransRecord::addLogRecord(array(
          'upc' => 'SIGNOUT',
          'description' => 'Sign Out Emp#' . CoreLocal::get('CashierNo'),
            ));
        Database::setglobalvalue("LoggedIn", 0);
        CoreLocal::set("LoggedIn",0);
        CoreLocal::set("training",0);
        CoreLocal::set("gui-scale","no");
        /**
        An empty transaction may still contain
        invisible, logging records. Rotate those
        out of localtemptrans to ensure sequential
        trans_id values
        */
        if (Database::rotateTempData()) {
          Database::clearTempTables();
        }
        $json['main_frame'] = MiscLib::base_url()."/plugins/SingleTransactionSignOn/SingleAuthTransLogin.php";
      //}
      return $json;
    }

}

