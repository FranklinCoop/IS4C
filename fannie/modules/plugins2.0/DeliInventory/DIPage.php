<?php

use COREPOS\Fannie\API\lib\Store;
use COREPOS\Fannie\API\lib\FannieUI;

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

class DIPage extends FannieRESTfulPage
{
    protected $header = 'Deli Inventory';
    protected $title = 'Deli Inventory';

    public function preprocess()
    {
        $this->addRoute('get<catUp>', 'get<catDown>', 'post<newItem>',
            'post<newCat>', 'delete<catID>', 'post<oldCat><renameCat>',
            'post<seq><catID>');

        return parent::preprocess();
    }

    protected function post_seq_catID_handler()
    {
        $catP = $this->connection->prepare("SELECT name FROM DeliCategories WHERE deliCategoryID=?");
        $catName = $this->connection->getValue($catP, array($this->catID));
        $upP = $this->connection->prepare("UPDATE deliInventoryCat SET seq=?, categoryID=?, category=? WHERE id=?");
        $i = 0;
        $this->connection->startTransaction();
        foreach ($this->seq as $itemID) {
            $args = array($i, $this->catID, $catName, $itemID);
            $this->connection->execute($upP, $args);
            $i++;
        }
        $this->connection->commitTransaction();

        return false;
    }

    protected function post_oldCat_renameCat_handler()
    {
        $prep = $this->connection->prepare("UPDATE DeliCategories SET name=? WHERE deliCategoryID=?");
        $this->connection->execute($prep, array($this->renameCat, $this->oldCat));

        return 'DIPage.php';
    }

    protected function post_newCat_handler()
    {
        $storeID = Store::getIdByIp();
        $storeID=1;
        $prep = $this->connection->prepare("INSERT INTO DeliCategories (name, storeID) VALUES (?, ?)");
        $this->connection->execute($prep, array($this->newCat, $storeID));

        return 'DIPage.php';
    }

    protected function delete_catID_handler()
    {
        $prep = $this->connection->prepare('DELETE FROM DeliCategories WHERE deliCategoryID=?');
        $this->connection->execute($prep, array($this->catID));

        return 'DIPage.php';
    }

    protected function delete_id_handler()
    {
        $prep = $this->connection->prepare('DELETE FROM deliInventoryCat WHERE id=?');
        $this->connection->execute($prep, array($this->id));

        return 'DIPage.php';
    }

