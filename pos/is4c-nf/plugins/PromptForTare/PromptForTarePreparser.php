<?php
/*******************************************************************************

    Copyright 2017 Franklin Community coop

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

namespace COREPOS\pos\parser\preparse;
use \CoreLocal;
use COREPOS\pos\parser\PreParser;

class ScaleValue extends PreParser 
{

        /**
      Defines how the UPC was entered.
      Known good values are:
      - keyed
      - scanned
      - macro
      - hid
    */
    private $source = 'keyed';

    const GENERIC_STATUS = 'NA';

    const SCANNED_PREFIX = '0XA';
    const SCANNED_STATUS = 'SS';

    const MACRO_PREFIX = '0XB';
    const MACRO_STATUS = 'KB';

    const HID_PREFIX = '0XC';
    const HID_STATUS = 'HI';

    const GS1_PREFIX = 'GS1~RX';
    const GS1_STATUS = 'GS';
    
    public function check($str)
    {
        if (is_numeric($str) && strlen($str) < 16) {
            return true;
        } elseif ($this->getPrefix($str) !== false) {
            return true;
        }

        return false;
    }

    private function prefixes()
    {
        return array(
            self::SCANNED_STATUS => self::SCANNED_PREFIX,
            self::MACRO_STATUS => self::MACRO_PREFIX,
            self::HID_STATUS => self::HID_PREFIX,
            self::GS1_STATUS => self::GS1_PREFIX,
        );
    }

    private function getPrefix($str)
    {
        foreach ($this->prefixes() as $prefix) {
            $len = strlen($prefix);
            if (substr($str,0,$len) == $prefix && is_numeric(substr($str, $len))) {
                return $prefix;
            }
        }

        return false;
    }

    private function getStatus($source)
    {
        foreach ($this->prefixes() as $status => $prefix) {
            if ($source == $prefix) {
                return $status;
            }
        }

        return self::GENERIC_STATUS;
    }

    function parse($str)
    {
        $retStr = $str;
        $this->source = $this->getPrefix($str);
        if ($this->source == self::GS1_PREFIX) {
            $str = $this->fixGS1($str);
        }
        $this->status = self::GENERIC_STATUS;
        if ($this->source !== false) {
            $this->status = $this->getStatus($this->source);
        }

        /**
          Do not apply scanned items if
          tare has been entered
        */
        if ($this->session->get('tare') > 0 && $this->source === self::SCANNED_PREFIX) {
            //do nothing
        } else {
            // add tare weight
                    /**
          Apply automatic tare weight
        */

            if ($row['scale'] != 0 && !$this->session->get("tare") && Plugin::isEnabled('PromptForTare') && !$this->session->get("tarezero")) {
                $ret['main_frame'] = $myUrl.'plugins/PromptForTare/TarePromptInputPage.php?item='.$upc;
                return $ret;
            } 
        }



        return $retStr;
    }

    function doc(){
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td>Checks if there is a tare then prompts casher to add one or adds default</td>
            </tr>
            </table>";
    }
}

