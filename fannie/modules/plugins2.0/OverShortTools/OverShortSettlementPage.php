<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

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

include(dirname(__FILE__).'/../../../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../../../classlib2.0/FannieAPI.php');
}

class OverShortSettlementPage extends FanniePage 
{
    // 10Nov13 EL Added title and header
    protected $title = 'Over/Short Settlement';
    protected $header = 'Over/Short Settlement';
    protected $auth_classes = array('overshorts');
    public $page_set = 'Plugin :: Over/Shorts';
    public $description = '[Settlements] Cover Sheet for accounting that shows total overshort for the day';
    public $themed = true;

    function preprocess()
    {
        $action = FormLib::get_form_value('action',False);
        if ($action !== False){
            $this->ajaxRequest($action);
            return False;
        }
        return True;
    }

    function ajaxRequest($action){
        global $FANNIE_OP_DB, $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        switch($action){
        case 'save':
            $date = FormLib::get_form_value('curDate');
            $data = FormLib::get_form_value('data');
            $user = FormLib::get_form_value('user');
            $resolved = FormLib::get_form_value('resolved');
            $notes = FormLib::get_form_value('notes');
            $store = FormLib::get('store');
    
            $model = new OverShortsLogModel($dbc);
            $model->date($date);
            $model->username($user);
            $model->resolved($resolved);
            $model->storeID($store);
            $model->save();
            
            $this->save($date,$store,$data);
            $this->saveNotes($date,$store,$notes);
            echo "saved";
            break;

        case 'date':
            $date = FormLib::get_form_value('arg');
            if (empty($date)) {
                $date = date('Y-m-d');
            }
            $store = FormLib::get('store', 1);
            $storeName = FormLib::storePicker()['names'][$store];
            $dlog = DTransactionsModel::selectDTrans($date);
            $args = array($date . ' 00:00:00', $date . ' 23:59:59', $store);

            /**
              Mode toggles how totals are calculated. Cashier mode lists totals for
              each cashier. Drawer mode lists a total for each register. Some of the code that
              follows (and underlying database) uses "emp_no" when it really means "whichever 
              identifier we're grouping by".
            */
            
            $output = "<h3 id=currentdate>$date</h3>";
            $output .= '<input type="hidden" id="currentstore" value="' . $store . '" />';

            $output .= "<form onsubmit=\"save(); return false;\">";
            $output .= "<table class=\"table\"><tr>";
            $output .= "<th>$storeName Daily Settlement</th><th>&nbsp;</th><th>(Account Number)</th><th>(POS)</th><th>(count)</th><th>(Totals)</th></tr>";
            //get the layout from the accounts table.
            $acctQ = $dbc->prepare("SELECT * FROM dailySettlementAccounts WHERE storeID = ?");
            $acctR = $dbc->execute($acctQ,array($store));
            $internalData = array('Unused');
            while ($row = $dbc->fetch_row($acctR)) {
                $output .= '<tr>';
                if (substr($row[0], 0,5) == '*HEAD') {
                    switch ($row[3]) {
                        case '|': //sql query read down.
                            $sql = sprintf($row[5],$dlog);
                            $internalData = array($sql);
                            $sqlQ = $dbc->prepare($sql);
                            $sqlR = $dbc->execute($sqlQ,$args);
                            $i = 0;
                            $sum = 0;
                            while($sqlW = $dbc->fetch_row($sqlR)) {
                                $internalData[$i] = $sqlW[0];
                                $sum = $sum + $sqlW[0];
                                $i++;
                            }
                            $internalData[$i] = array_sum($internalData);
                            break;
                        case '_': //sql query read accross.
                            $sql = sprintf($row[5],$dlog);
                            $internalData = array($sql);
                            $sqlQ = $dbc->prepare($sql);
                            $sqlR = $dbc->execute($sqlQ,$args);
                            $sum = 0;
                            $sqlW = $dbc->fetch_row($sqlR);
                            for($i=0; $i<sizeof($sqlW); $i++) {
                                $internalData[$i] = $sqlW[$i];
                                $sum = $sum + $sqlW[$i];
                            }
                            $internalData[$i] = array_sum($internalData);
                            break;
                        case '@': //php function call
                            $function = $row[5];
                            $internalData = array($function);
                            $internalData = $this->$function($dbc,$dlog,$args);
                            break;
                        default:
                            # code...
                            break;
                    }

                } else {
                    for ($key=0;$key<sizeof($row)/2;$key++){
                        switch ($key) {
                            case '0':
                                $output .= '<td>'.$row[$key].'</td><td>&nbsp;</td>';
                                break;
                            case '1':
                                if($row[$key] == ''){
                                    $output .= '<td bgcolor="#A9A9A9"></td>';
                                } else {
                                    $output .= '<td>'.$row[$key].'</td>';
                                }   
                                break;
                            case '2':
                                $value = $internalData[(int)$row[5]];
                                if ($row[3] != '=' && $value != '') {
                                    $output .= '<td>'.$value.'</td>';
                                }  else {
                                    $output .= '<td bgcolor="#A9A9A9"></td>';
                                }
                                break;
                            case '3':
                                if ($row[3] != '=') {
                                    $output .= '<td>enter here</td>';
                                } else {
                                    $output .= '<td bgcolor="#A9A9A9"></td>';
                                }
                                
                                break;
                            case '4':
                                $value = $internalData[(int)$row[5]];
                                if ($row[3] == '='){
                                    $output .= '<td>'.$value.'</td>';
                                    $internalData = array('empty');
                                } else {
                                    $output .= '<td bgcolor="#A9A9A9"></td>';
                                }
                                break;
                            default:
                                # code...
                                break;
                        }
                    }
                }
                //$output .= '<td>'.$row[0].'</td><td>&nbsp;</td>';
                //$output .= '<td>('.$row[1].')</td>';
                $output .= '</tr>';
                $rowCount++;
            }

            $output .= "</table>";
        
            $model = new OverShortsLogModel($dbc);
            $model->date($date);
            $model->load();
            $output .= "<p>This date last edited by: <span id=lastEditedBy><b>".$model->username()."</b></span><br />";
            $output .= "<button type=submit class=\"btn btn-default\">Save</button>";
            $output .= " <label><input type=checkbox id=resolved ";
            if ($model->resolved() == 1)
                $output .= "checked";
            $output .= " /> Resolved</label></p>";
            $output .= "</form>";

            /* "send" output back */
            echo $output;
            break;
        }
    }

