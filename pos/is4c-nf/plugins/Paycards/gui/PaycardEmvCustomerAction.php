<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op
    Modifications copyright 2010 Whole Foods Co-op

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

use COREPOS\pos\lib\FormLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\UdpComm;
use COREPOS\pos\lib\LaneLogger;
use COREPOS\pos\plugins\Paycards\card\CardValidator;
use COREPOS\pos\lib\DriverWrappers\ScaleDriverWrapper;
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\lib\gui\BasicCorePage;
use \CoreLocal as session;
if (!class_exists('AutoLoader')) include_once(dirname(__FILE__).'/../../../lib/AutoLoader.php');

class PaycardEmvCustomerAction extends BasicCorePage 
{
    private $strmsg = '';
    private $icon = '';
    private $buttons = array();

    function preprocess()
    {
        $this->conf = new PaycardConf();
        $msg = 'Select card type on pin pad.';
        $msgTitle = 'Customer Action';
        

        if (FormLib::get('reginput', false) !== false) {
            $input = strtoupper(trim(FormLib::get('reginput')));
            // CL always exits
            if ($input == "CL" || $input == "TERM:CANCEL") {
                $this->conf->set("msgrepeat",0);
                $this->conf->set("toggletax",0);
                $this->conf->set("togglefoodstamp",0);
                $this->conf->reset();
                $this->conf->set("CachePanEncBlock","");
                $this->conf->set("CachePinEncBlock","");
                $this->conf->set("CacheCardType","");
                $this->conf->set("CacheCardCashBack",0);
                $this->conf->set("CardCashBackChecked", false);
                $this->conf->set('ccTermState','swipe');
                UdpComm::udpSend("termReset");
                $this->change_page($this->page_url."gui-modules/pos2.php");
                return False;
            } elseif( $input == "" || substr($input, 0, 5)=='TERM:') {
                $url = $this->page_url.'gui-modules/pos2.php?reginput=DATACAP&repeat=1';
                $this->change_page($url);
            } 
            elseif ( $input != "" && substr($input,-2) != "CL") {
                $msg = 'Invalid entery try again or clear to';
            }
        } elseif (FormLib::get('cancel') == 1) {
            UdpComm::udpSend("termReset");
            echo 'Canceled';
            return false;
        } // post?

        $this->strmsg='<b>'.$msgTitle.'</b><br>
                <br><b>'.$msg.'</b><font size=-1>
                <p>
                <p>[enter] to continue
                <br>[clear] ' . _('to cancel') . '
                </font>"';

        return true;
    }
    function head_content() {
        //$this->default_parsewrapper_js();
        //$this->scanner_scale_polling(true);
        $url = $this->page_url."plugins/Paycards/gui/PaycardEmvCustomerAction.php?reginput=";
        
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
        $this->input_header('action="PaycardEmvCustomerAction.php" onsubmit="return submitWrapper"');
        echo DisplayLib::printheaderb();
        echo '<div class="baseHeight">';

        echo "<div id=\"boxMsg\" class=\"centeredDisplay\">";
        echo "<div class=\"boxMsgAlert coloredArea\">";
        echo CoreLocal::get("alertBar");
        if (CoreLocal::get('alertBar') == '') {
            echo 'Alert';
        }
        echo"</div>";
        echo "
            <div class=\"boxMsgBody\">
                <div class=\"msgicon\"><img src=\"$icon\" /></div>
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
    }

}/*
        echo DisplayLib::boxMsg("<b>Customer Action</b><br>
                <br><b>Select card type on pin pad.</b><font size=-1>
                <p>
                <p>[enter] to continue
                <br>[clear] " . _('to cancel') . "
                </font>","Green Fields Market",true);
                */

AutoLoader::dispatch();