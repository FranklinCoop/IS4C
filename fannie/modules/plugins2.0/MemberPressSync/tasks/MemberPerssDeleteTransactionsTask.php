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
class MemberPressDeleteTransactionsTask extends FannieTask 
{
    public $name = 'Delete Member Press Transactions';

    public $description = 'Do not run, this is for testing to remove transaction imports so that the importer can be run again with bugs fixed. ';

    public $default_schedule = array(
        'min' => 0,
        'hour' => 1,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
    );

    private $mpURL ='';
    private $mpKey = '';
    

    
    public function run()
    {
       # http://localhost/CORE/fannie/modules/plugins2.0/MemberPressSync/TestPages/MPSDeleteTransactionPage.php
       $conf = FannieConfig::factory();
       $settings = $conf->get('PLUGIN_SETTINGS');
       $mpURL = $settings['mpUrl'];
       $mpKey = $settings['mpAPIKey'];

       $params = array('page' => 1, 'per_page'=>99999);
       $url = $mpURL . "transactions" . '?' . http_build_query($params);
       //curl_setopt($ch, CURLOPT_URL, $url);

       //echo $url.'<br>';
       $ch = curl_init($url);

       //$postOpts = array('page' => 1, 'per_page'=>99999);
       //$postOptsJSON = json_encode($postOpts);
       curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
       curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "GET" );
       //curl_setopt($ch, CURLOPT_POST, true); 
       //curl_setopt( $ch, CURLOPT_POSTFIELDS, $postOptsJSON );
       //echo $postOptsJSON;

       $header = array();
       $header[] = 'MEMBERPRESS-API-KEY: '.$mpKey; // Your API KEY from MemberPress Developer Tools Here
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
       #echo '<table class="table table-bordered table-striped table-condensed">';
       #echo '<tr><th>Transactions</th></tr><tr>';
       $transToRemove = array();
       //foreach ($transactions[0] as $key => $value){
       //    echo '<th>'.$key.'</th>';
      // }
      #echo '<th>id</th><th>gateway</th>';
      # echo '</tr>';
       
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
           $this->deleteTransaction($key,$mpURL,$mpKey);
       }
    }


    private function deleteTransaction($trans_num,$mpURL,$mpKey) {

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
       # echo '<tr>';
        echo 'URL: '.$url.' Response'.$response."\n";
        #echo '</tr>';
        

        curl_close($ch);
    }

    private function truncateTables(){
        $queries = array("TRUNCATE TABLE core_op.MemberPressMemberMap",
            "TRUNCATE TABLE core_op.MemberPressTransactionMap",
            "TRUNCATE TABLE core_op.MemberPressPaymentPlanMap",
            "TRUNCATE TABLE core_trans.dtransactions");
        foreach ($queries as $key => $query) {
            $prep = $connection->prepare($query);
            $row = $connection->execute($query, array());
        }
    }
    
}