    function save($date,$store,$data){
        global $FANNIE_OP_DB, $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $bycashier = explode(',',$data);

        $model = new DailyCountsModel($dbc);
        $model->date($date);
        $model->storeID($store);
        foreach ($bycashier as $c){
            $temp = explode(':',$c);
            if (count($temp) != 2) continue;
            $cashier = $temp[0];
            $tenders = explode(';',$temp[1]);
            $model->emp_no($cashier);
            foreach($tenders as $t){
                $temp = explode('|',$t);
                $tender_type = $temp[0];
                $amt = isset($temp[1]) ? rtrim($temp[1]) : '';
                if ($amt != ''){
                    $model->tender_type($tender_type);
                    $model->amt($amt);
                    $model->save();
                }
            }
        }
    }

    function saveNotes($date,$store,$notes){
        global $FANNIE_OP_DB, $FANNIE_PLUGIN_SETTINGS;
        $dbc = FannieDB::get($FANNIE_PLUGIN_SETTINGS['OverShortDatabase']);
        $noteIDs = explode('`',$notes);
        $model = new DailyNotesModel($dbc);
        $model->date($date);
        $model->storeID($store);
        foreach ($noteIDs as $n){
            $temp = explode('|',$n);
            $emp = $temp[0];
            $note = str_replace("'","''",urldecode($temp[1]));
            $model->emp_no($emp);
            $model->note($note);
            $model->save();
        }
    }

