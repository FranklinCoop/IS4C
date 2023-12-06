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

class PriceCheckTabletPage2 extends FannieRESTfulPage
{
    
    public function preprocess()
    {
        $this->addRoute('get<done>', 'get<back>');
        if (!isset($this->session->pctItems) || !is_array($this->session->pctItems)) {
            $this->session->pctItems = array();
        }
        if (FormLib::get_form_value('serialNumber') != null && FormLib::get_form_value('serialNumber') != '') {
            $this->session->serialNumber = FormLib::get_form_value('serialNumber');
        }


        $ret = parent::preprocess();
        $this->window_dressing = false;

        return $ret;
    }

    public function __construct()
    {
        
        parent::__construct();
    }
    /**
     * handles back button click
     **/
    protected function get_back_handler()
    {
        $this->session->pctItems = array();
        return 'PriceCheckTabletPage2.php?serialNumber='.$this->session->serialNumber;
    }
    /**
     * Prints price and resets for next scan
     **/
    protected function get_done_handler()
    {
        //create receipt
        $ph = new COREPOS\pos\lib\PrintHandlers\ESCPOSPrintHandler();
        $receipt = "\n"
            . $ph->textStyle(true, false, true)
            . date('n j, Y g:i:a') . "\n";
        
        $i = sizeof($this->session->pctItems);
        if ($i > 0) {
            $item = $this->session->pctItems[$i-1];
            $receipt .= $item['upc'] . "\n";
            $receipt .= $item['name'] . "\n";
            $receipt .= str_pad($item['price'], 4) . ' ';
            $receipt .= "\n";
    
            $receipt .= str_repeat("\n", 4);
            $receipt .= $ph->cutPaper();
    
    
            $tabletSN = $this->session->serialNumber;
            $printerIP = $this->getPrinterIP();
            
            if ($printerIP != 0) {
                $net = new COREPOS\pos\lib\PrintHandlers\ESCNetRawHandler();
                $net->setTarget($printerIP.':9100');
                $net->writeLine($receipt);
            }

            //$net->writeLine($receipt);
            //clear memory we don't need it anymore if because we printed the receipt
            $this->session->pctItems = array();
        }


        return 'PriceCheckTabletPage2.php?serialNumber='.$tabletSN;
    }
    /**
     * Handles upcs 
     **/

    protected function get_id_handler()
    {
        $upc1 = BarcodeLib::padUPC($this->id);
        $upc2 = '0' . substr($upc1, 0, 12);

        $query = "
            SELECT p.normal_price,
                p.special_price,
                p.discounttype,
                " . ItemText::longBrandSQL() . ",
                " . ItemText::longDescriptionSQL() . ",
                p.upc
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
            echo '<div class="h1 alert alert-danger">Item not found</div>';
            return false;
        }
        $upc = 'UPC:'.$row['upc'];

        $item = ($row['brand'] != '' ? $row['brand'] . ' ' : '') . $row['description'];
        switch ($row['discounttype']) {
            case 1:
                $price = sprintf('Sale Price: $%.2f', $row['special_price']);
                break;
            case 2:
            case 0:
                $price = sprintf('Price: $%.2f', $row['normal_price']);
                break;
        }

        $printBtn = '';
        if ($this->getPrinterIP() != 0) {
            $printBtn = "<a href=\"PriceCheckTabletPage2.php?done=1\" class=\"btn btn-success btn-lg btn-block p-5\">Print</a>";
        }

        echo "<div class=\"h1\">{$item}</div><div class=\"h1\">{$upc}</div><div class=\"h1\">{$price}</div>";
        echo "<div class=\"col text-center\"><br /><br />
           {$printBtn}
        {$this->printerIP}
        <a href=\"PriceCheckTabletPage2.php?back=1\" class=\"btn btn-danger btn-lg btn-block p-5\">Back</a>
        </div>";

        $items = $this->session->pctItems;
        
        $items[] = array(
            'upc' => $row['upc'],
            'price' => $price,
            'name' => $row['description'],
        );
        $this->session->pctItems = $items;

        return false;
    }

    private function getPrinterIP(){
        //aassign values to printer Array;
        GLOBAL $FANNIE_PLUGIN_SETTINGS;
        $ipAdd = $FANNIE_PLUGIN_SETTINGS['tabletSerials'];
        $serials = explode(',', $FANNIE_PLUGIN_SETTINGS['tabletSerials']);
        $printers = explode(',', $FANNIE_PLUGIN_SETTINGS['printIPs']);
        $printerIP = 0;
        foreach ($serials as $serial => $number){
            if ($number == $this->session->serialNumber) {
                $printerIP = $printers[$serial];
            }
        }
        return $printerIP;
    }

    
    protected function get_view()
    {
        $this->addJQuery();
        $this->addBootstrap();
        $this->addScript('priceCheckTablet2.js');
        $this->addOnloadCommand("\$('#pc-upc').focus();");
        $this->addOnloadCommand("priceCheckTablet2.showDefault();");
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
<br />
<form method="get" id="pc-form" onsubmit="priceCheckTablet2.search(); return false;">
    <div class="form-inline text-center">
        <input type="text" class="form-control form" name="id" id="pc-upc" autocomplete="off" />
        <button type="submit" id="pc-search" class="btn btn-default btn-success">Search</button>
    </div>
</form>
<br />
<div id="pc-results" class="well well-lg text-center"></div>

HTML;

    }
}

FannieDispatch::conditionalExec();