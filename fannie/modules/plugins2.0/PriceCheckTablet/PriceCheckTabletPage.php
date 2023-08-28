<?php

/**
 * This is intended for use with some kind of in-aisle
 * device and as such is purposely lacking all menus and
 * so forth
 */

use COREPOS\Fannie\API\item\ItemText;
use COREPOS\Fannie\API\lib\Store;

include(__DIR__ . '/../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}
if (!class_exists('COREPOS\\pos\\lib\\PrintHandlers\\PrintHandler')) {
    include(__DIR__ . '/../../../../pos/is4c-nf/lib/PrintHandlers/PrintHandler.php');
}
if (!class_exists('COREPOS\\pos\\lib\\PrintHandlers\\ESCPOSPrintHandler')) {
    include(__DIR__ . '/../../../../pos/is4c-nf/lib/PrintHandlers/ESCPOSPrintHandler.php');
}
if (!class_exists('COREPOS\\pos\\lib\\PrintHandlers\\ESCNetRawHandler')) {
    include(__DIR__ . '/../../../../pos/is4c-nf/lib/PrintHandlers/ESCNetRawHandler.php');
}

class PriceCheckTabletPage extends FannieRESTfulPage
{
    public function preprocess()
    {
        $this->addRoute('get<done>', 'get<back>');
        if (!isset($this->session->pctItems) || !is_array($this->session->pctItems)) {
            $this->session->pctItems = array();
        }
        $ret = parent::preprocess();
        $this->window_dressing = false;

        return $ret;
    }

    protected function get_id_handler()
    {
        $upc1 = BarcodeLib::padUPC($this->id);
        $upc2 = '0' . substr($upc1, 0, 12);

        $query = "
            SELECT p.normal_price,
                p.special_price,
                p.discounttype,
                " . ItemText::longBrandSQL() . ",
                " . ItemText::longDescriptionSQL() . "
            FROM products AS p
                LEFT JOIN productUser AS u ON p.upc=u.upc
            WHERE p.store_id=?
                AND p.upc=?";
        $prep = $this->connection->prepare($query);
        $store = 1;//Store::getIdByIp();
        $row = $this->connection->getRow($prep, array($store, $upc1));
        if ($row === false) {
            $row = $this->connection->getRow($prep, array($store, $upc2));
        }

        if ($row === false) {
            echo '<div class="h2 alert alert-danger">Item not found</div>';
            return false;
        }

        $item = ($row['brand'] != '' ? $row['brand'] . ' ' : '') . $row['description'];
        switch ($row['discounttype']) {
            case 1:
                $price = sprintf('Sale Price: $%.2f', $row['special_price']);
                break;
            case 0:
                $price = sprintf('Price: $%.2f', $row['normal_price']);
                break;
        }


        echo "<div class=\"h2\">{$item}</div><div class=\"h2\">{$price}</div>";
        echo "<div class=\"col-sm-5\"><br /><a href=\"PriceCheckTabletPage.php?done=1\" class=\"btn btn-success btn-lg\">Print</a>
        <br />
        <br />
        <a href=\"PriceCheckTabletPage.php?back=1\" class=\"btn btn-danger btn-lg\">Back</a>
        </div>";

        $items = $this->session->pctItems;
        
        $items[] = array(
            //'upc' => $row['upc'],
            'price' => $price,
            'name' => $row['description'],
        );
        $this->session->pctItems = $items;

        return false;
    }

        /**
     * Finish transaction.
     *
     * Generates receipt and sends to printer, twice, via
     * network.
     *
     * Adds items to dtransactions, copies them to suspended,
     * and flips the dtransactions records to trans_status=X
     */
        /**
     * Just clear session data to start over
     */
    protected function get_back_handler()
    {
        $this->session->pctItems = array();
        return 'PriceCheckTabletPage.php';
    }

    protected function get_done_handler()
    {


        $ph = new COREPOS\pos\lib\PrintHandlers\ESCPOSPrintHandler();
        $receipt = "\n"
            . $ph->textStyle(true, false, true)
            //. 'Order #' . $orderNumber . "\n"
            . date('n j, Y g:i:a') . "\n";
        
            $i = sizeof($this->session->pctItems);
            $item = $this->session->pctItems[$i-1];
            $receipt .= $item['name'] . "\n";
            $receipt .= str_pad($item['price'], 4) . ' ';
            $receipt .= "\n";

        $receipt .= str_repeat("\n", 4);
        $receipt .= $ph->cutPaper();


        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $ipAdd = $FANNIE_PLUGIN_SETTINGS['T1PrintIP'];

        $net = new COREPOS\pos\lib\PrintHandlers\ESCNetRawHandler();
        $net->setTarget('192.168.2.105:9100');
        $net->writeLine($receipt);
        //$net->writeLine($receipt);

        $this->session->pctItems = array();

        return 'PriceCheckTabletPage.php';
    }
    
    protected function get_view()
    {
        $this->addJQuery();
        $this->addBootstrap();
        $this->addScript('priceCheckTablet.js');
        $this->addOnloadCommand("\$('#pc-upc').focus();");
        $this->addOnloadCommand("priceCheckTablet.showDefault();");
        if (file_exists(__DIR__ . '/../../../src/javascript/composer-components/bootstrap/css/bootstrap.min.css')) {
            $bootstrap = '../../../src/javascript/composer-components/bootstrap/css/';
        } elseif (file_exists(__DIR__ . '/../../src/javascript/bootstrap/css/bootstrap.min.css')) {
            $bootstrap = '../../../src/javascript/bootstrap/css/';
        }
        return <<<HTML
<!DOCTYPE html> 
<html>
<head>
    <link rel="stylesheet" type="text/css" href="{$bootstrap}bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{$bootstrap}bootstrap-theme.min.css">
</head>
<body class="container">
<form method="get" id="pc-form" onsubmit="priceCheckTablet.search(); return false;">
    <div class="form-inline">
        <input type="text" class="form-control form" name="id" id="pc-upc" autocomplete="off" />
        <button type="submit" class="btn btn-default btn-success">Search</button>
    </div>
</form>
<div id="pc-results" class="well"></div>

HTML;
    }
}

FannieDispatch::conditionalExec();

