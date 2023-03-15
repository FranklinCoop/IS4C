<?php
/*******************************************************************************

    Copyright 2022 Franklin Community co-op

    This file is part of CORE-POS.

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

use COREPOS\Fannie\API\item\ItemModule;
use COREPOS\Fannie\API\item\ItemRow;

class ProduceSearchModule extends ItemModule implements ItemRow
{

    public function width()
    {
        return self::META_WIDTH_FULL;
    }

    private function getDepts($upc, $storeID=false)
    {
        $dbc = $this->db();
        $query = "
        SELECT p.store_id as store_id, d.superID as super_id, n.super_name as super_name, s.searchable as searchable
        FROM products p
        LEFT JOIN produceSearchList s on p.upc = s.upc AND p.store_id = s.store_id
        JOIN superdepts d ON p.department = d.dept_ID
        JOIN superDeptNames n ON d.superID = n.superID
            WHERE p.upc=?  aND p.store_id=?";// . ($storeID ? ' AND p.store_id=? ' : '') . " ";
            
        $args = array($upc);
        if ($storeID) {
            $args[] = $storeID;
        }
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep,$args);
        return $res;
    }

    public function showEditForm($upc, $display_mode=1, $expand_mode=1)
    {
        $upc = BarcodeLib::padUPC($upc);

        $ret = '';
        $ret = '<div id="prodSerachFieldset" class="panel panel-default">';
        $ret .=  "<div class=\"panel-heading\">
                <a href=\"\" onclick=\"\$('#produceSearchContents').toggle();return false;\">
                search_depts
                </a></div>";
        $css = ($expand_mode == 1) ? '' : ' collapse';
        $ret .= '<div id="produceSearchContents" class="panel-body' . $css . '">';
        // class="col-lg-1" works pretty well with META_WIDTH_HALF
        $ret .= '<div id="ItemSearch_deptsTable" class="col-sm-5">';

        $dbc = $this->db();
        $res = $this->getDepts($upc);

        $tableStyle = " style='border-spacing:5px; border-collapse: separate;'";
        $ret .= "<table{$tableStyle}>";
        $i=0;
        while($row = $dbc->fetchRow($res)){
            if ($i==0) $ret .= '<tr>';
            if ($i != 0 && $i % 2 == 0) $ret .= '</tr><tr>';
            $ret .= sprintf('<td><input type="checkbox" id="item-search-dept-%d" name="search_depts[]" value="%d" %s /></td>
                <td><label for="item-search-dept-%d">%s</label></td>',$i, $row['superID'],
                ($row['searchable']==0 ? '' : 'checked'),
                $i,
                $row['super_name']
            );
            //embed dept serach info to avoid re-querying it on save
            $ret .= sprintf('<input type="hidden" name="ds_attrs[]" value="%s" />
                            <input type="hidden" name="ds_bits[]" value="%d" />',
                            $row['super_name'], $row['superID']);
            $i++;
        }
        $ret .= '</tr></table>';

        $ret .= '</div>' . '<!-- /#ItemSearch_deptsTable -->';
        $ret .= '</div>' . '<!-- /#produceSearchContents -->';
        $ret .= '</div>' . '<!-- /#prodSerachFieldset -->';

        return $ret;
    }

    public function formRow($upc, $activeTab, $storeID)
    {
        if (FannieConfig::config('STORE_MODE') == 'HQ') {
            return $this->rowOfDepts($upc, $storeID);
        }

        return $activeTab ? $this->rowOfDepts($upc) : '';
    }

    private function rowOfDepts($upc, $storeID=false)
    {
        $upc = BarcodeLib::padUPC($upc);
        $dbc = $this->db();
        $res = $this->getDepts($upc, $storeID);
        $ret = '<tr class="small"><th class="text-right">Searchable</th><td colspan="9">';
        while ($row = $dbc->fetchRow($res)) {
            $ret .= sprintf('<label><input type="checkbox" name="search_depts%s[]" value="%d" %s />
                    %s</label>&nbsp;&nbsp;&nbsp;',
                    ($storeID ? $storeID : ''),
                    $row['store_id']+100, ($row['searchable'] ? 'checked' : ''), $row['super_name']);
            // embed dept serach info to avoid re-querying it on save
            $ret .= sprintf('<input type="hidden" name="ds_attrs%s[]" value="%s" />
                            <input type="hidden" name="ds_bits%s[]" value="%d" />',
                            ($storeID ? $storeID : ''), $row['super_name'],
                            ($storeID ? $storeID : ''), $row['super_id']);
        }
        $ret .= '</td></tr>';
        if ($storeID) {
            $ret .= '<input type="hidden" name="searchStores[]" value="' . $storeID . '" />';
        }

        return $ret;
    }

    public function saveFormData($upc)
    {
        $multi = FormLib::get('searchStores');
        if (is_array($multi)) {
            $ret = true;
            foreach ($multi as $store) {
                $ret = $this->realSave($upc, $store);
                
            }

            return $ret;
        }

        return $this->realSave($upc, '');
    }

    private function realSave($upc, $suffix)
    {

        try {
            $fName = 'search_depts' . $suffix;
            $searchDepts = $this->form->{$fName};
            $aName = 'ds_attrs' . $suffix;
            $attrs = $this->form->{$aName};
            $bName = 'ds_bits' . $suffix;
            $bits = $this->form->{$bName};
        } catch (Exception $ex) {
            $searchDepts = array();
            $attrs = array();
            $bits = array();
        }
        if (!is_array($searchDepts)) {
            return false;
        }
        $serach = FormLib::get($fName, array());
        $serachable = in_array($suffix, $searchDepts) ? 1 : 0;
        //echo "<p>INSIDE saveFormDatat <br> UPC: ".$upc." - Store: ".$suffix." - Search Value:".var_dump($serach)."<br></p>";

        $dbc = $this->connection;
        
        //$checked = ( $searchDepts[$suffix] === 0) ? 0 : 1 ;
        //$superID = $bits[$suffix];

        $model = new ProduceSearchListModel($dbc);
        $model->upc($upc);
        $model->store_id($suffix);
        //$model->searchable($checked);
        
        $model->searchable(in_array($suffix+100, $serach) ? 1 : 0);
        //$model->superID($superID);
        $saved = $model->save();

        return $saved;
    }

}

