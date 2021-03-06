<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op

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

use COREPOS\pos\lib\gui\NoInputCorePage;
use COREPOS\pos\lib\TransRecord;
include_once(dirname(__FILE__).'/../lib/AutoLoader.php');

class PaidOutComment extends NoInputCorePage 
{

    function preprocess()
    {
        if (isset($_REQUEST["selectlist"])){
            $input = $_REQUEST["selectlist"];
            $qstr = '';
            if ($input == "CL" || $input == ''){
                CoreLocal::set("strEntered","");
                //CoreLocal::set("refund",0);
            } elseif ($input == "Other"){
                return True;
            } else {
                $input = str_replace("'","",$input);
                $output = CoreLocal::get("strEntered");
                $qstr = '?reginput=' . urlencode($output) . '&repeat=1';

                // add comment calls additem(), which wipes
                // out refundComment; save it
                TransRecord::addcomment("PaidOut: ".$input);
                CoreLocal::set("strEntered", $output);
                //CoreLocal::set("refund",1);
            }
            $this->change_page($this->page_url."gui-modules/pos2.php" . $qstr);
            return false;
        }
        return True;
    }
    
    function head_content()
    {
        ?>
        <script type="text/javascript" src="../js/selectSubmit.js"></script>
        <?php
    } // END head() FUNCTION

    function body_content() 
    {
        ?>
        <div class="baseHeight">
        <div class="centeredDisplay colored">
        <span class="larger">reason for paidout</span>
        <form name="selectform" method="post" 
            id="selectform" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <?php
        if (isset($_POST['selectlist']) && $_POST['selectlist'] == 'Other') {
        ?>
            <input type="text" id="selectlist" name="selectlist" 
                onblur="$('#selectlist').focus();" />
        <?php
        }
        else {
        ?>
            <select name="selectlist" id="selectlist"
                onblur="$('#selectlist').focus();">
            <option>Paid to Supplier</option>
            <option>Store Use</option>
            <option>Coupon</option>
            <option>Other</option>
            <option>Discount</option>
            <option>Gift Card Refund</option>
            </select>
        <?php
        }
        ?>
        </form>
        <p>
        <span class="smaller">[clear] to cancel</span>
        </p>
        </div>
        </div>    
        <?php
        $this->add_onload_command("\$('#selectlist').focus();\n");
        //if (isset($_POST['selectlist']) && $_POST['selectlist'] == 'Other') 
        $this->add_onload_command("selectSubmit('#selectlist', '#selectform')\n");
    } // END body_content() FUNCTION
}

AutoLoader::dispatch();

