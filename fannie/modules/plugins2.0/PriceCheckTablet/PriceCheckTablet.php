<?php

use COREPOS\Fannie\API\FanniePlugin;

class PriceCheckTablet extends FanniePlugin
{
    public $plugin_settings = array(
    'T1PrintIP' => array('default'=>'','label'=>'Printer IP/Host',
            'description'=>'IP or hostname for printer'),
    );

    public $plugin_description = 'Tool to build, suspend, & print small orders';
}

