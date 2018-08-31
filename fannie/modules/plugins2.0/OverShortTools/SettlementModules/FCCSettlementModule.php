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
    protected $colPrint = array(true,true,false,true,true,false);
    protected $numRows = 40;
    protected $rowData;
    protected $cellFormats = array();



    function __construct($dbc,$dlog,$date,$store) {
        $model = new DailySettlementModel($dbc);
        $model->date($date,'=');
        $model->storeID($store,'=');
        if (!$model->find()) {
            $model = $this->populateAccountTable($dbc,$dlog,$store,$date);
            $model->date($date,'=');
            $model->storeID($store,'=');
        }
        $this->rowData = $model;
        $this->cellFormats = $this->rowFormat();
    }

    public function getTable($dbc,$dlog,$date,$store) {
        $model = new DailySettlementModel($dbc);
        $model->date($date,'=');
        $model->storeID($store,'=');
        return $model;
    }


private function populateAccountTable($dbc,$dlog,$store,$date){
    $date1 = $date.' 00:00:00';
    $date2 = $date.' 23:59:59';
    $args = array($date1,$date2,$store);
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
        $model->storeID($store);
        $model->save();
    }
    return $model;
}

private function genRowData($dbc,$dlog,$args) {
    $ret = array();
    $row = array();
    //Sales Totals
    $value = $this->getSalesTotals($dbc,$dlog,$args);
    $row[] = 'SALES TOTALS';
    $row[] = '';
    $row[] = $value;
    $row[] = $value;
    $row[] = $value;
    $row[] = 0;
    $ret[] = $row;
    //Tax Section
    $rowNames = array('PLUS SALES TAX Collected','PLUS SALES TAX Collected','TOTAL TAX');
    $accountNumbers = array('(2450A990)','(2400M990)','');
    $values = $this->getTaxTotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $ret[] = $row;
    }


    //R/A section
    $rowNames = array('TRASH STICKER SALES','PLUS GIFT CARD Sold','PLUS MEMBER EQUITY Payment','PLUS CHARGE Payment',
                      'PAYPAL TIPS','DELIVERY FEE','PLUS R/A OTHER (PAID-IN)','TOTAL R/A');
    $accountNumbers = array('(4060G900)','(2500A990)','(2800A990)','aditional entry','(5255G500)','(5255G500)','aditional entry','');
    $values = $this->getRATotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $ret[] = $row;
    }

    //Discount Section
    $rowNames = array('LESS Working Discount','LESS Staff Discount','LESS Senior Discount','LESS Food for All Discount','TOTAL DISCOUNTS');
    $accountNumbers = array('(4160G900)','(4150G900)','(4130G900)','(4110G900)','');
    $values = $this->getDiscountTotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = $values[$key];
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $ret[] = $row;
    }

    //Card Media Section
    $rowNames = array('VISA/MASTER/DISCOVER','LESS AMEX','LESS DEBIT','LESS SNAP / EBT: Food',
                      'LESS SNAP / EBT: Cash','LESS GIFT CARD Redeemed','TOTAL CARD MEDIA');
    $accountNumbers = array('(1025A990)','(1025A990)','(1025A990)','(1025A990)','(1025A990)','(2500A990)','');
    $values = $this->getCardTotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = $values[$key];
        $ret[] = $row;
    }

    // other tenders
    $rowNames = array('LESS Paper GIFT CERT','LESS Staff GIFT CERT','LESS Greenfield $ GIFT CERT','BUZZ REWARDS','r CREDITS','PAYPAL','LESS STORE CHARGE','LESS PAID OUT','TOTAL Other Credits');
    $accountNumbers = array('(2500A990)','(7800G990)','(1230A990)','(1065A990)','(1070A990)','(1075A990)','(1200A990)','additional entry','');
    $values = $this->getOtherTotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = $values[$key];
        $ret[] = $row;
    }

    //coupon section
    $rowNames = array('LESS STORE COUPON','LESS CO-OP DEALS COUPONS','LESS OTHER VENDOR COUPONS','TOTAL COUPON');
    $accountNumbers = array('(4170G900)','(1210A990)','(1215A990)','');
    $values = $this->getCouponTotals($dbc,$dlog,$args);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = 0;
        $row[] = $values[$key];
        $ret[] = $row;
    }

    //Total & overshort section
    $rowNames = array('','TOTAL','BANK DEPOSIT','OVER / SHORT');
    $accountNumbers = array('','','','(419G900)');
    $total = $ret[0][2] + $ret[3][2] + $ret[11][2] - $ret[4][2] - $ret[16][2] - $ret[23][2] - $ret[32][2] -$ret[36][2];
    $deposit = $this->getDeposit($dbc,$dlog,$args);
    $values = array(0,$total,$deposit,$total-$deposit);
    for ($key=0;$key<sizeof($values);$key++){
        $row = array();
        $row[] = $rowNames[$key];
        $row[] = $accountNumbers[$key];
        $row[] = $values[$key];
        $row[] = 0;
        $row[] = ($key == sizeof($values) -1) ? $values[$key] : 0;
        $row[] = 0;
        $ret[] = $row;
    }

    return $ret;
}

