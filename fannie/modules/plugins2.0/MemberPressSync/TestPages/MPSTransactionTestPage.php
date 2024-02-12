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

class MPSTransactionTestPage extends FanniePage {

	protected $header = 'Member Press Transaction Test Page';
	protected $title = 'Member Press Transaction Test';

    public function __construct(){
        parent::__construct();
        $conf = FannieConfig::factory();
        $settings = $conf->get('PLUGIN_SETTINGS');
        $this->mpURL = $settings['mpUrl'];
        $this->mpKey = $settings['mpAPIKey'];
    }

    public function body_content()
    {
       # http://localhost/CORE/fannie/modules/plugins2.0/MemberPressSync/TestPages/MPSTransactionTestPage.php
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $mpURL = $settings['mpUrl'];
        $mpKey = $settings['mpAPIKey'];

        $params = array('page' => 1, 'per_page'=>99999, 'member'=>$memberID);
        $url = $mpURL . "transactions/" . '?' . http_build_query($params);
        

        echo $url."\n";
        $ch = curl_init($url);

  
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );

        $header = array();
        $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
        $header[] = 'Content-Type: application/json';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $response = curl_exec($ch);
        echo "<div><p>".$response."</p></br></div>";

        if(curl_errno($ch)){
            throw new Exception(curl_error($ch));
        }
        
        $transactions = json_decode($response, true);

        curl_close($ch);

        //$transactions = MemberPressSyncLib::getTransactions($member['id'],$this->mpURL, $this->mpKey);
        echo '<table class="table table-bordered table-striped table-condensed"><tr><th>TransID</th><th>Trans_num</th><th>Amount</th><th>member</th><th>status</th><th>gateway</th><th>created_at</th></tr>';
        foreach ($transactions as $transaction) {
            echo '<tr><td>';
            echo $transaction['id'];
            echo '</td><td>';
            echo $transaction['trans_num'];
            echo '</td><td>';
            echo $transaction['amount'];
            echo '</td><td>';
            echo $transaction['member']['id'];
            echo '</td><td>';
            echo $transaction['status'];
            echo '</td><td>';
            echo $transaction['gateway'];
            echo '</td><td>';
            echo $transaction['created_at'];
            echo '</td></tr>';
        }
        echo '</table></td></tr>';

        
        // get members
        //curl "https://www.franklincommunity.coop/wp-json/mp/v1/members?page=2&per_page=10" \
        //-H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7"

        //get transactions
        //curl "https://www.franklincommunity.coop/wp-json/mp/v1/transactions?page=2&per_page=10" \
       //-H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7"
        

       /* create member
        $ curl -X POST "https://www.franklincommunity.coop/wp-json/mp/v1/members" \
       -H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7" \
       -d email="kipperdeutsch@gmail.com" \
       -d username="kipperdeutsch@gmail.com" \
       -d first_name=Kipper \
       -d last_name=Deutsch 
       /*

       /* update member
        -X POST "https://www.franklincommunity.coop/wp-json/mp/v1/members/639" \
       -H "MEMBERPRESS-API-KEY: ZiMQ0jtNY7" \
       -d email="kipperdeutsch@gmail.com" \
       -d username="kipperdeutsch@gmail.com" \
       -d first_name=Kipper \
       -d last_name=Deutsch 
       */
    }


    private function echoTransaction($trans) {
        echo '<table class="table table-bordered table-striped table-condensed">';
        echo '<tr><th>transaction'.$trans['trans_num'].'</th></tr><tr>';
        $this->loopArray($trans);
        echo '</tr></table>';
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