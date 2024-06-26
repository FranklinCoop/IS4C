<?php
/*******************************************************************************

    Copyright 2001, 2004 Wedge Community Co-op

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
use COREPOS\pos\lib\Authenticate;
use COREPOS\pos\lib\Drawers;
use COREPOS\pos\lib\UdpComm;
use COREPOS\pos\lib\TransRecord;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\Kickers\Kicker;
include_once(dirname(__FILE__).'/../lib/AutoLoader.php');

class logout extends NoInputCorePage 
{
    private $color;
    private $heading;
    private $msg;

    private function getPassword()
    {
        $ret = $this->form->tryGet('reginput');
        if ($ret === '') {
            $ret = $this->form->tryGet('userPassword');
        }

        return $ret;
    }

    function preprocess()
    {
        $this->color ="coloredArea";
        $this->heading = _("enter password");
        $this->msg = _("confirm no sales");

        if ($this->form->tryGet('reginput', false) !== false || $this->form->tryGet('userPassword', false) !== false) {

            $passwd = $this->getPassword();

            if (strtoupper($passwd) == "CL") {
                $this->change_page($this->page_url."gui-modules/pos2.php");
                return False;
            } elseif (Authenticate::checkPassword($passwd)) {
                TransRecord::addLogRecord(array(
                    'upc' => 'SIGNOUT',
                    'description' => 'Sign Out Emp#' . $this->session->get('CashierNo'),
                ));
                Database::setglobalvalue("LoggedIn", 0);
                $this->session->set("LoggedIn",0);
                $this->session->set("training",0);
                /**
                  An empty transaction may still contain
                  invisible, logging records. Rotate those
                  out of localtemptrans to ensure sequential
                  trans_id values
                */
                if (Database::rotateTempData()) {
                    Database::clearTempTables();
                }
                $drawer = new Drawers($this->session, Database::pDataConnect());
                if (session_id() != '') {
                    session_write_close();
                }
                $kicker_object = Kicker::factory($this->session->get('kickerModule'));
                if ($kicker_object->kickOnSignOut()) {
                    $drawer->kick();
                }
                $drawer->free($drawer->current());
                $this->change_page($this->page_url."login.php");
                return false;
            }

            $this->color ="errorColoredArea";
            $this->heading = _("re-enter password");
            $this->msg = _("invalid password");
        }
        // beep on initial page load
        if ($this->session->get('LoudLogins') == 1) {
            UdpComm::udpSend('twoPairs');
        }

        return true;
    }

    function head_content()
    {
        $this->default_parsewrapper_js('reginput','nsform');
        $this->scanner_scale_polling(true);
    }

    function body_content()
    {
        ?>
        <div class="baseHeight">
        <div class="<?php echo $this->color; ?> centeredDisplay">
        <span class="larger">
        <?php echo $this->heading ?>
        </span><br />
        <form name="form" id="nsform" method="post" autocomplete="off" 
            action="<?php echo AutoLoader::ownURL(); ?>">
        <input type="password" name="userPassword" tabindex="0" 
            onblur="$('#userPassword').focus();" id="userPassword" />
        <input type="hidden" id="reginput" name="reginput" value="" />
        </form>
        <p>
        <?php echo $this->msg ?>
        </p>
        </div>
        </div>
        <?php
        $this->addOnloadCommand("\$('#userPassword').focus();\n");
    } // END true_body() FUNCTION

}

AutoLoader::dispatch();

