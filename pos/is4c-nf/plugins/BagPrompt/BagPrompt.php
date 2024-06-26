<?php
/*******************************************************************************

    Copyright 2016 George Street Co-op

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
use COREPOS\pos\plugins\Plugin;

/**
  Plugin class

  Plugins are collections of modules. Each collection should
  contain one module that subclasses 'Plugin'. This module
  provides meta-information about the plugin like settings
  and enable/disable hooks
*/
class BagPrompt extends Plugin 
{

    /**
      Desired settings. These are automatically exposed
      on the 'Plugins' area of the install page and
      written to ini.php
    */
    public $plugin_settings = array(
        'bagChargePLU' => array(
            'default' => '9500',
            'label' => 'PLU to use for bagCharge',
            'description' => 'Enter the plu to apply when a bag is used.'
            )
    );

    public $plugin_description = 'Automatically Ask how many backs to chrage the customer for.';

    public function plugin_transaction_reset()
    {
       //echo("<script>console.log('resetting bagPropmtAsked');</script>");
        CoreLocal::set('bagProptAsked', 0);
       // $bagAsk  =  CoreLocal::get('bagProptAsked');
        //echo("<script>console.log('{$bagAsk}');</script>");
    }
}
