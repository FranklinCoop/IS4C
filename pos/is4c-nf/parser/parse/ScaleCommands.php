<?php
/*******************************************************************************

    Copyright 2007 Whole Foods Co-op

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

namespace COREPOS\pos\parser\parse;
use COREPOS\pos\parser\Parser;
use COREPOS\pos\lib\MiscLib;

class ScaleCommands extends Parser 
{
    private $cbError;

    function check($str)
    {
        if ($str == "SCLREBOOT") {
            MiscLib::reBoot();
            return true;
        }
        return false;
    }

    function parse($str)
    {
        $ret = $this->default_json();
        return $ret;
    }


    function doc()
    {
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td>TERMMANUAL</td>
                <td>
                Send CC terminal to manual entry mode
                </td>
            </tr>
            <tr>
                <td>TERMRESET</td>
                <td>Reset CC terminal to begin transaction</td>
            </tr>
            <tr>
                <td>CCFROMCACHE</td>
                <td>Charge the card cached earlier</td>
            </tr>
            <tr>
                <td>PANCACHE:<encrypted block></td>
                <td>Cache an encrypted block on swipe</td>
            </tr>
            <tr>
                <td>PINCACHE:<encrypted block></td>
                <td>Cache an encrypted block on PIN entry</td>
            </tr>
            </table>";
    }
}

