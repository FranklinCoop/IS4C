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

/* --COMMENTS - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

    * 24Oct2013 Eric Lee Defeated:
    *                    + A WEFC_Toronto-only textbox for collecting Member Card#
    *  5Oct2012 Eric Lee Added:
    *                    + A WEFC_Toronto-only chunk for collecting Member Card#
    *                    + A general facility for displaying an error encountered in preprocess()
    *                       in body_content() using tempMessage.

*/

use COREPOS\pos\lib\gui\NoInputCorePage;
include_once(dirname(__FILE__).'/../lib/AutoLoader.php');

class MemberNotificationPage extends NoInputCorePage
{

    function body_content()
    {
        echo "<div class=\"baseHeight\">"
            ."<form id=\"selectform\" method=\"post\" action=\""
            .AutoLoader::ownURL() . "\">";

                echo "<div class=\"listbox\">"
            ."<select name=\"search\" size=\"15\" "
            .' style="min-height: 200px; min-width: 220px; max-width: 390px;" '
            ."onblur=\"\$('#reginput').focus();\" ondblclick=\"document.forms['selectform'].submit();\" 
            id=\"reginput\">";


        echo "</form></div>";
    } // END body_content() FUNCTION

}
AutoLoader::dispatch();