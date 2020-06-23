<?php
/*******************************************************************************

    Copyright 2018 Franklin Community Co-op

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

require(dirname(__FILE__) . '/../../../../config.php');
if (!class_exists('FannieAPI')) {
    include(__DIR__ . '/../../../../classlib2.0/FannieAPI.php');
}

class FCCSettlementModule extends SettlementModule {
    protected $numCols = 6;
    protected $colNames = array('Market Daily Settlement','(Account Number)','(POS)','(count)','(Totals)','(Diff)');
    protected static $colPrint = array('id'=>'false',
                    'date' => false,
                    'storeID' => false,
                    'lineNo' => false,
                    'lineName'=> true,
                    'acctNo' => true,
                    'amt' => false,
                    'count' => true,
                    'total' => true,
                    'diff' => false,
                    'totalRow' =>false,
                    'diffShow' => false,
                    'diffWith' => false,
                    'reportOrder' => false);
    protected $numRows = 40;
    protected $rowData;
    protected $cellFormats = array();



    function __construct($dbc,$dlog,$date,$store) {
        $model = new DailySettlementModel($dbc);
        $model->date($date,'=');
        $model->storeID($store,'=');
        $today = date('Y-m-d');
        if (!$model->find() && $date < $today) {
            $model = $this->populateAccountTable($dbc,$dlog,$store,$date);
            $model->date($date,'=');
            $model->storeID($store,'=');
            
            $noteModel = new DailySettlementNotesModel($dbc);
            $noteModel->date($date);
            $noteModel->storeID($store);
            $noteModel->save();
        } else {

        }
        $this->rowData = $model;
        $this->cellFormats = static::rowFormat();
    }

    public function getTable($dbc,$dlog,$date,$store) {
        $model = new DailySettlementModel($dbc);
        $model->date($date,'=');
        $model->storeID($store,'=');
        return $model;
    }

    public function getColNames() {
        $hiddenPrint = array('(POS)','(Diff)');
        $return = '';
        foreach ($this->colNames as $key => $name) {
            if (in_array($name, $hiddenPrint))
                $return .= '<th class="hidden-print">' . $name . '</th>';
            else
               $return .= '<th>' . $name . '</th>'; 
        }
        return $return;
    }  


private function populateAccountTable($dbc,$dlog,$store,$date){
    $date1 = $date.' 00:00:00';
    $date2 = $date.' 23:59:59';
    $args = array($date1,$date2,$store);
    $lastIDQ = $dbc->prepare("SELECT `id` FROM dailySettlement ORDER BY `id` DESC LIMIT 1");
    $lastIDR = $dbc->execute($lastIDQ, array());
    $lastIDVal = $dbc->fetch_row($lastIDR)[0];
    $lastID = (is_null($lastIDVal)) ? 0 : $lastIDVal ;

    $model = new DailySettlementModel($dbc);

    $tableData = $this->genRowData($dbc,$dlog,$args);
    foreach ($tableData as $rowID => $rowArr) {
        $model->date($date);
        $model->lineNo($rowID);
        $model->lineName($rowArr[0]);
        $model->acctNo($rowArr[1]);
        $model->amt($rowArr[2]);
        $model->count($rowArr[3]);
        $model->total($rowArr[4]);
        $model->diff($rowArr[5]);
        $model->totalRow($lastID+$rowArr[6]);
        $model->diffShow($lastID+$rowArr[7]);
        $model->diffWith($lastID+$rowArr[8]);
        $model->reportOrder($rowID);
        $model->storeID($store);
        $model->save();
    }
    return $model;
}

    public function recalculatePosTotals($dbc,$dlog,$date,$store){
        $model = new DailySettlementModel($dbc);
        $model->date($date,'=');
        $model->storeID($store,'=');
        $today = date('Y-m-d');
        // create model if day has no entry or update values
        if (!$model->find() && $date < $today) {
            $model = $this->populateAccountTable($dbc,$dlog,$store,$date);
            $model->date($date,'=');
            $model->storeID($store,'=');
            
            $noteModel = new DailySettlementNotesModel($dbc);
            $noteModel->date($date);
            $noteModel->storeID($store);
            $noteModel->save();
        } else {
            $date1 = $date.' 00:00:00';
            $date2 = $date.' 23:59:59';
            $args = array($date1,$date2,$store);

            $tableData = $this->genRowData($dbc,$dlog,$args);
            $saveModel = new DailySettlementNotesModel($dbc);
            foreach ($model->find() as $obj) {
                $rowArr = $tableData[$obj->lineNo()];
                $saveModel->id($obj->id());
                //$model->total($rowArr[4]);
                $saveModel->amt($rowArr[2]);
                $saveModel->diff($obj->amt() - $rowArr[2]);
                //$model->totalRow($lastID+$rowArr[6]);
                //$model->diffShow($lastID+$rowArr[7]);
                //$model->diffWith($lastID+$rowArr[8]);
                //$model->reportOrder($rowID);
                //$model->storeID($store);
                $saveModel->save();
            }
            
        }

        $this->rowData = $model;

    }

    public static function updateTotalCell($dbc, $value, $cellID) {
        $json = array('msg'=>'','secID'=>0, 'secTotal' => 0, 'secDiff'=>0, 'diff'=>0,
                        'grandTotalID'=>0,'grandTotal'=>0,'grandDiff'=>0);
        $model = new DailySettlementModel($dbc);
        $model->id($cellID);
        $obj = $model->find();
        $amt = $obj[0]->amt();
        $count = $obj[0]->count();
        $diffWithID = $obj[0]->diffWith();
        $diffShowID = $obj[0]->diffShow();
        $secDiff = $value - $obj[0]->amt();


        $model->total($value);
        $model->save();

        $model = new DailySettlementModel($dbc);
        $model->id($diffWithID);
        $objs = $model->find();
        $obj = $objs[0];
        $overshort = $value - $obj->total();

        $model = new DailySettlementModel($dbc);
        $model->id($diffShowID);
        $model->total($overshort);

        $model->save();

        $json['secID'] = $cellID;
        $json['secTotal'] = round($value,2);
        $json['secDiff'] = round($secDiff,2);

        $json['grandTotalID'] = $diffShowID;
        $json['grandTotal'] = round($overshort,2);

        //update line item.
        $model = new DailySettlementModel($dbc);
        $model->id($cellID);
        $model->count($value);
        $model->diff($value - $amt);
        $saved = $model->save();

        return $json;
    }

//should return a json object.
    public static function updateCell($dbc, $value, $cellID) {
        $json = array('msg'=>'','secID'=>0, 'secTotal' => 0, 'secDiff'=>0, 'diff'=>0,
                        'grandTotalID'=>0,'grandTotal'=>0,'grandDiff'=>0);


        $model = new DailySettlementModel($dbc);
        $model->id($cellID);
        $obj = $model->find();
        $amt = $obj[0]->amt();
        $count = $obj[0]->count();
        $totalID = $obj[0]->totalRow();

        // section totals updated
        $model = new DailySettlementModel($dbc);
        $model->id($totalID,'=');
        $obj = $model->find();
        $total = $obj[0]->total();
        $posTotal = $obj[0]->amt();
        $newTotal = $total-$count+$value;
        $json['secID'] = $totalID;
        $json['secTotal'] = round($newTotal,2);
        $json['secDiff'] = round($newTotal - $posTotal,2);
        $grandTotalID = $obj[0]->totalRow();

        $model = new DailySettlementModel($dbc);
        $model->id($totalID);
        $model->total($newTotal);
        $model->diff($newTotal - $posTotal);
        $model->save();

        //uptade the footer totals.
        $model = new DailySettlementModel($dbc);
        $model->totalRow($grandTotalID,'=');
        $newGrandTotal = 0;
        foreach ($model->find() as $obj) { 
            switch ($obj->lineNo()) {
                case '0':
                case '3':
                case '10':
                    $newGrandTotal += $obj->total();
                    break;
                default:
                    $newGrandTotal -= $obj->total();
                    break;
            }
        }


        $model = new DailySettlementModel($dbc);
        $model->id($grandTotalID,'=');
        $objs = $model->find();
        $obj = $objs[0];
        $diffWithID = $obj->diffWith();
        $diffID = $obj->diffShow();

        $model = new DailySettlementModel($dbc);
        $model->id($diffWithID,'=');
        $objs = $model->find();
        $obj = $objs[0];
        $grandDiff = $obj->total() - $newGrandTotal;

        $model = new DailySettlementModel($dbc);
        $model->id($grandTotalID);
        $model->total($newGrandTotal);
        $model->diff($grandDiff);
        $model->save();

        $model = new DailySettlementModel($dbc);
        $model->id($diffID);
        $model->total($grandDiff);
        $model->save();


        

        $json['grandTotalID'] = $grandTotalID;
        $json['grandTotal'] = round($newGrandTotal,2);
        $json['diffID'] = $diffID;
        $json['grandDiff'] = round($grandDiff,2);

        //update line item.
        $model = new DailySettlementModel($dbc);
        $model->id($cellID);
        $model->count($value);
        $model->diff($value - $amt);
        $saved = $model->save();
        $json['diff'] = round($value-$amt,2);

        if (!$saved) {
            $json['msg'] = 'Error saving count';
        }
        return $json;
    }

private function genRowData($dbc,$dlog,$args) {
    $store = $args[2];
    $ret = array();
    $row = array();
    //Sales Totals
    $value = $this->getSalesTotals($dbc,$dlog,$args);
    $totalRow = 38;
    $row[] = 'DEPT SALES TOTALS';
    $row[] = '';
    $row[] = $value;
    $row[] = $value;
    $row[] = $value;
    $row[] = 0;
    $row[] = $totalRow;
    $row[] = 0;
    $row[] = 0;
    $row[] = 0;
    $ret[] = $row;
    //Tax Section
    $rowNames = array('PLUS SALES TAX Collected','PLUS MEALS TAX Collected','TOTAL TAX');
    $gfmAcctNo = array('(2400G990)','(2450A990)','');
    $mccAcctNo = array('(2400M990)','(2450A990)','');
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(1,2,3);
    $values = $this->getTaxTotals($dbc,$dlog,$args);
    $totalRow = 4;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }


    //R/A section
    $rowNames = array('PLUS GIFT CARD Sold','PLUS MEMBER EQUITY Payment','PLUS CHARGE Payment',
                      'PAYPAL TIPS','DELIVERY FEE','PLUS R/A OTHER (PAID-IN)','TOTAL R/A');
    $gfmAcctNo = array('(2500A990)','(2800A990)','aditional entry','(5255G500)','(5255G500)','aditional entry','');
    $mccAcctNo = array('(2500A990)','(2800A990)','aditional entry','(5255M500)','(5255M500)','aditional entry','');
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(4,5,6,7,8,9,10);
    $values = $this->getRATotals($dbc,$dlog,$args);
    $totalRow = 11;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    //Discount Section
    $rowNames = array('LESS Working Discount','LESS Staff Discount','LESS Senior Discount','LESS Food for All Discount','TOTAL DISCOUNTS');
    $gfmAcctNo = array('(4160G900)','(4150G900)','(4130G900)','(4110G900)','');
    $mccAcctNo = array('(4160M900)','(4150M900)','(4130M900)','(4110M900)','');
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(11,12,13,14,15);
    $values = $this->getDiscountTotals($dbc,$dlog,$args);
    $totalRow = 16;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    //Card Media Section
    $rowNames = array('LESS AMEX','LESS DEBIT','LESS SNAP / EBT: Cash','LESS SNAP / EBT: Food',
                      'VISA/MASTER/DISCOVER','LESS GIFT CARD Redeemed','TOTAL CARD MEDIA');
    $accountNumbers = array('(1025A990)','(1025A990)','(1025A990)','(1025A990)','(1025A990)','(2500A990)','');
    $reportOrder = array(16,17,18,19,20,21,22);
    $values = $this->getCardTotals($dbc,$dlog,$args);
    $totalRow = 23;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = -$values[$key];
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    // other tenders
    $rowNames = array('LESS Paper GIFT CERT','LESS Staff GIFT CERT','LESS Greenfield $ GIFT CERT','BUZZ REWARDS','r CREDITS','PAYPAL','LESS STORE CHARGE','LESS PAID OUT','TOTAL Other Credits');
    $gfmAcctNo = array('(2500A990)','(7800G990)','(1230A990)','(1065A990)','(1070A990)','(1075A990)','(1200A990)','additional entry','');
    $mccAcctNo = array('(2500A990)','(7800M990)','(1230A990)','(1065A990)','(1070A990)','(1075A990)','(1200A990)','additional entry','');$
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(23,24,25,26,27,28,29,30,31);
    $values = $this->getOtherTotals($dbc,$dlog,$args);
    $totalRow = 32;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = -$values[$key];
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    //coupon section
    $rowNames = array('LESS STORE COUPON','LESS CO-OP DEALS COUPONS','LESS OTHER VENDOR COUPONS','TOTAL COUPON');
    $gfmAcctNo = array('(4170G900)','(1210A990)','(1215A990)','');
    $mccAcctNo = array('(4170M900)','(1210A990)','(1215A990)','');
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(32,33,34,35);
    $values = $this->getCouponTotals($dbc,$dlog,$args);
    $totalRow = 36;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = -$values[$key];
        $row[] = ($key == sizeof($values) -1) ? 38 : $totalRow;
        $row[] = 0;
        $row[] = 0;
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    //Total & overshort section
    $rowNames = array('','TOTAL','BANK DEPOSIT','OVER / SHORT');
    $gfmAcctNo = array('','','','(419G900)');
    $mccAcctNo = array('','','','(419M900)');
    $accountNumbers = ($this->store == 1) ? $gfmAcctNo : $mccAcctNo ;
    $reportOrder = array(36,37,38,39);
    $total = $ret[0][2] + $ret[3][2] + $ret[10][2] - $ret[15][2] - $ret[22][2]-$ret[31][2] - $ret[35][2];
    $ctTotal = $ret[0][4] + $ret[3][4] + $ret[10][4] - $ret[15][4] - $ret[22][4]-$ret[31][4] - $ret[35][4];
    $deposit = $this->getDeposit($dbc,$dlog,$args);
    $rowBlank = array(0,0,0,0);
    $rowTotal = array($total,$ctTotal,$ctTotal,0);
    $rowDepost = array($deposit[0],$deposit[1],$deposit[1],0);
    $rowOS = array($deposit[0] - $total,0,$deposit[1] - $ctTotal,0);
    $values = array($rowBlank,$rowTotal,$rowDepost,$rowOS);
    $diffShows = array(0,40,40,0);
    $diffWiths = array(0,39,38,0);
    $totalRow = 0;
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key]; //name
        $row[] = $accountNumbers[$key]; //acct
        $row[] = $values[$key][0]; //pos
        $row[] = $values[$key][1]; // count
        $row[] = $values[$key][2]; //total
        $row[] = 0;
        $row[] = $totalRow;
        $row[] = $diffShows[$key];
        $row[] = $diffWiths[$key];
        $row[] = $reportOrder[$key];
        $ret[] = $row;
    }

    return $ret;
}

private function getSalesTotals($dbc,$dlog,$args) {
    $query = $dbc->prepare("SELECT 
                sum(t.total) as dept_sales_total
                FROM {$dlog} t JOIN core_op.superdepts s on t.department = s.dept_ID
                WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id=?
                AND t.trans_type IN ('I','D') and s.superID < 15 and trans_status !='X'  AND emp_no != 9999");
    $result = $dbc->execute($query,$args);
    $return = 'ERR';
    $row = $dbc->fetch_row($result);
    if ($row) {
        $return = $row[0];
    }
    return $return;
}

private function getTaxTotals($dbc,$dlog,$args) {
    $query = $dbc->prepare("SELECT sum(regPrice),description FROM {$dlog} 
                          WHERE upc ='TAXLINEITEM' AND `datetime` BETWEEN ? AND ? AND store_id =? AND trans_status != 'X'
                          GROUP BY RIGHT(description,7) ORDER BY RIGHT(description,7)  AND emp_no != 9999");
    $result = $dbc->execute($query,$args);
    $return = array();
    $description;
    while ($row = $dbc->fetch_row($result)) {
        $return[] = $row[0];
        $description = $row[1];
    }
    if (sizeof($return) < 2 && $description = '6.25000% SalesTax') {
        $return[] = 0;
    } else {
        array_unshift($return, 0);
    }

    $return[] = array_sum($return);

    return $return;
}

    private function getRATotals($dbc,$dlog,$args) {
        $query = $dbc->prepare("SELECT 
            SUM(case when t.department = 902 then t.total else 0 end) as gift_sales_total,
            SUM(case when t.department = 992 then t.total else 0 end) as member_payment_total,
            SUM(case when t.department = 990 then t.total else 0 end) as charge_payment_total,
            SUM(case when t.upc = 6901 then t.total else 0 end) as paypal_tips_total,
            SUM(case when t.upc = 6900 then t.total else 0 end) as paypal_delivery_total,
            SUM(CASE WHEN t.department NOT IN (902,992,990,994) AND t.upc NOT IN (6900,6901) THEN t.total ELSE 0 END) AS paid_in_total
            FROM {$dlog} t JOIN core_op.superdepts s ON t.department = s.dept_ID
            WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id =? AND trans_status != 'X'
            AND t.trans_type IN ('D','I') AND s.superID = 15  AND emp_no != 9999");
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
            $return[] = $row[$key];
            $retVal = (!is_null($row[$key])) ? $row[$key] : 0 ;
        }
        
        $return[] = array_sum($return);
        return $return;
    }

    private function getDiscountTotals($dbc,$dlog,$args) {
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
            FROM {$dlog}
            WHERE `datetime` BETWEEN ? AND ? AND store_id=? AND trans_status != 'X'  AND emp_no != 9999");
        $discR = $dbc->execute($discQ, $args);
        
        $return = array();
        $discSum = 0;
        $row = $dbc->fetch_row($discR);
        
        //correct rounding errors
        for($key=0;$key<5;$key++) {
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

    private function getCardTotals($dbc,$dlog,$args){
        $query = $dbc->prepare("SELECT
            0 as AmexTotal,
            sum(case when trans_subtype='DC' AND trans_type ='T' then -total else 0 end) as debit_total,
            sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
            sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
            sum(case when trans_subtype='CC' AND trans_type ='T' then -total else 0 end) as credit_total,
            sum(case when trans_subtype='GD' AND trans_type ='T' then -total else 0 end) as gift_card_total
            FROM {$dlog} WHERE `datetime` BETWEEN ? AND ? AND store_id=? AND trans_status != 'X' AND emp_no != 9999");
        
        
        /*
        $query = $dbc->prepare("SELECT 
            sum(case when p.`issuer` = 'DEBIT' and trans_subtype='DC' then p.amount else 0 end) as DEBIT,
            sum(case when p.`issuer` = 'AMEX' and trans_subtype='CC' then p.amount else 0 end) as AMEX,
            sum(case when p.`issuer` in ('DCVR','VISA','M/C') and trans_subtype='CC' then p.amount else 0 end) as CREDIT,
            sum(case when p.`issuer` = 'EBT' and trans_subtype = 'EF' then p.amount else 0 end) as EBTFOOD,
            sum(case when p.`issuer` = 'EBT' and trans_subtype = 'EC' then p.amount else 0 end) as EBTCASH,
            sum(case when p.`issuer` = 'GIFT' and trans_subtype = 'GD' then p.amount else 0 end) as GIFT
            FROM {$dlog} t LEFT JOIN core_trans.PaycardTransactions p on p.registerNo = t.register_no
            AND p.transNo = t.trans_no AND p.empNo = t.emp_no AND p.paycardTransactionID = t.numFlag
            WHERE t.trans_type ='T' AND t.trans_subtype IN ('CC','DC','EC','EF','GD')
            AND t.`datetime` BETWEEN ? AND ? AND t.store_id =?
            AND p.`issuer` != '0'                 AND p.httpCode=200
            AND p.xResultCode = 1 and t.trans_status <>'X'");

        */
    
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        $args[] = $args[0];

        $amexQ = $dbc->prepare("SELECT 
            sum(case when p.`issuer` = 'AMEX' and trans_subtype='CC' then p.amount else 0 end) as AMEX
            FROM {$dlog} t LEFT JOIN core_trans.PaycardTransactions p on p.registerNo = t.register_no
            AND p.transNo = t.trans_no AND p.empNo = t.emp_no AND p.paycardTransactionID = t.numFlag
            WHERE t.trans_type ='T' AND t.trans_subtype IN ('CC','DC','EC','EF','GD')
            AND t.`datetime` BETWEEN ? AND ? AND t.store_id =?
            AND p.`issuer` != '0'                 AND p.httpCode=200
            AND p.xResultCode = 1 and t.trans_status <>'X' AND DATE(p.requestDateTime) = DATE(?)");

        $amexR = $dbc->execute($amexQ, $args);
        $amexW = $dbc->fetch_row($amexR);
        $amexTotal = ($amexW) ? $amexW[0] : 0 ;
        
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
            if ($key == 4)
                $return[] = $row[$key] - $amexTotal;
            elseif ($key == 0)
                $return[] = $amexTotal;
            else
                $return[] = $row[$key];
        }

        $return[] = array_sum($return);
        return $return;
    }

    private function getOtherTotals($dbc,$dlog,$args) {
        $query = $dbc->prepare("SELECT
            sum(case when t.trans_subtype = 'TC' then -total else 0 end) as GiftCert,
            0 as StaffCert,
            0 as downTownDollards,
            0 as buzzRewards,
            sum(case when t.trans_subtype = 'RC' then -total else 0 end) as rCreditTotal,
            sum(case when t.trans_subtype = 'PY' then -total else 0 end) as PayPalTotal,
            sum(case when t.trans_subtype = 'MI' then -total else 0 end) as StoreCharge,
            sum(case when t.department = 994 then -total else 0 end) as PaidOut
            FROM {$dlog} t
            WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id =? AND trans_status != 'X'  AND emp_no != 9999");
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
            $return[] = $row[$key];
        }
        $return[] = array_sum($return);
        return $return;
    }

    private function getCouponTotals($dbc,$dlog,$args) {
        $query = $dbc->prepare("SELECT
            -sum(case when t.trans_subtype = 'IC' then total else 0 end) as StoreCoupons,
            0 as CoopDealCoupons,
            -sum(case when t.trans_subtype IN ('MC','CP') then total else 0 end) as OtherCoupons
            FROM {$dlog} t
            WHERE t.`datetime` between ? and ? and t.store_id =? AND trans_status != 'X'  AND emp_no != 9999");
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
            $return[] = $row[$key];
        }
        $return[] = array_sum($return);
        return $return;
    }

    private function getDeposit($dbc,$dlog,$args) {
        $return = array();
        $posQ = $dbc->prepare("SELECT -sum(case when t.trans_subtype IN ('CA','CK') then t.total else 0 end) as depositTotal
            FROM {$dlog} t
            WHERE t.`datetime` between ? and ? and t.store_id =? AND trans_status != 'X'  AND emp_no != 9999");
        $posR = $dbc->execute($posQ,$args);
        $posW = $dbc->fetch_row($posR);
        $return[] = $posW[0];

        $qDate = new DateTime($args[0]);
        $countQ = $dbc->prepare("SELECT SUM(case when tender_type='CA' then amt - 250.00
                                            when tender_type='CK' then amt else 0 end)
                                FROM dailyCounts 
                                WHERE `date` = ? AND storeID = ? AND tender_type in ('CA','CK')  AND emp_no != 9999");
        $countR = $dbc->execute($countQ,array($qDate->format('Y-m-d'), $args[2]));
        $countW = $dbc->fetch_row($countR);
        $return[] = $countW[0];

        
        $return = array($posW[0],$countW[0]);
        return $return;
    }

    public function getCellFormat($lineNo,$name) {
        $rowNo = $this->cellFormats[$lineNo];
        $formatType = static::rowFormayTypes($rowNo);
        $ret ='';
        $hiddenPrint = (static::$colPrint[$name]) ? '' : 'class="hidden-print"';
        switch ($formatType[$name]) {
            case 'false':      
                break;
            case 'dark':
                $ret = '<td '.$hiddenPrint.'id="{$name}{$objID}" bgcolor="#A9A9A9"></td>';
                break;
            case 'entry':
                $ret = '<td id="{$name}{$objID}" data-value={$value} data-totalid="{$totalID}"><input type="number" class="form-control" value="{$value}" 
                        onchange="saveValue.call(this, this.value, {$objID});"/></td">';
                        // name id value value id 
                break;
            case 'total':
                $ret = '<td id="{$name}{$objID}" data-value={$value} data-totalid="total39">{$value}
                                <input type="hidden"/>
                                </td>'; //name id value value
                break;
            case 'totalEntry':
                $ret = '<td id="{$name}{$objID}" data-value={$value}><input type="number" class="form-control" value="{$value}" 
                        onchange="saveTotal.call(this, this.value, {$objID});"/></td">';
                        // name id value value id 
                break;
            default:
                $ret = '<td '.$hiddenPrint.' id="{$name}{$objID}" data-value={$value}>{$value}
                                <input type="hidden"/>
                                </td>'; //name id value value
                break;
        }
        return $ret;
    }

    public static function getRowReportFormat($lineNo){
        $formatType = static::rowFormat();
        $ret = array();
        switch ($formatType[$lineNo]) {
            case 'totalRow':
                $ret = array(true,false,false,true);      
                break;
            case 'entryRow':
                $ret = array(true,true,true,false);
                break;
            case 'totalEnteryRow':
                $ret = array(true,false,false,true);
                break;
            case 'blankRow':
                $ret = array(false,false,false,false);
                break;
            case 'overshortRow':
                $ret = array(true,true,false,true);
                break;
            default:
                $ret = array(true,false,false,false);
                break;
        }
        return $ret;
    }

    private static function rowFormayTypes($type){
        $ret =array();
        switch ($type) {
            case 'totalRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'',
                    'acctNo' => 'dark',
                    'amt' => '',
                    'count' => 'dark',
                    'total' => 'total',
                    'diff' => '',
                    'totalRow' =>'false',
                    'diffShow' => 'false',
                    'diffWith' => 'false',
                    'storeID' => 'false',
                    'reportOrder' => 'false');
                break;
            case 'entryRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'',
                    'acctNo' => '',
                    'amt' => '',
                    'count' => 'entry',
                    'total' => 'dark',
                    'diff' => '',
                    'totalRow' =>'false',
                    'diffShow' => 'false',
                    'diffWith' => 'false',
                    'storeID' => 'false',
                    'reportOrder' => 'false');
                break;
            case 'totalEnteryRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'',
                    'acctNo' => 'dark',
                    'amt' => '',
                    'count' => 'dark',
                    'total' => 'totalEntry',
                    'diff' => '',
                    'totalRow' =>'false',
                    'diffShow' => 'false',
                    'diffWith' => 'false',
                    'storeID' => 'false',
                    'reportOrder' => 'false');
                break;
            case 'blankRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'dark',
                    'acctNo' => 'dark',
                    'amt' => 'dark',
                    'count' => 'dark',
                    'total' => 'dark',
                    'diff' => 'dark',
                    'totalRow' =>'false',
                    'diffShow' => 'false',
                    'diffWith' => 'false',
                    'storeID' => 'false',
                    'reportOrder' => 'false');
                break;
            case 'overshortRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'dark',
                    'acctNo' => '',
                    'amt' => 'dark',
                    'count' => 'dark',
                    'total' => '',
                    'diff' => '',
                    'totalRow' =>'false',
                    'diffShow' => 'false',
                    'diffWith' => 'false',
                    'storeID' => 'false',
                    'reportOrder' => 'false');
                break;
            default:
                # code...
                break;
        }
        return $ret;
    }

    private static function rowFormat(){
        $formmating = array('totalRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','totalRow',
                            'blankRow','totalRow','totalEnteryRow','overshortRow'
                        );

        return $formmating;
    }


}