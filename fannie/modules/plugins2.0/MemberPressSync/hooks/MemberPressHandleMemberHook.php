<?php
/*******************************************************************************

    Copyright 2023-12-06 Franklin Community Coop

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

use COREPOS\Fannie\API\item\ItemText;

include(dirname(__FILE__).'/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}
include_once(dirname(__FILE__).'/../lib/MemberPressSyncLib.php');
/**
*/
class MemberPressHandleMemberHook
{
    
    
    private static $receivedWebhook    = null;
    /**
     * Retrieve the incoming webhook request as sent.
     *
     * @param string $input An optional raw POST body to use instead of php://input - mainly for unit testing.
     *
     * @return array|false    An associative array containing the details of the received webhook
     */
    public static function receive($input = null)
    {
        if (is_null($input)) {
            if (self::$receivedWebhook !== null) {
                $input = self::$receivedWebhook;
            } else {
                $input = file_get_contents("php://input");
            }
        }

        if (!is_null($input) && $input != '') {
            return self::processWebhook($input);
        }

        return false;
    }

    /**
     * Process the raw request into a PHP array and dispatch any matching subscription callbacks
     *
     * @param string $input The raw HTTP POST request
     *
     * @return array|false    An associative array containing the details of the received webhook
     */
    private static function processWebhook($input)
    {
        self::$receivedWebhook = $input;
        parse_str($input, $result);
        if ($result && isset($result['type'])) {
            self::dispatchWebhookEvent($result['type'], $result['data']);
            return $result;
        }

        return false;
    }
}