<?php

namespace COREPOS\Fannie\API\item;

class VendorCallback
{
    public function run($upc)
    {
        $log = new \FannieLogger();
        $log->debug('In the parent for some reason');
    }
}

