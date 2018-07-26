<?php
/*******************************************************************************

    Copyright 2010 Whole Foods Co-op, Duluth, MN

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

class EnterEquityPayments extends \COREPOS\Fannie\API\member\MemberModule {

    public function width()
    {
        return parent::META_WIDTH_HALF;
    }

    function showEditForm($memNum, $country="US")
    {
        global $FANNIE_URL,$FANNIE_TRANS_DB;

        $dbc = $this->db();
        $trans = $FANNIE_TRANS_DB.$dbc->sep();
        //full total;
        $infoQ = $dbc->prepare("SELECT sum(stockPurchase) as payments
                FROM {$trans}stockpurchases
                WHERE card_no=?");
        $infoR = $dbc->execute($infoQ,array($memNum));
        $equity = 0;
        if ($dbc->num_rows($infoR) > 0) {
            $w = $dbc->fetch_row($infoR);
            $equity = $w['payments'];
        }
        // all pruchases.
        $paymentsQ = $dbc->prepare("SELECT tdate, stockPurchase,trans_num
                                    FROM {$trans}stockpurchases
                                    WHERE card_no=?
                                    ORDER BY tdate DESC");
        $paymentsR = $dbc->execute($paymentsQ,array($memNum));



        $ret = "<div class=\"panel panel-default\">
            <div class=\"panel-heading\">Equity Payments</div>
            <div class=\"panel-body\">";

        $ret .= '<div class="form-group">';
        $ret .= '<span class="label primaryBackground">Stock Purchased</span> ';
        $ret .= sprintf('%.2f',$equity);
        $ret .= " <a href=\"{$FANNIE_URL}reports/Equity/index.php?memNum=$memNum\">History</a>";

        $ret .= '</div>';
        // Payment tabel head.
        $ret .= '<table class="table table-striped table-bordered">';
        $ret .= '<thead><tr>';
        $ret.= '<th>Date</th>';
        $ret.= '<th>Payment</th>';
        $ret.= '<th>Note</th>';
        $ret .= '</tr></thead>';
        $ret .= '<tbody>';
        //payment table body.
                //payment input group
        $ret .= '<td> <input name="paymentDate"
                maxlength="10" value="" id="paymentDate"
                class="form-control date-field" /> </td>'; 
        $ret .= '<td> <input type ="number" name="payment_amt" maxlength="10"
                value="" class="form-control" /></td>';
        $ret .= '<td><input name="payment_note" maxlength="15"
                value="" class="form-control" /></td>';
        while ($row = $dbc->fetchRow($paymentsR)) {
            $ret .= '<tr>';
            $payDate = date('Y-m-d', strtotime($row[0]));
            $ret .= sprintf('<td>%s</td>',$payDate);
            $ret .= sprintf('<td>%s</td>',$row[1]);
            $ret .= sprintf('<td>%s</td>',$row[2]);

            $ret .= '</tr>';
        }
        $ret .= '</tbody>';
        $ret .= '</table>';

        $ret .= '</div>';


        $ret .= '<div class="form-group">';
        $ret .= "<a href=\"{$FANNIE_URL}mem/correction_pages/MemEquityTransferTool.php?memIN=$memNum\">Transfer Equity</a>";
        $ret .= ' | ';
        $ret .= "<a href=\"{$FANNIE_URL}mem/correction_pages/MemArEquitySwapTool.php?memIN=$memNum\">Convert Equity</a>";
        $ret .= '</div>';

        $ret .= "</div>";
        $ret .= "</div>";

        return $ret;
    }

    public function saveFormData($memNum, $json=array())
    {
        global $FANNIE_URL,$FANNIE_TRANS_DB;

        $dbc = $this->db();
        $trans = $FANNIE_TRANS_DB.$dbc->sep();

        $paymentDate = FormLib::get_form_value('paymentDate');
        $paymentAmt = FormLib::get_form_value('payment_amt');
        $paymentNote = FormLib::get_form_value('payment_note');

        $upQ = $dbc->prepare("INSERT INTO {$trans}stockpurchases (card_no,stockPurchase,tdate,trans_num,trans_id, dept)
                VALUES (?,?,?,?,0,992)");
        $upR = $dbc->execute($upQ, array($memNum,$paymentAmt,$paymentDate,$paymentNote));

        if ( $upR === False )
            return "Error: problem saving payments.";
        else
            return "";

        return "";

    // saveFormData
    }

}

