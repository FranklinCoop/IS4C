<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Community Co-op

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

require(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../classlib2.0/FannieAPI.php');
}

class ProdLocationEditor extends FannieRESTfulPage
{
    protected $header = 'Product Location Update';
    protected $title = 'Product Location Update';
    protected $sortable = true;

    public $description = '[Product Location Update] find and update products missing
        floor section locations.';
    public $has_unit_tests = true;

    private $date_restrict = 1;
    private $data = array();

    function preprocess()
    {
        $this->__routes[] = 'get<start>';
        $this->__routes[] = 'get<batch>';
        $this->__routes[] = 'post<batch><save>';
        $this->__routes[] = 'post<list><save>';
        $this->__routes[] = 'post<upc><save>';
        $this->__routes[] = 'get<start>';
        $this->__routes[] = 'post<newLocation>';
        $this->__routes[] = 'get<list>';
        $this->__routes[] = 'post<delete_location>';
        $this->__routes[] = 'get<remove>';
        $this->__routes[] = 'post<remove_list>';
        return parent::preprocess();
    }

    function post_remove_list_handler()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $storeA = FormLib::get('storeA', 999);
        $storeB = FormLib::get('storeB', 999);
        $removeList = FormLib::get('remove_list', array());
        $upcs = explode("\r\n", $removeList);
        $fspmIDs = array();

        list($inStr, $args) = $dbc->safeInClause($upcs);
        $args[] = $storeA;
        $args[] = $storeB;
        $prep = $dbc->prepare("SELECT floorSectionProductMapID FROM FloorSectionProductMap AS m INNER JOIN FloorSections AS s 
            ON s.floorSectionID=m.floorSectionID WHERE upc IN ($inStr) AND s.storeID IN (?, ?)");
        $res = $dbc->execute($prep, $args); 
        while ($row = $dbc->fetchRow($res)) {
            $fspmIDs[] = $row['floorSectionProductMapID'];
        }

        list($removeInStr, $removeA) = $dbc->safeInClause($fspmIDs);
        $removeP = $dbc->prepare("DELETE FROM FloorSectionProductMap WHERE floorSectionProductMapID IN ($removeInStr)");
        $removeR = $dbc->execute($removeP, $removeA);

        return 'ProdLocationEditor.php';
    }
    
    function get_remove_view()
    {
        return <<<HTML
<div class="row">
    <div class="col-lg-4">
        <button onClick="location.href = 'ProdLocationEditor.php'" class="btn btn-default">Back</button>
    </div>
    <div class="col-lg-4">
        <h5>Remove Floor Locations</h5>
        <form action="ProdLocationEditor.php" method="post">
            <div class="form-group">
                <textarea class="form-control" name="remove_list" rows=10></textarea>
            </div>
        <label for="storeA">Store A</label>
            <div class="form-group">
                <input type="checkbox" class="form-control" name="storeA" value="1"/>
            </div>
        <label for="storeA">Store B</label>
            <div class="form-group">
                <input type="checkbox" class="form-control" name="storeB" value="2"/>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-default" />
            </div>
        </form>
    </div>
    <div class="col-lg-4"></div>
</div>
HTML;
    }

    function post_delete_location_handler()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $upc = FormLib::get('upc');
        $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
        $location = FormLib::get('location');
        $args = array($upc, $location);
        $prep = $dbc->prepare('
            DELETE FROM FloorSectionProductMap
            WHERE upc = ?
                AND floorSectionID = ?
        ');
        $dbc->execute($prep, $args);

        return false;
    }

    function post_newLocation_view()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $upc = FormLib::get('upc');
        $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
        $newLocation = FormLib::get('newLocation');
        $storeID = FormLib::get("storeID", 1);

