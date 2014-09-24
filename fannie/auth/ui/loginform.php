<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(dirname(__FILE__) . '/../../classlib2.0/FannieAPI.php');
}
if (!function_exists('validateUserQuiet')) {
    require(dirname(__FILE__) . '/../login.php');
}

class FannieAuthLoginPage extends FannieRESTfulPage
{
    protected $title = 'Fannie : Auth';
    protected $header = 'Fannie : Auth';

    public function preprocess()
    {
        if (isset($_GET["redirect"]) && init_check()){
            header("Location:".$_GET['redirect']);
            return false;
        }
        $this->__routes[] = 'get<logout>';
        $this->__routes[] = 'post<name><password>';

        return parent::preprocess();
    }

    /**
      Logout the current user as requested 
    */
    public function get_logout_handler()
    {
        logout();

        return true;
    }

    /**
      Check submitted credentials. Redirect to destination
      on success, proceed to error message on failure
    */
    public function post_name_password_handler()
    {
        global $FANNIE_AUTH_LDAP, $FANNIE_AUTH_SHADOW;
        $name = FormLib::get('name');
        $password = FormLib::get('password');
        $login = login($name,$password);
        $redirect = FormLib::get('redirect', 'menu.php');

        if (!$login && $FANNIE_AUTH_LDAP) {
            $login = ldap_login($name,$password);
        }

        if (!$login && $FANNIE_AUTH_SHADOW) {
            $login = shadow_login($name,$password);
        }

        if ($login) {
            header("Location: $redirect");
            return false;
        } else {
            return true;
        }
    }

    /**
      Error message for failed login
    */
    public function post_name_password_view()
    {
        $redirect = FormLib::get('redirect', 'menu.php');
        return "Login failed. <a href=loginform.php?redirect=$redirect>Try again</a>?";
    }

    /**
      After logout, just display the regular login form
      with a line noting logout was successful
    */
    public function get_logout_view()
    {
        return "<blockquote><i>You've logged out</i></blockquote>"
            . $this->get_view();
    }

    /**
      Show the login form unless the user is already logged in
      Logged in users get links to potentially intended destinations
    */
    public function get_view()
    {
        $current_user = checkLogin();

        ob_start();
        if ($current_user) {
            echo "You are logged in as $current_user<p />";
            if (isset($_GET['redirect'])){
                echo "<b style=\"font-size:1.5em;\">It looks like you don't have permission to access this page</b><p />";
            }
            echo "<a href=menu.php>Main menu</a>  |  <a href=loginform.php?logout=yes>Logout</a>?";
        } else {
            $redirect = FormLib::get('redirect', 'menu.php');
            echo "<form action=loginform.php method=post>";
            echo "<table cellspacing=2 cellpadding=4><tr>";
            echo "<td>Name:</td><td><input type=text name=name></td>";
            echo "</tr><tr>";
            echo "<td>Password:</td><td><input type=password name=password></td>";
            echo "</tr><tr>";
            echo "<td><input type=submit value=Login></td><td><input type=reset value=Clear></td>";
            echo "</tr></table>";
            echo "<input type=hidden value=$redirect name=redirect />";
            echo "</form>";
            echo "<script type=text/javascript>";
            echo "document.forms[0].name.focus();";
            echo "</script>";
        }

        return ob_get_clean();
    }
}

if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $obj = new FannieAuthLoginPage();
    $obj->drawPage();
}

