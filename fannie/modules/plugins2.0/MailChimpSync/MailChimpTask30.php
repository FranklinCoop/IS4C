<?php

class MailChimpTask30 extends FannieTask
{
    public function run()
    {
        $settings = $this->config->get('PLUGIN_SETTINGS');
        $client = new MailchimpMarketing\ApiClient();
        $client->setConfig(array(
            'apiKey' => $settings['MailChimpApiKey'],
            'server' => $settings['MailChimpPrefix'],
        ));
        $dbc = FannieDB::get($this->config->get('OP_DB'));
        $res = $dbc->query("SELECT email_1 FROM meminfo AS m INNER JOIN custdata AS c
            ON m.card_no=c.CardNo AND c.personNum=1
            WHERE email_1 LIKE '%@%'");
        try {
            $sent = array();
            $accounts = array();
            while ($row = $dbc->fetchRow($res)) {
                if (!isset($sent[$row['email_1']])) {
                    $accounts[] = array(
                        'email_address' => $row['email_1'],
                        'status' => 'subscribed',
                    );
                    echo $this->cronMsg("account email: ".$row['email_1']."\n");
                }
                $sent[$row['email_1']] = true;
                if (count($accounts) >= 450) {
                    $resp = $client->lists->batchListMembers($settings['MailChimpListID'], array(
                        'members' => $accounts,
                        'sync_tags' => false,
                        'update_existing' => false,
                    ));
                    $accounts = array();
                    echo "Over 450 accounts:".var_dump($resp)."\n";
                }
            }
            if (count($accounts) > 0) {
                $resp = $client->lists->batchListMembers($settings['MailChimpListID'], array(
                    'members' => $accounts,
                    'sync_tags' => false,
                    'update_existing' => false,
                ));
                echo "Uploading Accounts";
            }
        } catch (Exception $ex) {
            echo $ex->getResponse()->getBody()->getContents();
        }
    }
}