        $args = array($upc, $newLocation);
        $prep = $dbc->prepare('
            INSERT INTO FloorSectionProductMap (upc, floorSectionID)
                values (?, ?)
        ');
        $dbc->execute($prep, $args);

        $ret = '';
        if ($dbc->error()) {
            $ret .= '<div class="alert alert-danger">Save Failed</div>';
            $ret .= '<div class="alert alert-warning">Error: ' . $dbc->error() . '</div>';
        } else {
            $ret .= '<div class="alert alert-success">Product Location Saved</div>';
        }
        $ret .= '<a class="btn btn-default" href="ProdLocationEditor.php">Home</a><br><br>';
        if (FormLib::get('batchCheck', false)) {
            $ret .= '<br><a class="btn btn-default" href="../../../scancoord/ScannieV2/content/Scanning/BatchCheck/SCS.php">
                Back to Batch Check</a><br><br>';
        } else {
            $ret .= '<br><a class="btn btn-default" href="ProdLocationEditor.php">Back</a><br><br>';
        }

        return $ret;
    }

    function post_upc_save_view()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $upc = FormLib::get('upc');
        $mapID = array();
        $secID = array();
        foreach ($_POST as $key => $value) {
            if(substr($key,0,7) == 'section') {
                $secID[substr($key,7)] = $value;
            }
        }
        $count = FormLib::get('numolocations');
        $newSection = array();
        $mapID = array();
        for ($i = 1; $i <= $count; $i++) {
            $curName = 'newSection' . $i;
            $oldName = 'mapID' . $i;
            $newSection[] = FormLib::get($curName);
        }
        foreach ($secID as $mapID => $sectionID) {
            if ($sectionID != 999) {
                $args = array($sectionID, $upc, $mapID);
                $prep = $dbc->prepare('
                    UPDATE FloorSectionProductMap
                    SET floorSectionID = ?
                    WHERE upc = ?
                        AND floorSectionProductMapID = ?;
                ');
                $dbc->execute($prep, $args);
            } else {
                $args = array($upc, $mapID);
                $prep = $dbc->prepare('
                    DELETE FROM FloorSectionProductMap
                    WHERE upc = ?
                        AND floorSectionProductMapID = ?;
                ');
                $dbc->execute($prep, $args);

            }
        }

        $ret = '';
        if ($dbc->error()) {
            $ret .= '<div class="alert alert-danger">Save Failed</div>';
            $ret .= '<div class="alert alert-warning">Error: ' . $dbc->error() . '</div>';
        } else {
            $ret .= '<div class="alert alert-success">Product Location Saved</div>';
        }
        if (FormLib::get('batchCheck', false)) {
            $ret .= '<br><a class="btn btn-default" href="../../../scancoord/ScannieV2/content/Scanning/BatchCheck/SCS.php">
                Back to Batch Check</a><br><br>';
        } else {
            $ret .= '<br><a class="btn btn-default btn-back" href="ProdLocationEditor.php">Back</a><br><br>';
        }



        return $ret;
    }

    function post_batch_save_view()
    {

        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $storeID = FormLib::get('storeID', 1);
        $start = FormLib::get('start');
        $end = FormLib::get('end');

        $ret = '';
        $item = array();
        foreach ($_POST as $upc => $section) {
            if (!is_numeric($upc)) continue;
            $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
            if ($section > 0) $item[$upc] = $section;
        }

        $prep = $dbc->prepare('
            INSERT INTO FloorSectionProductMap (upc, floorSectionID) values (?, ?);
        ');
        foreach ($item as $upc => $section) {
            $args = array($upc, $section );
            $dbc->execute($prep, $args);
        }
        $ret .= '<div class="alert alert-success">Update Successful</div>';

        $cache = new FloorSectionsListTableModel($dbc);
        $cache->refresh();

        $ret .= '<br><br><a class="btn btn-default btn-back" href="javascript:history.back()">Back</a><br><br>';
        $ret .= '<a class="btn btn-default" href="ProdLocationEditor.php">Return</a><br><br>';

        return $ret;
    }

    function post_list_save_view()
    {

        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $storeID = FormLib::get('storeID', 1);
        $start = FormLib::get('start');
        $end = FormLib::get('end');
        $ret = '';
        $item = array();
        foreach ($_POST as $upc => $section) {
            $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
            if ($section > 0) $item[$upc] = $section;
        }

        foreach ($item as $upc => $section) {
            $floorSectionRange = array();
            if ($storeID == 1) {
                $floorSectionRange[] = 1;
                $floorSectionRange[] = 19;
            } elseif ($storeID == 2) {
                $floorSectionRange[] = 30;
                $floorSectionRange[] = 47;
            }
            $args = array($upc,$floorSectionRange[0],$floorSectionRange[1]);
            $prepZ = ("DELETE FROM FloorSectionProductMap WHERE upc = ? AND floorSectionID BETWEEN ? AND ?");
            $dbc->execute($prepZ,$args);

            $args = array($upc,$section);
            $prep = $dbc->prepare('
                INSERT INTO FloorSectionProductMap (upc, floorSectionID) values (?, ?);
            ');
            $dbc->execute($prep, $args);
        }
        $ret .= '<div class="alert alert-success">Update Successful</div>';

        $ret .= '<br><br><a class="btn btn-default btn-back" href="javascript:history.back()">Back</a><br><br>';
        $ret .= '<a class="btn btn-default" href="ProdLocationEditor.php">Return</a><br><br>';

        return $ret;
    }

    private function getCurrentBatches($dbc)
    {
        $res = $dbc->query('SELECT batchID FROM batches WHERE ' . $dbc->curdate() . ' BETWEEN startDate AND endDate');
        $ret = array();
        while ($row = $dbc->fetchRow($res)) {
            $ret[] = $row['batchID'];
        }

        return $ret;
    }

    function get_start_view()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $start = FormLib::get('start');
        $end = FormLib::get('end');
        $storeID = FormLib::get('storeID');
        $args = array($start, $end, $storeID);
        $where = 'b.batchID BETWEEN ? AND ?';
        if ($start == 'CURRENT' && $end == 'CURRENT') {
            $batches = $this->getCurrentBatches($dbc);
            list($inStr, $args) = $dbc->safeInClause($batches);
            $args[] = $storeID;
            $where = "b.batchID IN ({$inStr})";
        }

            $query = $dbc->prepare('
                select
                    p.upc,
                    p.description as pdesc,
                    p.department,
                    pu.description as pudesc,
                    p.brand,
                    d.dept_name
                from products as p
                    left join batchList as bl on bl.upc=p.upc
                    left join batches as b on b.batchID=bl.batchID
                    left join productUser as pu on pu.upc=p.upc
                    left join departments as d on d.dept_no=p.department
                where ' . $where . '
                    and p.store_id= ?
                    AND department < 700
                    AND department != 208
                    AND department != 235
                    AND department != 240
                    AND department != 500
                order by p.department;
            ');
            $result = $dbc->execute($query, $args);
            $item = array();
            $fsChk = $dbc->prepare('SELECT m.floorSectionID
                FROM FloorSectionProductMap AS m
                    INNER JOIN FloorSections AS f ON m.floorSectionID=f.floorSectionID
                WHERE m.upc=?
                    AND f.storeID=?
                ORDER BY m.floorSectionID DESC');
            while($row = $dbc->fetchRow($result)) {
                $curFS = $dbc->getValue($fsChk, array($row['upc'], $storeID));
                if ($curFS) continue;
                $item[$row['upc']]['upc'] = $row['upc'];
                $item[$row['upc']]['dept'] = $row['department'];
                $item[$row['upc']]['desc'] = $row['pdesc'];
                $item[$row['upc']]['brand'] = $row['brand'];
                $item[$row['upc']]['dept_name'] = $row['dept_name'];
            }

            foreach ($item as $upc => $row) {
                $item[$upc]['sugDept'] = $this->getLocation($item[$upc]['dept'],$dbc);
            }

            $args = array($storeID);
            $query = $dbc->prepare('SELECT
                    floorSectionID,
                    name
                FROM FloorSections
                WHERE storeID = ?
                ORDER BY name;');
            $result = $dbc->execute($query,$args);
            $floor_section = array();
            while($row = $dbc->fetch_row($result)) {
                $floor_section[$row['floorSectionID']] = $row['name'];
            }

            $ret = "";
            $ret .= '<table class="table">
                <thead>
                    <th>UPC</th>
                    <th>Brand</th>
                    <th>Description</th>
                    <th>Dept. No.</th>
                    <th>Department</th>
                    <th>Location</th>
                </thead>
                <form method="post">
                    <input type="hidden" name="save" value="1">
                    <input type="hidden" name="batch" value="1">
                    <input type="hidden" name="start" value="' . $start . '">
                    <input type="hidden" name="end" value="' . $end . '">
                    <input type="hidden" name="storeID" value="' . $storeID . '">
                ';
            foreach ($item as $key => $row) {
                $ret .= '
                    <tr><td><a href="ItemEditorPage.php?searchupc='.$upc.'&ntype=UPC&superFilter=" target="_blank">' . $key . '</a></td>
                    <td>' . $row['brand'] . '</td>
                    <td>' . $row['desc'] . '</td>
                    <td>' . $row['dept'] . '</td>
                    <td>' . $row['dept_name'] . '</td>
                    <td><Span class="collapse"> </span>
                        <select class="form-control input-sm" name="' . $key . '" value="" />
                            <option value="0">* no location selected *</option>';

                    foreach ($floor_section as $fs_key => $fs_value) {
                        if ($fs_key == $item[$key]['sugDept']) {
                            $ret .= '<option value="' . $fs_key . '" name="' . $key . '" selected>' . $fs_value . '</option>';
                        } else {
                            $ret .= '<option value="' . $fs_key . '" name="' . $key . '">' . $fs_value . '</option>';
                        }
                    }

                    $ret .= '</tr>';
            }

        $ret .= '<tr><td><input type="submit" class="btn btn-default" value="Update Locations"></td>
            <td><br><br></td></table>
            </form>';

        if (FormLib::get('batchCheck', false)) {
            $ret .= '<br><a class="btn btn-default" href="../../../scancoord/ScannieV2/content/Scanning/BatchCheck/SCS.php">
                Back to Batch Check</a><br><br>';
        } else {
            $ret .= '<br><a class="btn btn-default btn-back" href="ProdLocationEditor.php">Back</a><br><br>';
        }


        return $ret;
    }

    function get_list_view()
    {

        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $storeID = FormLib::get('storeID', false);
        $storePicker = FormLib::storePicker('storeID');
        $upcs = FormLib::get('upcs', '');
        $selStoreOpts = '';

        $suggestions = $this->getSuggestions();

        $ret = "";
        $ret .= '
        <div class="row">
            <form method="get" action="ProdLocationEditor.php">
                <div class="col-lg-2" style="background: rgba(0,0,0,0.1); padding: 5px"">
                    <textarea class="form-control" name="upcs" rows=6>'.$upcs.'</textarea>
                </div>
                <div class="col-lg-2" style="background: rgba(0,0,0,0.1); padding: 5px">
                    <div>
                    <input type="hidden" name="list" value="1">
                    <div class="form-group"> '.$storePicker['html'].' </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-default form-control">Submit List</button>
                    </div>
            </div>
                <div class="col-lg-8"></div>
            </div>
            </form>
        </div>
        <div class="row">
            <div class="col-lg-2"></div>
            <div class="col-lg-8"></div>
            <div class="col-lg-2">
                <div class="form-group">
                    <span href="#" class="btn btn-primary form-control" id="alt-update-form-btn">Update Locations</span>
                </div>
            </div>
        </div>
        ';

        $plus = array();
        if ($upcs = FormLib::get('upcs')) {
            $chunks = explode("\r\n", $upcs);
            foreach ($chunks as $key => $str) {
                $plus[] = str_pad($str, 13, '0', STR_PAD_LEFT);
            }
        }

        list($inClause,$args) = $dbc->safeInClause($plus);
        $args[] = $storeID;
        $qString = 'select
                p.upc,
                p.description as pdesc,
                p.department,
                pu.description as pudesc,
                p.brand,
                d.dept_name,
                fslv.sections
            from products as p
                left join FloorSectionProductMap as pp on pp.upc=p.upc
                left join FloorSectionsListView AS fslv ON fslv.upc=p.upc
                left join productUser as pu on pu.upc=p.upc
                left join departments as d on d.dept_no=p.department
            WHERE p.upc IN ('.$inClause.')
                AND fslv.storeID = ?
            order by p.department;';

        $query = $dbc->prepare($qString);
        $result = $dbc->execute($query, $args);

        //  Catch products that don't yet have prod maps.
        $upcsWithLoc = array();
        while ($row = $dbc->fetch_row($result)) {
            $upcsWithLoc[] = $row['upc'];
        }

        $upcsMissingLoc = array();
        foreach ($plus as $upc) {
            if (!in_array($upc,$upcsWithLoc)) {
                $upcsMissingLoc[] = $upc;
            }
        }
        list($inClauseB,$bArgs) = $dbc->safeInClause($upcsMissingLoc);
        $bArgs[] = $storeID;
        $bString = '
         select
            p.upc,
            p.description as pdesc,
            p.department,
            pu.description as pudesc,
            p.brand,
            d.dept_name
            from products as p
                left join productUser as pu on pu.upc=p.upc
                left join departments as d on d.dept_no=p.department
                left join FloorSectionsListView AS fslv ON fslv.upc=p.upc
            WHERE p.upc IN ('.$inClauseB.')
                AND p.store_id = ?
            order by p.department;
        ';
        $bQuery = $dbc->prepare($bString);
        $bResult = $dbc->execute($bQuery,$bArgs);

        $item = array();
        $result = $dbc->execute($query,$args);

        $results = array($result,$bResult);
        $sections = array();
        foreach ($results as $res) {
            while($row = $dbc->fetch_row($res)) {
                $item[$row['upc']]['upc'] = $row['upc'];
                $item[$row['upc']]['dept'] = $row['department'];
                $item[$row['upc']]['desc'] = $row['pdesc'];
                $item[$row['upc']]['brand'] = $row['brand'];
                $item[$row['upc']]['dept_name'] = $row['dept_name'];
                $section = isset($row['sections']) ? $row['sections'] : '';
                $item[$row['upc']]['curSections'] = $section;
                $v = isset($row['curSections']) ? $row['curSections'] : '';
                if (!in_array($section, $sections)) {
                    $sections[] = $section;
                }
            }
            if ($dbc->error()) {
                echo '<div class="alert alert-danger">' . $dbc->error() . '</div>';
            }
        }

        $sectionContent = "<div style=\"padding:10px;\"></div><strong>Show Locations: </strong>";
        foreach ($sections as $section) {
            if ($section != null) {
                $sectionContent .= "<span style=\"padding: 10px;\">
                    <input type=\"checkbox\" class=\"checkboxx\" name=\"$section\"
                    id=\"$section\" checked>
                    <label for=\"$section\" style=\"font-weight: normal\">$section</label>
                    </span>";
            }
        }

        foreach ($item as $upc => $row) {
            $item[$upc]['sugDept'] = $suggestions[$row['dept']];
        }

        $args = array($storeID);
        $query = $dbc->prepare('SELECT
                floorSectionID,
                name
            FROM FloorSections
            WHERE storeID = ?
            ORDER BY name;');
        $result = $dbc->execute($query,$args);
        $floor_section = array();
        while($row = $dbc->fetch_row($result)) {
            $floor_section[$row['floorSectionID']] = $row['name'];
        }
        if ($er = $dbc->error()) {
            echo '<div class="alert alert-danger">'.$er.'</div>';
        }

        $ret .= $sectionContent;
        $ret .= '<table class="table mySortableTable tablesorter tablesorter-bootstrap">
            <thead>
                <th>UPC</th>
                <th>Brand</th>
                <th>Description</th>
                <th>Dept. No.</th>
                <th>Department</th>
                <th>Current Location(s)</th>
                <th>
                    Location
                    <div class="input-group">
                        <span class="input-group-addon">Change All</span>
                            <select class="form-control input-sm" onchange="updateAll(this.value, \'.locationSelect\');">
                                <option value="">Select one...</option>

                        ';
        foreach ($floor_section as $fs_key => $fs_value) {
            $ret .= '<option value="' . $fs_key . '">' . $fs_value . '</option>';
        }
        $ret .= '
                    </select></div>
                </th>
            </thead>
            <form method="post" name="update" id="update">
                <input type="hidden" name="save" value="1">
            ';
        foreach ($item as $key => $row) {
            $ret .= '
                <tr data-selected=""><td><a href="ItemEditorPage.php?searchupc='.$key.'&ntype=UPC&superFilter=" target="_black">' . $key . '</a></td>
                <td>' . $row['brand'] . '</td>
                <td>' . $row['desc'] . '</td>
                <td>' . $row['dept'] . '</td>
                <td>' . $row['dept_name'] . '</td>
                <td class="locations">' . $row['curSections'] . '</td>
                <td><Span class="collapse"> </span>
                    <select class="locationSelect form-control input-sm" name="' . $key . '" value="" />
                        <option value="0">* no location selected *</option>';

                foreach ($floor_section as $fs_key => $fs_value) {
                    if ($fs_key == $item[$key]['sugDept']) {
                        $ret .= '<option value="' . $fs_key . '" name="' . $key . '" selected>' . $fs_value . '</option>';
                    } else {
                        $ret .= '<option value="' . $fs_key . '" name="' . $key . '">' . $fs_value . '</option>';
                    }
                }

                $ret .= '</select></tr>';
        }

        $ret .= '<tr><td><input type="submit" class="class=btn btn-primary form-control" value="Update Locations"></td>
            <td><a class="btn btn-default btn-back" href="ProdLocationEditor.php">Back</a><br><br></td></table>
            </form>';
        // tablesorter scripts not included?
        //$this->addOnloadCommand("$('.mySortableTable').tablesorter();");

        return $ret;

    }

    function get_batch_view()
    {
        $stores = FormLib::storePicker('storeID', 1);
        $ret = '
            <form method="get"class="form-inline">

            <div class="input-group" style="width:200px;">
                <span class="input-group-addon">Batch#</span>
                <input type="text" class="form-control inline" name="start" autofocus required>
            </div>
            <div class="input-group" style="width:200px;">
                <span class="input-group-addon">to Batch#</span>
                <input type="text" class="form-control inline" name="end" required><br>
            </div><br><br>
            <div class="input-group" style="width:200px;">
                <span class="input-group-addon">Store</span>
                ' . $stores['html'] . '
            </div><br /><br />

                <input type="submit" class="btn btn-default" value="Find item locations">

                <button type="submit" class="btn btn-default"
                    onclick="$(\'input.inline\').val(\'CURRENT\');">Current Batches</button>

            </form><br>
            <a class="btn btn-default btn-back" href="ProdLocationEditor.php">Back</a><br><br>
        ';

        return $ret;
    }

    function get_view()
    {
        return <<<HTML
<div class="row">
    <div class="col-lg-4">
    </div>
    <div class="col-lg-4">
        <form method="get">
            <div class="form-group">
                <button type="submit" class="btn btn-default" style="width: 300px" name="list">Edit a list of <strong>upcs</strong></button>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-default" style="width: 300px" name="batch">Edit Locations by <strong>batch-ids</strong></button>
            </div>
            <div class="form-group">
                <a class="btn btn-default" style="width: 300px" href="FloorSections/EditLocations.php">Edit Floor <strong>sub-locations</strong></a>
            </div>
            <div class="form-group">
                <button  class="btn btn-default" style="width: 300px" name="remove"><strong>Remove</strong> Floor Sections</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
    </div>
</div>
HTML;
    }

    private function arrayToOpts($arr, $selected=-999, $id_label=false)
    {
        $opts = '';
        foreach ($arr as $num => $name) {
            if ($id_label === true) {
                $name = $num . ' ' . $name;
            }
            $opts .= sprintf('<option %s value="%d">%s</option>',
                                ($num == $selected ? 'selected' : ''),
                                $num, $name);
        }

        return $opts;
    }

   public function javascript_content()
   {
       return <<<JAVASCRIPT
$('a').not('.btn-back').attr('target','_blank');
$(document).ready(function(){
    $('tr').each(function(){
        let upc = $(this).find('td:eq(0)').text();
        if (upc > 1) {
            let selected = $(this).find('.locationSelect option:selected').val();;
            $(this).attr('data-selected', selected);
            let datavalue = $(this).attr('data-selected');
        }
    });
});
$('.delete_location').click(function(){
    var upc = $(this).attr('data-upc');
    var plocation = $(this).attr('data-location');
    var parent_elm = $(this).closest('div.location-container');
    $.ajax({
        type: 'post',
        data: 'upc='+upc+'&location='+plocation+'&delete_location=1',
        success: function(response) {
            parent_elm.hide();
        }
    });
});
function toggleAll(elem, selector) {
    if (elem.checked) {
        $(selector).prop('checked', true);
    } else {
        $(selector).prop('checked', false);
    }
}
function updateAll(val, selector) {
    $(selector).val(val);
}
$('.checkboxx').on('change', function(){
    var boxval = $(this).attr('name');
    if ($(this).prop('checked')) {
        //became checked
        $('td').each(function(){
            var value = $(this).text();
            if ($(this).hasClass('locations')) {
                if (value == boxval) {
                    let tr = $(this).closest('tr');
                    let selected = tr.attr('data-selected');
                    tr.find('.locationSelect').val(selected);
                    tr.show();

                }
            }
        });
    } else {
        //became un-checked
        $('td').each(function(){
            var value = $(this).text();
            if ($(this).hasClass('locations')) {
                if (value == boxval) {
                    let tr = $(this).closest('tr');
                    let selected = tr.find('.locationSelect option:selected').val();;
                    tr.attr('data-selected', selected);
                    tr.find('.locationSelect').val(0);
                    tr.hide();
                    //next, i need to change the selected to = 0
                }
            }
        });
    }
});

$("#alt-update-form-btn").click(function(){
    document.forms['update'].submit();
});
JAVASCRIPT;
   }

    public function helpContent()
    {
        return '<p>
            Edit the physical sales-floor location
            of products found in batches that fall within a
            specified range of batch IDs.
            <lu>
                <li><b>Update by UPC</b> View and update location(s) for individual items.</li>
                <li><b>Update by a List of UPCs</b> Paste a list of UPCs to view/update. Updating by
                    List will DELETE all current locations and replace them with the selected
                    location.</li>
                <li><b>Update by BATCH I.D.</b> Update products within a batch range. Update by Batch I.D.
                    will only check products that do not currently have a location assigned.</li>
            </lu>
            </p>
            ';
    }

    public function getSuggestions()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $storeID = FormLib::get('storeID', 1);
        $depts = array();

        $args = array($storeID);
        $prep = $dbc->prepare("SELECT 
            f.floorSectionID, 
            count(f.floorSectionID) AS coung,
            dept_no
            FROM FloorSectionProductMap AS f
                LEFT JOIN products AS p ON f.upc=p.upc
                LEFT JOIN departments AS d ON p.department=d.dept_no
            RIGHT JOIN FloorSections AS s ON f.floorSectionID=s.floorSectionID
                WHERE s.storeID = ?
            GROUP BY dept_no, f.floorSectionID
            ORDER BY dept_no, count(f.floorSectionID) ASC;
            ");
        $res = $dbc->execute($prep, $args);;
        while ($row = $dbc->fetchRow($res)) {
            $dept = $row['dept_no'];
            $fsID = $row['floorSectionID'];
            $depts[$dept] = $fsID;    
        }

        return $depts;
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertInternalType('string', $this->get_view());
        $phpunit->assertInternalType('string', $this->get_batch_view());
        $phpunit->assertInternalType('string', $this->get_list_view());
        $phpunit->assertInternalType('string', $this->get_start_view());
    }
}

FannieDispatch::conditionalExec();

