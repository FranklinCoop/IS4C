<?php

use COREPOS\Fannie\API\FanniePlugin;

class PriceCheckTablet extends FanniePlugin
{
    public $plugin_settings = array(
            'tabletSerials' => array('default'=>'','label'=>'Serial ID list of tablets',
            'description'=>'Comma seprated list of serials for tablets'),
            'printIPs' => array('default'=>'','label'=>'List of IP/Host for printers',
            'description'=>'A comma sperated list of printer IPs 1 to 1 with the tablet serail
                            You want to print from. e.g 192.168.1.X, 0, 192.168.1.Y
                            You can have tablets print to the same printer by adding the ip for
                            each printer. 0 will not print or display the print button.'),
    );

    public $plugin_description = 'Price Check page that can print to netowrked epson receipt printers based on tablet serial number
    requires a tablet that can be made to send its serial to a webpage. I\'m using elotouch 10 inch tablets.
    A tablet with no serel IP combantion set will have no print option.';
}

