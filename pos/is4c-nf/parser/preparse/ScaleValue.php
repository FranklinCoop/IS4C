<?php
/*******************************************************************************

    Copyright 2019 Franklin Community Co-op

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
use COREPOS\pos\lib\DisplayLib;
use COREPOS\pos\parser\PreParser;

class ScaleValue extends PreParser 
{
    
    function check($str){
        if (strstr($str, "$"))
            return True;
        return False;
    }

    function parse($str)
    {
        $ret = $str;
        if (CoreLocal::get("weight") == 0) {
            return $str;
        } else {
            $weight = CoreLocal::get("weight");
            $ret = str_replace("$", $weight, $str);
        }
        return $ret;
    }

    function isLast(){
        return True;
    }

    function doc(){
        return "<table cellspacing=0 cellpadding=3 border=1>
            <tr>
                <th>Input</th><th>Result</th>
            </tr>
            <tr>
                <td><i>number</i>*<i>item</i></td>
                <td>Enter <i>item</i> <i>number</i> times
                (e.g., 2*item to ring up two of the same
                item)</td>
            </tr>
            </table>";
    }
}