private function getSalesTotals($dbc,$dlog,$args) {
    $query = $dbc->prepare("SELECT 
        sum(case when department!=0 and trans_type !='T' and department NOT IN (992,990,994,995,902) and upc!='0000000001930' AND trans_status != 'X' then total else 0 end) as dept_sales_total
        FROM {$dlog}
        WHERE `datetime` BETWEEN ? AND ? AND store_id=?");
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
                          GROUP BY description");
    $result = $dbc->execute($query,$args);
    $return = array();
    while ($row = $dbc->fetch_row($result)) {
        $return[] = $row[0];
    }
    $return[] = array_sum($return);

    return $return;
}

    private function getRATotals($dbc,$dlog,$args) {
        $query = $dbc->prepare("SELECT 
        sum(case when t.department = 960 then t.total else 0 end) as trashStickersTotal,
        sum(case when t.department = 902 then t.total else 0 end) as gift_sales_total,
        sum(case when t.department = 992 then t.total else 0 end) as member_payment_total,
        sum(case when t.department = 990 then t.total else 0 end) as charge_payment_total,
        sum(case when t.upc = 6901 then t.total else 0 end) as paypal_tips_total,
        sum(case when t.upc = 6900 then t.total else 0 end) as paypal_delivery_total,
        sum(case when t.department = 995 then t.total else 0 end) as paid_in_total
        from {$dlog} t
        where t.`datetime` between ? and ? and t.store_id =? AND trans_status != 'X'");
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
            $return[] = $row[$key];
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
            WHERE `datetime` BETWEEN ? AND ? AND store_id=? AND trans_status != 'X'");
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
        $query = $dbc->prepare("SELECT  sum(case when trans_subtype='CC' AND trans_type ='T' then -total else 0 end) as credit_total,
            0 as AmexTotal,
            sum(case when trans_subtype='DC' AND trans_type ='T' then -total else 0 end) as debit_total,
            sum(case when trans_subtype='EF' AND trans_type ='T' then -total else 0 end) as snap_total,
            sum(case when trans_subtype='EC' AND trans_type ='T' then -total else 0 end) as snap_cash_total,
            sum(case when trans_subtype='GD' AND trans_type ='T' then -total else 0 end) as gift_card_total
            FROM {$dlog} WHERE `datetime` BETWEEN ? AND ? AND store_id=? AND trans_status != 'X'");
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
        $return = array();
        for ($key=0;$key<$dbc->numFields($result);$key++) {
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
            WHERE t.`datetime` BETWEEN ? AND ? AND t.store_id =? AND trans_status != 'X'");
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
            WHERE t.`datetime` between ? and ? and t.store_id =? AND trans_status != 'X'");
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
        $query = $dbc->prepare("SELECT -sum(case when t.trans_subtype IN ('CA','CK') then t.total else 0 end) as depositTotal
            FROM {$dlog} t
            WHERE t.`datetime` between ? and ? and t.store_id =? AND trans_status != 'X'");
        $result = $dbc->execute($query,$args);
        $row = $dbc->fetch_row($result);
        return $row[0];
    }

    public function getCellFormat($lineNo,$name) {
        $rowNo = $this->cellFormats[$lineNo];
        $formatType = $this->rowFormayTypes($rowNo);
        $ret ='';
        switch ($formatType[$name]) {
            case 'false':      
                break;
            case 'dark':
                $ret = '<td id="%s %d %s %s" bgcolor="#A9A9A9"></td>';
                break;
            case 'entry':
                $ret = '<td><input type="text" class="form-control" value="%s" 
                        onchange="saveValue.call(this, this.value, %d);" id="%s" name="%s"/></td">';
                        //onchange="saveType.call(this, this.value, %d);" 
                break;
            default:
                $ret = '<td>%s</font>
                                <input type="hidden" name="%d" id="%s"  value="%s" />
                                </td>';
                break;
        }
        return $ret;
    }

    private function rowFormayTypes($type){
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
                    'total' => '',
                    'diff' => '',
                    'storeID' => 'false');
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
                    'storeID' => 'false');
                break;
            case 'totalEnteryRow':
                $ret = array('id'=>'false',
                    'date' =>'false',
                    'lineNo' => 'false',
                    'lineName'=>'',
                    'acctNo' => 'dark',
                    'amt' => '',
                    'count' => 'dark',
                    'total' => 'entry',
                    'diff' => '',
                    'storeID' => 'false');
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
                    'storeID' => '');
                break;
            default:
                # code...
                break;
        }
        return $ret;
    }

    private function rowFormat(){
        $formmating = array('totalRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','entryRow','totalRow',
                            'entryRow','entryRow','entryRow','totalRow',
                            'blankRow','totalRow','totalEnteryRow','totalRow'
                        );

        return $formmating;
    }


}