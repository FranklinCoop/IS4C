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

class SettlementReportPDF {
    protected $BIG_FONT = 29.37;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $width = 206;
    protected $height = 83;
    protected $startX = 85;
    protected $startY = 42;
    protected $data = array();
    protected $store = 0;
    protected $date = '';

    function __construct($dbc,$date,$store) {

        $this->store = $store;
        $this->date = $date;
        $showCol = FCCSettlementModule::getColPrint();
        $query = $dbc->prepare("SELECT lineName,acctNo,`count`,total
                  FROM dailySettlement
                  WHERE `date`=? AND storeID=?
                  ORDER BY reportOrder");
        $result = $dbc->execute($query,array($date,$store));
        $report = array();
        while ($row = $dbc->fetch_row($result)) {
            $report[] = $row;
        }

        $this->data = $report;
    }

    public function drawPDF() {
        $pdf = new \FPDF('P', 'pt', 'Letter');
        $pdf->SetMargins($this->startX, $this->startY, $this->startX);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFont('Arial','',11);



        $x = $this->startX;
        $y = $this->startY;
        $pdf->SetXY($x,$y);
        foreach ($this->data as $line) {
            $pdf->setY($y);
            $pdf->Cell(185,12,$line[0],1,0,C);
            $y +=12;
        }
        

        
        $pdf->Output('SettlementReportPDF.pdf','I');
    }
}