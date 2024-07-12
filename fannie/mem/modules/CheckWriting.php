<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op, Duluth, MN

    This file is part of CORE-POS.

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

class CheckWriting extends \COREPOS\Fannie\API\member\MemberModule {

    public function width()
    {
        return parent::META_WIDTH_HALF;
    }

    // Return a form segment to display or edit the Contact Preference.
    function showEditForm($memNum, $country="US"){

        global $FANNIE_URL;

        $account = self::getAccount();
        $primary = array('discount'=>0, 'staff'=>0, 'lowIncomeBenefits'=>0, 'chargeAllowed'=>0, 'checksAllowed'=>0);
        foreach ($account['customers'] as $c) {
            if (!$c['accountHolder']) {
                continue;
            }
            $accountValue = $c['checksAllowed'] ? 'checked' : '';
                    // Compose the display/edit block.
            $ret = "<div class=\"panel panel-default\">
                <div class=\"panel-heading\">Check Writing Privilage</div>
                <div class=\"panel-body\">";

            $ret .= '<div class="form-group form-inline">
                <span class="label primaryBackground">Allow Checks </span>';
            $ret .= sprintf('<input type="checkbox" name="MemCheckWriting" id="MemCheckWriting"
                %s value="%d" class="checkbox-inline" /></label>',$accountValue,$c['checksAllowed']);
            $ret .= "</select></div>";

            $ret .= "</div>";
            $ret .= "</div>";
        }

        //$accountValue = $row['WriteChecks'] !==0 ? 'checked' : 'checked';


        return $ret;

    // showEditForm
    }

    // Update Check writing
    // Return "" on success or an error message.
    public function saveFormData($memNum, $json=array())
    {
        $formPref = FormLib::get('MemCheckWriting')==='' ? '0' : '1';

        foreach ($json['customers'] as $key=>$customer) {
            if ($customer['checksAllowed'] != $formPref) {
                $json['customers'][$key]['checksAllowed'] = $formPref;
            }
        }
        return $json;

    }

// CheckWriting
}

