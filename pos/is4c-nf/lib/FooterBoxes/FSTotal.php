<?php
/*******************************************************************************

    Copyright 2013 Franklin Community Co-op

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
namespace COREPOS\pos\lib\FooterBoxes;
use \CoreLocal;

class FSTotal extends FooterBox {

	var $header_css = "color: #004080;";
	var $display_css = "font-weight:bold;font-size:110%;color:#808080;";

	function header_content(){
		return _("SNAP Total");
	}

	function display_content(){
		global $CORE_LOCAL;
		$saleTTL = (is_numeric($CORE_LOCAL->get("fsEligible"))) ? number_format($CORE_LOCAL->get("fsEligible"),2) : "0.00";
		return $saleTTL;
	}
}

?>