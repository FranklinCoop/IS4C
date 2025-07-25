<?php

class MailChimpTaskFCC extends FannieTask
{
    public function run()
    {

        //$this->exportAccounts();
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $client = new MailchimpMarketing\ApiClient();
        $client->setConfig(array(
            'apiKey' => $settings['MailChimpApiKey'],
            'server' => $settings['MailChimpPrefix'],
        ));
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $res = $dbc->query("SELECT email_1, CONCAT(UPPER(SUBSTRING(c.FirstName,1,1)), LOWER(SUBSTRING(c.FirstName,2, LENGTH(c.FirstName)))) as first_name, 
	        CONCAT(UPPER(SUBSTRING(c.LastName,1,1)), LOWER(SUBSTRING(c.LastName,2, LENGTH(c.LastName)))) as last_name, 
            street, phone, state, zip, card_no
            FROM meminfo AS m INNER JOIN custdata AS c
            ON m.card_no=c.CardNo AND c.personNum=1
            WHERE email_1 LIKE '%@%' and `Type` ='PC' and email_1 in ('rowan.oberski@fcc.coop')");
        
        try {
            $sent = array();
            $accounts = array();
            $errors = array();
            while ($row = $dbc->fetchRow($res)) {
                if (!isset($sent[$row['email_1']])) {
                    $merge_fields = array(
                        'EMAIL' => $row['email_1'],
                        'FNAME' => $row['first_name'],
                        'LNAME' => $row['last_name'],
                        'ADDRESS' => $row['street'],
                        'PHONE' => $row['phone'],
                        'STATE' => $row['state'],
                        'ZIP' => $row['zip'],
                        'CARDNO' => $row['card_no'],
                        
                    );
                    $accounts[] = array(
                        'email_address' => $row['email_1'],
                        'merge_fields' => $merge_fields,
                        'status' => 'subscribed',
                    );
                }
                $sent[$row['email_1']] = true;
                if (count($accounts) >= 450) {
                    $resp = $client->lists->batchListMembers($settings['MailChimpListID'], array(
                        'members' => $accounts,
                        'sync_tags' => false,
                        'update_existing' => false,
                    ));
                    //echo print_r($resp);
                    $accounts = array();
                }
            }
            if (count($accounts) > 0) {
                $resp = $client->lists->batchListMembers($settings['MailChimpListID'], array(
                    'members' => $accounts,
                    'sync_tags' => false,
                    'update_existing' => false,
                ));
                //echo print_r($resp);
            }
        } catch (Exception $ex) {
            echo $ex->getResponse()->getBody()->getContents();
        }
    }

    public function exportAccounts() {
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $client = new MailchimpMarketing\ApiClient();
        $client->setConfig(array(
            'apiKey' => $settings['MailChimpApiKey'],
            'server' => $settings['MailChimpPrefix'],
        ));

        try {
            $fields = array('members.id','members.email_address','members.merge_fields', 'members.last_changed');
            $resp = $client->lists->getListMembersInfo($settings['MailChimpListID'], $fields, null,1);
            //echo print_r($resp);
            $memberList = json_decode(json_encode($resp), true);
            //echo print_r($memberList);
            foreach ($memberList['members'] as $key => $value) {
                echo 'ID: '.$value['id'].'email: '.$value['email_address']."\n";
                if ($value['merge_fields']['CARDNO'] > 0){
                    $mcID = new MailchimpIDModel($dbc);
                    $mcID->mailchimpID($value['id']);
                    $mcID->cardNo(value['merge_fields']['CARDNO']);
                    $mcID->personNum(1);
                    $mcID->mailchimpEmail($value['email_address']);
                    $mcID->last_changed($value['merge_fields']['last_changed']);
                    $mcID->save();
                }
            }
        } catch (Exception $ex) {
            echo print_r($ex);//$ex->getResponse()->getBody()->getContents();
        }
        
    }
}
