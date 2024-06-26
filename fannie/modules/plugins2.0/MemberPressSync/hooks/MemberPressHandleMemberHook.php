<?php
/*******************************************************************************

    Copyright 2018 Franklin Community co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

require(dirname(__FILE__) . '/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}
//file_put_contents('test.txt', file_get_contents('php://input'));
class MemberPressHandleMemberHook extends FannieRESTfulPage
{
    protected $header = 'WebHook Test';
    protected $title = 'WebHook Test';

    public $description = '[Webhook Test] Testing incoming webhooks from memberpress on the website.';

    public $themed = true;
    

    public function preprocess()
    {
        //$this->__routes[] = 'get<date><store><pdf>';
        $this->__routes[] = 'post<contents>';
        //$this->__routes[] = 'post<date><store>';
        //$this->__routes[] = 'post<id><value>';
        //$this->__routes[] = 'post<id><total>';
        //$this->__routes[] = 'post<id><notes>';
        return parent::preprocess();
    }

    public function post_contents_handler() {
        //$json = $json_decode($this->contents);
        echo 'TEST';
        return false;
    }

    public function post_handler()
    {
        //GLOBAL $FANNIE_PLUGIN_SETTINGS;
        //$dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        //$json = json_decode($data);

        $actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        file_put_contents('test.txt', file_get_contents('php://input')."\n");
        //file_put_contents('test.txt', $actual_link."\n\n");
        //echo json_encode($json);
        echo var_dump($actual_link)."\n".$this->input;
        return false;
    }

    public function get_view()
    {
        $dbc = $this->connection;
        $table = 'HELLO WORLD </br>';
        ob_start();
        ?>
        <div id="displayarea">
            <?php echo $table; ?>
        </div>

        <?php
        return ob_get_clean();

    }

    public function helpContent()
    {
        return '<p>
            A page for testing webhooks recvied from memberpress.
            </p>';
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
    }
}

FannieDispatch::conditionalExec();
/*
<?php
echo "<body><b>HELLO WORLD!</b></body>";
file_put_contents('test.txt', file_get_contents('php://input'));
?>
<table>
<?php 


    foreach ($_POST as $key => $value) {
        echo "<tr>";
        echo "<td>";
        echo $key;
        echo "</td>";
        echo "<td>";
        echo $value;
        echo "</td>";
        echo "</tr>";
    }


?>
</table>
*/