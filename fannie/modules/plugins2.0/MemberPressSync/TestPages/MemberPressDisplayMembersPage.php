<?php
/*******************************************************************************

    Copyright 2023 Franklin Community Co-op

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

	parts of this file was adapted from http://sourceforge.net/projects/mysql2sqlite/

*********************************************************************************/
include(dirname(__FILE__).'/../../../../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
include_once(dirname(__FILE__).'/../lib/MemberPressSyncLib.php');

class MemberPressDisplayMembersPage extends FanniePage {

	protected $header = 'Member Press Test Page';
	protected $title = 'Member Press Test';
    private $mpURL ='';
    private $mpKey = '';
    

    public function __construct(){
        parent::__construct();
        $conf = FannieConfig::factory();
        $settings = $conf->get('PLUGIN_SETTINGS');
        $this->mpURL = $settings['mpUrl'];
        $this->mpKey = $settings['mpAPIKey'];
    }

    public function body_content()
    {
        # http://localhost/CORE/fannie/modules/plugins2.0/MemberPressSync/TestPages/MemberPressDisplayMembersPage.php
        $members = MemberPressSyncLib::getMembers($this->mpURL, $this->mpKey);

        //map the exisiting website members logically.
        $originMP = array();
        $originCORE = array();
        $originNM = array();
        $tableHeaders = '';

        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>Users coming from memberpress</th></tr>';
        foreach ($members as $member) {
            
            if ($tableHeaders === '') { 
                echo '<tr>';
                echo $this->loopArrayKeys($member);
                echo '</tr>';
                $tableHeaders = 'done';
            }
            echo '<tr>';
            echo $this->loopArray($member);
            echo '</tr>';

        }
        echo '</table>';

    }

    private function loopNestedArray($arr) {
        $headersDone = false;
        $ret = 'Nested Table <table>">';
        foreach($arr as $key => $value) {
            if ($headersDone) {
                $ret.= '<tr>'.$this->loopArrayKeys($value).'</tr><tr>';
            }
            $ret .= '<td>';
            $ret .= $value;
            $ret .= '</td>';
        }
        $ret .= '</tr></table>';
        return $ret;
    }

    private function loopArray($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<td>';
            if (is_array($value)) {
                $ret .= $this->loopNestedArray($value);
            } else {
                $ret .= $value;
            }
            $ret .= '</td>';
        }
        return $ret;
    }
    private function loopArrayKeys($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<th>';
            $ret .= $key;
            $ret .= '</th>';
        }
        return $ret;
    }
    private function getMPMemberships () {

    }
}

FannieDispatch::conditionalExec(false);