    private function calculateDiscounts($dbc,$dlog,$args=array()){
        $discQ =$dbc->prepare(" 
            SELECT 
            sum(case 
                    when upc='DISCOUNT'  and percentDiscount >=10 and memType =3 then -unitPrice* (10/percentDiscount)
                    when upc='DISCOUNT'  and percentDiscount >= 15 and memType =5 then -unitPrice* (15/percentDiscount)
                    when upc='DISCOUNT'  and percentDiscount >= 23 and memType =9 then -unitPrice* (8/percentDiscount)
                    when upc='DISCOUNT'  and percentDiscount = 21 and memType =9 then -unitPrice* (6/percentDiscount)
                else 0 end) as working_disc,
            sum(case
                    when upc='DISCOUNT' and percentDiscount != 0 and memType in (7,8,9,10) then -unitPrice*(15/percentDiscount)
                else 0 end) as staff_disc,
            sum(case
                    when upc='DISCOUNT' and percentDiscount != 0 and memType =6 then -unitPrice* (10/percentDiscount)
                    when upc='DISCOUNT' and percentDiscount != 0 and memType =10 then -unitPrice* (8/percentDiscount )
                else 0 end) as food_for_all_disc,
            sum(case
                    when upc='DISCOUNT' and percentDiscount >0 and memType in (0,1) then -unitPrice
                    when upc='DISCOUNT' and (percentDiscount-10)/percentDiscount >0 and memType in (3,6) then -unitPrice*((percentDiscount-10)/percentDiscount)
                    when upc='DISCOUNT' and (percentDiscount-15)/percentDiscount >0 and memType in (5,7,8) then -unitPrice*((percentDiscount-15)/percentDiscount)
                    when upc='DISCOUNT' and (percentDiscount-23)/percentDiscount >0 and memType in (9,10) then -unitPrice*((percentDiscount-23)/percentDiscount)
                    when upc='DISCOUNT' and percentDiscount = 0 then -unitPrice
                else 0 end) as seinorDisc,
            sum(case when upc='DISCOUNT' then -unitPrice else 0 end) as total_disc
            FROM ".$dlog."
            WHERE `datetime` BETWEEN ? AND ? AND store_id=?;");
        $discR = $dbc->execute($discQ, $args);
        
        $return = array();
        $discSum = 0;
        $row = $dbc->fetch_row($discR);
        
        //correct rounding errors
        for($key=0;$key<=5;$key++) {
            $info = number_format($row[$key], 2, '.', '');
            if ($key < 4) {
                $discSum += $info;
                $return[$key] = $info;
            } elseif ($key==4) {
                $diff = $info-$discSum;
                $return[$key] = $info;
                if ($diff != 0) { 
                    $return[2] += $diff;
                    $discSum += $diff;
                }
            }
        }
        //final error check returns values for troubleshooting.
        if ($discSum - $return[4] !=0) {
            $return = array('Math ERR',$discSum,$return[4],'Math ERR','Math ERR');
        }

        return $return;
    }

    function javascript_content(){
        ob_start();
    ?>

function setdate()
{
    var dataStr = $('#osForm').serialize();
    dataStr += '&action=date';
    $('#date').val('');
    $('#forms').html('');
    $('#loading-bar').show();
    $.ajax({
        url: 'OverShortSettlementPage.php',
        data: dataStr,
        success: function(data){
            $('#loading-bar').hide();
            $('#forms').html(data);
        }
    });
}

function calcOS(type,empID){
    var dlogAmt = $('#dlog'+type+empID).html();
    var countAmt = $('#count'+type+empID).val();
    
    if (countAmt.indexOf('+') != -1){
        var temp = countAmt.split('+');
        var countAmt = 0;
        for (var i = 0; i < temp.length; i++){
            countAmt += Number(temp[i]);
        }
        $('#count'+type+empID).val(Math.round(countAmt*100)/100);
    }
    
    var extraAmt = 0;
    if (type == 'CA'){
        extraAmt = $('#startingCash'+empID).val();

        if (extraAmt.indexOf('+') != -1){
            var temp = extraAmt.split('+');
            var extraAmt = 0;
            for (var i = 0; i < temp.length; i++){
                extraAmt += Number(temp[i]);
            }
            $('#startingCash'+empID).val(Math.round(extraAmt*100)/100);
        }
    }
    
    var diff = Math.round((countAmt - dlogAmt - extraAmt)*100)/100;
    
    $('#os'+type+empID).html(diff);
    $('#os'+type+empID+'Hidden').val(diff);
    
    resum(type);
    cashierResum(empID);
}

function resum(type){
    var countSum = 0;
    $('.countT'+type).each(function(){
        countSum += Number($(this).val());
    });

    if (type == 'CA'){
        $('.startingCash').each(function(){
            countSum -= Number($(this).val());
        });
    }
    
    var osSum = 0;
    $('.osT'+type).each(function(){
        osSum += Number($(this).val());
    });
        
    var oldcount = Number($('#count'+type+'Total').html());
    var oldOS = Number($('#os'+type+'Total').html());
    var newcount = Math.round(countSum*100)/100;
    var newOS = Math.round(osSum*100)/100;

    $('#count'+type+'Total').html(newcount);
    $('#os'+type+'Total').html(newOS);

    var overallCount = Number($('#overallCountTotal').html());
    var overallOS = Number($('#overallOSTotal').html());

    var newOverallCount = overallCount + (newcount - oldcount);
    var newOverallOS = overallOS + (newOS - oldOS);

    $('#overallCountTotal').html(Math.round(newOverallCount*100)/100);
    $('#overallOSTotal').html(Math.round(newOverallOS*100)/100);
}

function cashierResum(empID){
    var countSum = 0;
    countSum -= Number($('#startingCash'+empID).val());
    $('.countEmp'+empID).each(function(){
        countSum += Number($(this).val());
    });
    var osSum = 0;
    $('.osEmp'+empID).each(function(){
        osSum += Number($(this).val());
    });
    $('#countTotal'+empID).html(Math.round(countSum*100)/100);
    $('#osTotal'+empID).html(Math.round(osSum*100)/100);
}

function save(){
    var outstr = '';
    var notes = '';
    var emp_nos = document.getElementsByName('cashier');
    $('.cashier').each(function(){
        var emp_no = $(this).val();
        outstr += emp_no+":";
        if ($('#startingCash'+emp_no).length != 0)
            outstr += "SCA|"+$('#startingCash'+emp_no).val()+";";

        $('.tcode'+emp_no).each(function(){
            var code = $(this).val();
            if ($('#count'+code+emp_no).length != 0)
                outstr += code+"|"+$('#count'+code+emp_no).val()+";";
        });
        
        var note = $('#note'+emp_no).val();
        
        notes += emp_no + "|" + escape(note);
        outstr += ",";
        notes += "`";
    });
    var note = $('#totalsnote').val();
    notes += "-1|"+escape(note);
    
    var curDate = $('#currentdate').html();
    var store = $('#currentstore').val();
    var user = $('#user').val();
    var resolved = 0;
    if (document.getElementById('resolved').checked)
        resolved = 1;

    $('#lastEditedBy').html("<b>"+user+"</b>");

    $.ajax({
        url: 'OverShortSettlementPage.php',
        type: 'post',
        data: 'action=save&curDate='+curDate+'&data='+outstr+'&user='+user+'&resolved='+resolved+'&notes='+notes+'&store='+store,
        success: function(data){
            if (data == "saved")
                alert('Data saved successfully');
            else
                alert(data);
        }
    }); 
}

    <?php
        return ob_get_clean();
    }


    function css_content(){
        ob_start();
        ?>
#forms {

}

body, table, td, th {
  color: #000;
}
    <?php
        return ob_get_clean();
    }

    function body_content(){
        global $FANNIE_URL;
        $user = FannieAuth::checkLogin();
        ob_start();
        ?>
        <form style='margin-top:1.0em;' id="osForm" onsubmit="setdate(); return false;" >
        <div class="form-group form-inline">
        <label>Date</label>:<input class="form-control date-field" type=text id=date name=arg />
        <?php
        $sp = FormLib::storePicker('store', false);
        echo $sp['html'];
        ?>
        <button type=submit class="btn btn-default">Set</button>
        <input type=hidden id=user value="<?php if(isset($user)) echo $user ?>" />
        </div>
        </form>

        <div id="loading-bar" class="collapse">
            <?php echo \COREPOS\Fannie\API\lib\FannieUI::loadingBar(); ?>
        </div>
        <div id="forms"></div>
        <?php
        return ob_get_clean();
    }

}

FannieDispatch::conditionalExec(false);

