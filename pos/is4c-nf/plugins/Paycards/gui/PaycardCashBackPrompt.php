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

use COREPOS\pos\lib\gui\BasicCorePage;
use COREPOS\pos\lib\FormLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;
use COREPOS\pos\lib\UdpComm;
use COREPOS\pos\lib\DisplayLib;

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

class PaycardCashBackPrompt extends BasicCorePage 
{
    private $strmsg = '';
    private $icon = '';
    private $buttons = array();
    
    function preprocess()
    {
        $this->msg = _("Enter Cash Back");
        $pos_home = MiscLib::base_url().'gui-modules/pos2.php';

        $msg = "Please Enter Cash Back";
        $msgTitle = 'Cash Back?';

        // info was submitted
        if (FormLib::get('reginput', false) !== false) {
            $reginput = strtoupper(FormLib::get('reginput'));
            if ($reginput == 'CL' || $reginput == "TERM:CANCEL"){
                // clear; go home
                $this->session->set("msgrepeat",0);
                $this->session->set("toggletax",0);
                $this->session->set("togglefoodstamp",0);
                $this->session->set("CachePanEncBlock","");
                $this->session->set("CachePinEncBlock","");
                $this->session->set("CacheCardType","");
                $this->session->set("CacheCardCashBack",0);
                $this->session->set("CardCashBackChecked", false);
                $this->session->set('ccTermState','swipe');
                UdpComm::udpSend("termReset");
                $this->change_page($pos_home);
                return false;
            } else {
                if ($reginput === '' || $reginput === '0'){
                    $this->session->set("CacheCardCashBack",0);
                    $this->session->set("CardCashBackChecked", true);
                    $this->change_page($pos_home.'?reginput=DATACAPDC&repeat=1');
                    return false;
                } 
                if (is_numeric($reginput)) {
                    $cashBack = $reginput/100;

                    if ($this->session->get("isMember") && $cashBack > 50) {
                        $msg = _("$50.00 limit for members");
                    } else if ($cashBack > 20) {
                        $msg = _("$20.00 limit for non-members");
                    } else {
                        $this->session->set("CacheCardCashBack",$cashBack);
                        $this->session->set("CardCashBackChecked", true);
                        $this->change_page($pos_home.'?reginput=DATACAPDC&repeat=1');
                        return false;
                    }

                }
            }
        }

        $this->strmsg='<b>'.$msgTitle.'</b><br>
                <br><b>'.$msg.'</b><font size=-1>
                <p>
                <p>[enter] to continue
                <br>[clear] ' . _('to cancel') . '
                </font>"';
        return true;
    }

    function head_content(){
        $url = $this->page_url."plugins/Paycards/gui/PaycardCashBackPrompt.php?reginput=";
        
        echo '<script type="text/javascript" src="<?php echo $this->page_url; ?>js/ajax-parser.js"></script>';
        echo '<script type="text/javascript" src="<?php echo $this->page_url; ?>js/CustomerDisplay.js"></script>';
        echo '<script type="text/javascript" src="../js/PaycardParserFunctions.js?date=20180308"></script>';
        ?>
        <script type="text/javascript">
        setUrl('<?php echo $url?>');
        function parseWrapper(str) {
            $('#reginput').val($('#reginput').val() + '' + str);
            submitWrapper();
        }

        </script>
        <?php
    }

    function body_content(){
        $this->icon = MiscLib::base_url()."graphics/exclaimC.gif";
        $this->input_header('action="PaycardCashBackPrompt.php" onsubmit="return submitWrapper"');
        echo '<div class="baseHeight">';
        echo DisplayLib::printheaderb();

        echo "<div id=\"boxMsg\" class=\"centeredDisplay\">";
        echo "<div class=\"boxMsgAlert coloredArea\">";
        echo CoreLocal::get("alertBar");
        if (CoreLocal::get('alertBar') == '') {
            echo 'Alert';
        }
        echo"</div>";
        echo "
            <div class=\"boxMsgBody\">
                <div class=\"msgicon\"><img src=\"$this->icon\" /></div>
                <div class=\"msgtext\">"
                . $this->strmsg . "
                </div>
                <div class=\"clear\"></div>
            </div>";
        if (!empty($buttons) && is_array($buttons)) {
            echo'<div class="boxMsgBody boxMsgButtons">';
            foreach ($$this->buttons as $label => $action) {
                $label = preg_replace('/(\[.+?\])/', '<span class="smaller">\1</span>', $label);
                $color = preg_match('/\[clear\]/i', $label) ? 'errorColoredArea' : 'coloredArea';
                echo sprintf('<button type="button" class="pos-button %s" 
                        onclick="%s">%s</button>',
                        $color, $action, $label);
            }
        echo '</div>';
        }
        echo "</div>"; // close #boxMsg

        echo '</div>';


        echo '<div id="footer">';
        echo DisplayLib::printfooter();
        echo '</div>';
    } // END true_body() FUNCTION

}

AutoLoader::dispatch();