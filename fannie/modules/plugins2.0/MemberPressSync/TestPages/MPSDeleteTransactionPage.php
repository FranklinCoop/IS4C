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

class MPSDeleteTransactionPage extends FanniePage {

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
       # http://localhost/CORE/fannie/modules/plugins2.0/MemberPressSync/TestPages/MPSDeleteTransactionPage.php
 
        $params = array('page' => 1, 'per_page'=>99999);
        $url = $this->mpURL . "transactions" . '?' . http_build_query($params);
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url.'<br>';
        $ch = curl_init($url);

        //$postOpts = array('page' => 1, 'per_page'=>99999);
        //$postOptsJSON = json_encode($postOpts);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
        //curl_setopt($ch, CURLOPT_POST, true); 
        //curl_setopt( $ch, CURLOPT_POSTFIELDS, $postOptsJSON );
        //echo $postOptsJSON;

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$this->mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        //$header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        //echo $response;
        //$members = json_decode(json_encode($response), true);
        $transactions = json_decode($response, true);
        //$headerDisplayed = false;
        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>Transactions</th></tr><tr>';
        $transToRemove = array();
        //foreach ($transactions[0] as $key => $value){
        //    echo '<th>'.$key.'</th>';
       // }
       echo '<th>id</th><th>gateway</th>';
        echo '</tr>';
        
        foreach ($transactions as $trans) {
            $id = $trans['id'];
            //echo '<tr>';
            //$ret = '';
            if ($trans['gateway'] == 'manual') {
                $transToRemove[$id] = $trans['gateway'];
            }
            
            //if (in_array($id, $transToRemove)) {
            //    echo $ret;
            //}
            //echo '</tr>';
            //$ret ='';
        }
        foreach ($transToRemove as $key => $value) {

            $this->deleteTransaction($key);
        }

    }

    private function deleteTransaction($trans_num) {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $mpURL = $settings['mpUrl'];
        $mpKey = $settings['mpAPIKey'];

        $url = $mpURL . "transactions/".$trans_num;
        //curl_setopt($ch, CURLOPT_URL, $url);

        echo $url.'<br>';
        $ch = curl_init($url);
        $username = 'rowan.oberski@franklincommunity.coop';
        $password = 'F10cdb2@';
         
        //$data_string = json_encode($transInfo);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "DELETE" );
        //curl_setopt( $ch, CURLOPT_POSTFIELDS, $data_string );
        //curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';
        //$header[] = 'Content-Length: ' . strlen($data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        //$this->mapTrans($transInfo['trans_num'],$transInfo['trans_num']);
        echo '<tr>';
        echo '<td>'.$url.'</td><td>'.$response.'</td>';
        echo '</tr>';
        

        curl_close($ch);
    }


    private function echoTransaction($trans) {
        echo '<tr><table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>transaction'.$trans['trans_num'].'</th></tr><tr>';
        echo $this->loopArray($trans);
        echo '</tr></table></tr>';
    }

    private function loopArray($arr) {
        $ret = '';
        foreach($arr as $key => $value) {
            $ret .= '<td>';
            $ret .= $value;
            $ret .= '</td>';
        }
        return $ret;
    }
    private function getMPMemberships () {

    }
}

FannieDispatch::conditionalExec(false);