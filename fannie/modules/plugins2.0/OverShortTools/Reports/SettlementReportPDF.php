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
    protected $startX = 55;
    protected $startY = 35;
    protected $data = array();
    protected $headers = array();
    protected $deptTable = array();
    protected $notes = array();
    protected $store = 0;
    protected $date = '';

    function __construct($dbc,$date,$store,$dlog) {

        $this->store = $store;
        $this->date = $date;
        $picker = FormLib::storePicker('store');
        $storeName = $picker['names'][$store];
        $query = $dbc->prepare("SELECT lineName,acctNo,`count`,total
                  FROM dailySettlement
                  WHERE `date`=? AND storeID=? AND lineNo !=4
                  ORDER BY reportOrder;");
        $result = $dbc->execute($query,array($date,$store));
        $report = array();
        $report[] = array($storeName,' Daily Settlement',$date,);
        $report[] = array('','(Account No)','Count','(Totals)');
        while ($row = $dbc->fetch_row($result)) {
            $report[] = array($row[0],$row[1],$row[2],$row[3]);
        }


        $deptQ = $dbc->prepare("SELECT n.super_name, SUM(t.total) 
                    FROM {$dlog} t 
                    JOIN core_op.superdepts s ON t.department = s.dept_ID 
                    JOIN core_op.superDeptNames n ON s.superID = n.superID
                    WHERE t.trans_type IN ('I','D') AND s.superID < 14
                    AND t.`datetime` BETWEEN ? AND ?
                    AND t.store_id = ? AND trans_status != 'X'
                    GROUP BY s.superID
                    UNION
                    SELECT d.dept_name, SUM(t.total) 
                    FROM {$dlog} t 
                    JOIN core_op.superdepts s ON t.department = s.dept_ID 
                    JOIN core_op.departments d on s.dept_ID = d.dept_no
                    WHERE t.trans_type IN ('I','D') AND s.superID = 14
                    AND t.`datetime` BETWEEN ? AND ?
                    AND t.store_id = ? AND trans_status != 'X'
                    GROUP BY t.department");
        $startDate = $date.' 00:00:00';
        $endDate = $date.' 23:59:59';
        $args = array($startDate,$endDate,$store,$startDate,$endDate,$store);
        $deptR = $dbc->execute($deptQ,$args);
        $deptReport = array();
        $deptReport[] = array('Department','Sales');
        $deptSum = 0;
        while ($deptW = $dbc->fetch_row($deptR)){
            $deptReport[] = array($deptW[0],$deptW[1]);
            $deptSum+=$deptW[1];
        }
        $deptReport[] = array('','');
        $deptReport[] = array('Total Department Sales',$deptSum);
        $deptReport[] = array('','');

        $deptQ2 = $dbc->prepare("SELECT d.dept_name, SUM(t.total) 
                    FROM {$dlog} t 
                    JOIN core_op.superdepts s ON t.department = s.dept_ID 
                    JOIN core_op.departments d on s.dept_ID = d.dept_no
                    WHERE t.trans_type IN ('I','D') AND s.superID = 15
                    AND t.`datetime` BETWEEN ? AND ?
                    AND t.store_id = ? AND trans_status != 'X'
                    GROUP BY t.department");
        $deptR2 = $dbc->execute($deptQ2,array($startDate,$endDate,$store));
        while ($deptW = $dbc->fetch_row($deptR2)){
            $deptReport[] = array($deptW[0],$deptW[1]);
            $deptSum+=$deptW[1];
        }
        $deptReport[] = array('','');
        $deptReport[] = array('Total Sales',$deptSum);

        $notesQ = $dbc->prepare("SELECT notes FROM dailySettlementNotes WHERE `date`=? AND storeID=?");
        $notesR = $dbc->execute($notesQ,array($date,$store));
        $notesArr = array();
        while ($row = $dbc->fetch_row($notesR)) {
            $notesArr[] = $row[0];
        }

        $this->notes = $notesArr;
        $this->deptTable = $deptReport;
        $this->data = $report;
    }

    public function drawPDF() {
        $pdf = new \FPDF('L', 'pt', 'Letter');
        $pdf->SetMargins($this->startX, $this->startY, $this->startX);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();
        $pdf->SetFont('Arial','',11);
        $pdf->SetFillColor(155,155,155);
        $pdf->SetDrawColor(155,155,155);

        $x = $this->startX;
        $y = $this->startY;
        $pdf->SetXY($x,$y);
        $lineWidths = array(185,80,80,80,80);
        foreach ($this->data as $lineNo => $line) {
            $pdf->setXY($this->startX,$y);
            $lineFromat = array();
            switch ($lineNo) {
                case 0:
                case 1:
                    $lineFormat = array(true,true,true,true);
                    break;
                case 2:
                case 3:
                case 4:
                case 5:
                    $lineFormat = FCCSettlementModule::getRowReportFormat($lineNo-2);
                    break;
                default:
                    $lineFormat = FCCSettlementModule::getRowReportFormat($lineNo-1);
                    break;
            }
            //$lineFormat = ($lineNo < 4) ? FCCSettlementModule::getRowReportFormat($lineNo+2) : FCCSettlementModule::getRowReportFormat($lineNo+3) ;
            foreach($line as $key => $info) {
                //$pdf->SetX($x);
                if($lineFormat[$key]){
                    $ali = (in_array($key, array(0,1)) || $lineNo == 1) ? 'C' : 'R' ;
                    $pdf->Cell($lineWidths[$key],13,$info,1,0,$ali);
                } else {
                    $pdf->Cell($lineWidths[$key],13,'',1,0,'C',true);
                }
                
                $x += $lineWidths[$key];
            }
            //$pdf->Cell(185,12,$line[0],1,0,C);
            $y +=13;
        }

        $y=$this->startY;
        $x=$this->startX + array_sum($lineWidths)-55;
        $pdf->SetXY($x,$y);
        $cellWidths = array(160,80);
        //$pdf->Cell(60,13,'here we',1,0,C);
        foreach ($this->deptTable as $lineNo => $line) {
            $pdf->setXY($x,$y);
            foreach ($line as $col => $info) {
                $ali = ($col==0) ? 'L' : 'R' ;
                $pdf->Cell($cellWidths[$col],13,$info,1,0,$ali);
                //$x += $cellWidths[$col];
            }
            $y+=13;
        }
        
        $y+=13;
        $x=$this->startX + array_sum($lineWidths)-55;
        $pdf->SetXY($x,$y);
        $pdf->Cell(240,13,'Notes:',1,0,'C');
        $y+=13;
        $pdf->SetXY($x,$y);
        $noteStr ='';
        foreach ($this->notes as $key => $note) {
            $noteStr.= $note;
        }
        $pdf->MultiCell(240, 13,$noteStr,1);

        
        $pdf->Output('SettlementReportPDF.pdf','I');
    }
}