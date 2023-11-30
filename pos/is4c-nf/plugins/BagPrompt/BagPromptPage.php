<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op.

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
use COREPOS\pos\lib\gui\NoInputCorePage;
use COREPOS\pos\lib\FormLib;
use COREPOS\pos\lib\MiscLib;
use COREPOS\pos\lib\TransRecord;
use COREPOS\pos\lib\Database;
use COREPOS\pos\lib\PrehLib;

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

if (!class_exists("AutoLoader")) include_once(realpath(dirname(__FILE__).'/../../lib/AutoLoader.php'));

class BagPromptPage extends NoInputCorePage 
{
    private $boxColor;
    private $msg;
    private $heading;

    function preprocess()
    {
        $this->boxColor = "coloredArea";
        $this->msg = _("Please enter the number of paperbags used,</br>press enter or clear if the customer has thier own bags.");
        $this->heading = "Enter Bags";
        // get calling class (required)
        $item = FormLib::get('item');
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
                return $this->done($pos_home);
            } else {
                if ($reginput === '' || $reginput === '0'){
                    return $this->done($pos_home);
                } 
                if (is_numeric($reginput) && $reginput < 30) {
                    //TransRecord::addTare($reginput);
                    $this->addBags($reginput);
                    return $this->done($pos_home);
                } else {
                    $this->boxColor="errorColoredArea";
                    $this->msg = _("Numeric Value between 0-30 required.");
                    return true;
                }
            }
        }
        return true;
    }

    private function done($pos_home){
        $repeat = CoreLocal::get('msgrepeat');
        CoreLocal::set("strEntered","TL");
        CoreLocal::set('bagProptAsked', 1);
        $this->change_page($pos_home.'?reginput=TL&repeat=1');
        return true;
    }

    private function addBags($amount) {
        $upc = 9500; // TODO make this configuredable instead of hard coede;
        $upc = str_pad($upc,13,'0',STR_PAD_LEFT);

        $dbc = Database::pDataConnect();
        $query = "select description,scale,tax,foodstamp,discounttype,
            discount,department,normal_price
                   from products where upc='".$upc."'";
        $result = $dbc->query($query);

        if ($dbc->num_rows($result) <= 0) return;

        $row = $dbc->fetchRow($result);
        
        $description = $row["description"];
        $description = str_replace("'", "", $description);
        $description = str_replace(",", "", $description);

        $scale = 0;
        if ($row["scale"] != 0) $scale = 1;

        list($tax, $foodstamp, $discountable) = PrehLib::applyToggles($row['tax'], $row['foodstamp'], $row['discount']);

        $discounttype = MiscLib::nullwrap($row["discounttype"]);

        $quantity = $amount;
        if ($this->session->get("quantity") != 0) {
            $quantity = $this->session->get("quantity");
        }

       // $saveRefund = $this->session->get("refund");

        TransRecord::addRecord(array(
            'upc' => $upc,
            'description' => $description,
            'trans_type' => 'I',
            'trans_subtype' => 'AD',
            'department' => $row['department'],
            'quantity' => $quantity,
            'ItemQtty' => $quantity,
            'unitPrice' => $row['normal_price'],
            'total' => $quantity * $row['normal_price'],
            'regPrice' => $row['normal_price'],
            'scale' => $scale,
            'tax' => $tax,
            'foodstamp' => $foodstamp,
            'discountable' => $discountable,
            'discounttype' => $discounttype,
        ));
    }

    function body_content(){
        ?>
        <div class="baseHeight">
        <div class="<?php echo $this->boxColor; ?> centeredDisplay">
        <span class="larger">
        <?php echo $this->heading; ?>
        </span><br />
        <form name="form" method="post" autocomplete="off" action="<?php AutoLoader::ownURL(); ?>">
        <input type="text" id="reginput" name='input' tabindex="0" onblur="$('#input').focus()" />
        <input type="hidden" name="item" value="<?php echo FormLib::get('item'); ?>" />
        </form>
        <p>
        <?php echo $this->msg; ?>
        </p>
        </div>
        </div>

        <?php
        $this->add_onload_command("\$('#reginput').focus();");
    } // END true_body() FUNCTION

}

AutoLoader::dispatch();

