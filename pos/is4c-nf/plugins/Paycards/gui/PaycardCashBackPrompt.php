<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op.

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
use COREPOS\pos\lib\FormLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;

/* this module is intended for re-use. Just set 
 * Pass the name of a class with the
 * static properties: 
 *  - requestTareHeader (upper message to display)
 *  - requestTareMsg (lower message to display)
 * and static method:
 *  - requestTareCallback(string $info)
 *
 * The callback receives the info entered by the 
 * cashier. To reject the entry as invalid, return
 * False. Otherwise return a URL to redirect to that
 * page or True to go to pos2.php.
 */

if (!class_exists("AutoLoader")) include_once(realpath(dirname(__FILE__).'/../../../lib/AutoLoader.php'));

class PaycardCashBackPrompt extends NoInputCorePage 
{
    private $boxColor;
    private $msg;
    private $heading;
    
    function preprocess()
    {
        $this->boxColor="coloredArea";
        $this->msg = _("Enter Cash Back");
        // get calling class (required)
        //$item = FormLib::get('item');
        $pos_home = MiscLib::base_url().'gui-modules/pos2.php';
        if ($item === '') {
            $this->change_page($pos_home);
            return false;
        }

        // info was submitted
        if (FormLib::get('input', false) !== false) {
            $reginput = strtoupper(FormLib::get('input'));
            if ($reginput == 'CL'){
                // clear; go home
                $this->change_page($pos_home);
                return false;
            } else {
                if ($reginput === '' || $reginput === '0'){
                    $this->session->set("CacheCardCashBack",'');
                    $this->change_page($pos_home.'?reginput=DATACAPDC&repeat=1');
                    return false;
                } 
                if (is_numeric($reginput)) {
                    $cashBack = $reginput/100;
                    if ($this->session->get("isMember") && $reginput > 5000) {
                        $this->boxColor="errorColoredArea";
                        $this->msg = _("$50.00 limit for members");
                    } else if ($reginput > 2000) {
                        $this->boxColor="errorColoredArea";
                        $this->msg = _("$20.00 limit for non-members");
                    } else {
                        CoreLocal::set("CacheCardCashBack",$cashBack);
                        $this->change_page($pos_home.'?reginput=DATACAPDC&repeat=1');
                        return false;
                    }

                }
            }
        }
        return true;
    }

    function body_content(){
        ?>
        <div class="baseHeight">
        <div class="<?php echo $this->boxColor; ?> centeredDisplay">
        <span class="larger">
        Cash Back?
        </span>
        <form name="form" method="post" autocomplete="off" action="<?php AutoLoader::ownURL(); ?>">
        <input type="text" id="reginput" name='input' tabindex="0" onblur="$('#input').focus()" />
        </form>
        <p>
        <?php echo $this->msg ?>
        </p>
        </div>
        </div>

        <?php
        $this->add_onload_command("\$('#reginput').focus();");
    } // END true_body() FUNCTION

}

AutoLoader::dispatch();