    protected function post_newItem_handler()
    {
        $storeID = Store::getIdByIp();
        $storeID=1;
        $catP = $this->connection->prepare("SELECT name FROM DeliCategories WHERE deliCategoryID=?");
        $catID = FormLib::get('newCatID');
        $catName = $this->connection->getValue($catP, array($catID));
        $insP = $this->connection->prepare("INSERT INTO deliInventoryCat
            (item, units, price, size, categoryID, category, vendorID, storeID)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $args = array(
            $this->newItem,
            FormLib::get('newUnits', 0),
            FormLib::get('newCost', 0),
            FormLib::get('newSize', ''),
            $catID,
            $catName,
            FormLib::get('newVendor', 0),
            $storeID,
        );
        $this->connection->execute($insP, $args);

        return 'DIPage.php';
    }

    protected function get_catUp_handler()
    {
        $upP = $this->connection->prepare("UPDATE DeliCategories SET seq=seq-1 WHERE deliCategoryID=?");
        $this->connection->execute($upP, array($this->catUp));
        $nameP = $this->connection->prepare('SELECT name FROM DeliCategories WHERE deliCategoryID=?');
        $name = $this->connection->getValue($nameP, array($this->catUp));
        $tag = str_replace(' ', '-', strtolower($name));

        return 'DIPage.php';
    }

    protected function get_catDown_handler()
    {
        $upP = $this->connection->prepare("UPDATE DeliCategories SET seq=seq+1 WHERE deliCategoryID=?");
        $this->connection->execute($upP, array($this->catDown));
        $nameP = $this->connection->prepare('SELECT name FROM DeliCategories WHERE deliCategoryID=?');
        $name = $this->connection->getValue($nameP, array($this->catDown));
        $tag = str_replace(' ', '-', strtolower($name));

        return 'DIPage.php';
    }

    protected function post_id_handler()
    {
        if (FormLib::get('name', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET item=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('name'), $this->id));
        } elseif (FormLib::get('size', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET size=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('size'), $this->id));
        } elseif (FormLib::get('caseSize', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET units=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('caseSize'), $this->id));
        } elseif (FormLib::get('cases', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET cases=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('cases'), $this->id));
        } elseif (FormLib::get('fractions', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET fraction=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('fraction'), $this->id));
        } elseif (FormLib::get('cost', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET price=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('cost'), $this->id));
        } elseif (FormLib::get('upc', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET upc=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('upc'), $this->id));
        } elseif (FormLib::get('sku', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET orderno=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('sku'), $this->id));
        } elseif (FormLib::get('vendor', false) !== false) {
            $upP = $this->connection->prepare("UPDATE deliInventoryCat SET vendorID=? WHERE id=?");
            $this->connection->execute($upP, array(FormLib::get('vendor'), $this->id));
        }

        return false;
    }

    private function addCategoryForm()
    {
        return <<<HTML
<div class="panel panel-default">
    <div class="panel panel-heading">Add Category</div>
    <div class="panel panel-body"><form method="post" action="DIPage.php">
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">Name</span>
                <input type="text" class="form-control input-sm" name="newCat" />
            </div>
        </div>
        <div class="form-group">
            <button class="btn btn-default" type="submit">Add Category</button>
        </div>
    </div>
</div>
<div class="panel panel-default">
    <div class="panel panel-heading">Rename Category</div>
    <div class="panel panel-body"><form method="post" action="DIPage.php">
        <div class="form-group">
            <select name="oldCat" class="form-control input-sm">
                <option value="Select category">
                {$this->cOpts}
            </select>
        </div>
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">New Name</span>
                <input type="text" class="form-control input-sm" name="renameCat" />
            </div>
        </div>
        <div class="form-group">
            <button class="btn btn-default" type="submit">Rename Category</button>
        </div>
    </div>
</div>
HTML;
    }

    private function addItemForm($storeID)
    {
        $vendors = new VendorsModel($this->connection);
        $vOpts = $vendors->toOptions();
        $catP = $this->connection->prepare("SELECT deliCategoryID, name FROM DeliCategories WHERE storeID=? ORDER BY name");
        $catR = $this->connection->execute($catP, array($storeID));
        $cOpts = '';
        while ($catW = $this->connection->fetchRow($catR)) {
            $cOpts .= "<option value=\"{$catW['deliCategoryID']}\">{$catW['name']}</option>";
        }
        $this->cOpts = $cOpts;
        return <<<HTML
<div class="panel panel-default">
    <div class="panel panel-heading">Add Item</div>
    <div class="panel panel-body"><form method="post" action="DIPage.php">
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">Item</span>
                <input type="text" class="form-control input-sm" name="newItem" />
            </div>
        </div>
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">Size</span>
                <input type="text" class="form-control input-sm" name="newSize" />
            </div>
        </div>
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">Units/Case</span>
                <input type="text" class="form-control input-sm" name="newUnits" />
            </div>
        </div>
        <div class="form-group">
            <div class="input-group">
                <span class="input-group-addon">Price/Case</span>
                <input type="text" class="form-control input-sm" name="newCost" />
            </div>
        </div>
        <div class="form-group">
            <select name="newVendor" class="form-control input-sm">
                <option value="">Select source vendor</option>
                {$vOpts}
            </select>
        </div>
        <div class="form-group">
            <select name="newCatID" class="form-control input-sm">
                <option value="">Select category</option>
                {$cOpts}
            </select>
        </div>
        <div class="form-group">
            <button class="btn btn-default" type="submit">Add Item</button>
        </div>
    </form></div>
</div>
HTML;
    }

    protected function get_view()
    {
        $storeID = Store::getIdByIp();
        $storeID=1;
        $catP = $this->connection->prepare("SELECT deliCategoryID, name FROM DeliCategories WHERE storeID=? ORDER BY seq, name");
        $itemP = $this->connection->prepare("SELECT i.*, v.vendorName
            FROM deliInventoryCat AS i
                LEFT JOIN vendors AS v ON i.vendorID=v.vendorID
            WHERE categoryID=? ORDER BY seq, item");
        $catR = $this->connection->execute($catP, array($storeID));
        $storeP = $this->connection->prepare("SELECT description FROM Stores WHERE storeID=?");
        $storeName = $this->connection->getValue($storeP, array($storeID));
        $ret = "<h3>{$storeName}</h3>";
        $ret .= '<div class="hidden-print"><a href="" onclick="di.showSourcing(); return false;">Show Sourcing</a>
            |
            <a href="" onclick="di.hideSourcing(); return false;">Hide Sourcing</a></div>';
        $ret .= '<div class="row hidden-print"><div class="col-sm-4">';
        while ($catW = $this->connection->fetchRow($catR)) {
            $tag = str_replace(' ', '-', strtolower($catW['name']));
            $ret .= sprintf('<a href="#%s">%s</a><br />', $tag, $catW['name']);
        }
        $ret .= '</div><div class="col-sm-4">';
        $ret .= $this->addItemForm($storeID);
        $ret .= '</div><div class="col-sm-4">';
        $ret .= $this->addCategoryForm();
        $ret .= '</div></div>';

        $catR = $this->connection->execute($catP, array($storeID));
        while ($catW = $this->connection->fetchRow($catR)) {
            $itemR = $this->connection->execute($itemP, array($catW['deliCategoryID']));
            $tag = str_replace(' ', '-', strtolower($catW['name']));
            $ret .= sprintf('<a name="%s"></a>
                <h3>%s
                <a href="DIPage.php?catUp=%d"><span class="glyphicon glyphicon-arrow-up"></span></a>
                <a href="DIPage.php?catDown=%d"><span class="glyphicon glyphicon-arrow-down"></span></a>
                </h3>', $tag, $catW['name'], $catW['deliCategoryID'], $catW['deliCategoryID']);
            if ($this->connection->numRows($itemR) == 0) {
                $ret .= sprintf('<a href="DIPage.php?_method=delete&catID=%d"
                    class="btn btn-default btn-danger">Delete this category</a>',
                    $catW['deliCategoryID']);
                continue;
            }
            $ret .= '<table class="table table-bordered table-striped small inventory-table"
                        data-cat-id="' . $catW['deliCategoryID'] . '">';
                        //data-cat-id="' . $catW['deliCategoryID'] . '" style="page-break-after: always;">';
            $ret .= '<tr><th>Item</th><th>Size</th><th>Units/Case</th><th>Cases</th><th>#/Each</th><th>Price/Case</th>
                     <th>Total</th><th class="upc">UPC</th><th class="sku">SKU</th><th class="vendor">Source</th></tr>';
            $sum = 0;
            while ($itemW = $this->connection->fetchRow($itemR)) {
                $total = $itemW['cases'] * $itemW['price'];
                if ($itemW['units'] != 0) {
                    $total = ($itemW['cases'] * $itemW['price']) + (($itemW['fraction'] / $itemW['units']) * $itemW['price']);
                }
                if ($total == INF) {
                    $total = 0;
                }
                $ret .= sprintf('<tr data-item-id="%d">
                    <td class="name editable">%s</td>
                    <td class="size editable">%s</td>
                    <td class="caseSize editable">%d</td>
                    <td class="cases editable">%.2f</td>
                    <td class="fractions editable">%.2f</td>
                    <td class="cost editable">$%.2f</td>
                    <td class="total">$%.2f</td>
                    <td class="upc editable">%s</td>
                    <td class="sku editable">%s</td>
                    <td class="vendor">%s</td>
                    <td class="trash"><a href="DIPage.php?_method=delete&id=%d">%s</a></td>
                    </tr>',
                    $itemW['id'],
                    $itemW['item'],
                    $itemW['size'],
                    $itemW['units'],
                    $itemW['cases'],
                    $itemW['fraction'],
                    $itemW['price'],
                    $total,
                    $itemW['upc'],
                    $itemW['orderno'],
                    $itemW['vendorName'],
                    $itemW['id'], FannieUI::deleteIcon()
                );
                $sum += $total;
            }
            $ret .= sprintf('<tr><th colspan="6">Grand Total</th><th>$%.2f</th><th class="trash" colspan="3"></tr>', $sum);
            $ret .= '</table>';
        }

        $vendR = $this->connection->query("SELECT vendorID, vendorName FROM vendors WHERE inactive=0 ORDER BY vendorName");
        $vendors = array();
        while ($row = $this->connection->fetchRow($vendR)) {
            $vendors[] = array(
                'id' => $row['vendorID'],
                'name'=> $row['vendorName'],
            );
        }
        $vendors = json_encode($vendors);

        $this->addScript('../../../src/javascript/chosen/chosen.jquery.min.js');
        $this->addCssFile('../../../src/javascript/chosen/bootstrap-chosen.css');
        $this->addScript('di.js?date=20190129');
        $this->addOnloadCommand('di.initRows();');
        $this->addOnloadCommand("di.setVendors({$vendors});");

        return $ret;
    }
}

FannieDispatch::conditionalExec();

