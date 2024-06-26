<?php 
require(dirname(__FILE__) . '/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}

class TestWebHook extends FanniePage 
{
    public $page_set = 'Plugin :: MemberPressSync';
    public $themed = false;

    /**
      Preprocess runs before the page is displayed.
      It handles form input.
    */
    private $display_func;

    public function preprocess()
    {
        global $FANNIE_PLUGIN_SETTINGS;
        //$ts_db = FannieDB::get($FANNIE_PLUGIN_SETTINGS['TimesheetDatabase']);
        $this->header = 'MemberPress Sync - WebHook Test';
        $this->title = 'Fannie - MP Webhook Test';
        $this->display_func = '';

        //GLOBAL $FANNIE_PLUGIN_SETTINGS;
        //$dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        //$json = json_decode($data);

        //$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        //file_put_contents('test.txt', file_get_contents('php://input')."\n");
        //file_put_contents('test.txt', $actual_link."\n\n");
        //echo json_encode($json);
        //echo var_dump($actual_link)."\n".$this->input;
        file_put_contents('test.txt', file_get_contents('php://input')."\n", FILE_APPEND);


        foreach ($_POST as $key => $value) {
            $this->display_func .= $key." => ".$value."\n";
        }

        return true;
    }
        /**
      Uses parent method to setup all javascript and css includes
      but returns an ultra simple header. Receipt page needs
      to be printable on paper
    */
    public function getHeader()
    {
        //parent::getHeader();
        return '';
    }

    /**
      Simple footer matches simple header
    */
    public function getFooter()
    {
        return '';
    }

    private function getReceiptDate($form)
    {
        // see if date was passed in
        try {
            $stamp = strtotime($form->date);
            if ($stamp === false) {
                return false;
            } else {
                return date('Y-m-d', $stamp);
            }
        } catch (Exception $ex) {}

        // see if year, month, and day were passed in
        try {
            $stamp = mktime(0, 0, 0, $form->month, $form->day, $form->year);
            return date('Y-m-d', $stamp);
        } catch (Exception $ex) {
            return false;
        }
    }


    function body_content()
    {
        echo $this->display_func;
    }
}

FannieDispatch::conditionalExec();

