<?php
/*******************************************************************************

    Copyright 2014 Whole Foods Co-op, Duluth, MN

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
namespace COREPOS\Fannie\API\item\signage;
if (!class_exists('FpdfWithMultiCellCount')) {
    include(dirname(__FILE__) . '/../FpdfWithMultiCellCount.php');
}

class CakeOfTheMonth18upL extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 29.37;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $fontHead = 'ModestoIOpenPrimary';
    protected $font = 'ModestoOpenInlineFill';
    protected $fontH = 'ModestoOpenInlineFillH';
    protected $fontM = 'ModestoOpenInlineFillM';

    protected $width = 288;
    protected $height = 124;
    protected $startX = 18;
    protected $startY = 25;
    protected $borderLineWidth=5;
    public function drawPDF()
    {
        set_time_limit(660);
        //define('FPDF_FONTPATH', dirname(__FILE__) . '/../../../src/fpdf/font/');
        define('FPDF_FONTPATH',dirname(__FILE__) . '/../../../src/fpdf/font/proprietary/');
        $pdf = new \FpdfWithMultiCellCount('P', 'pt', 'Letter');
        $pdf->AddFont('ModestoIOpenPrimary','','ModestoIOpenPrimary.php');
        $pdf->AddFont('ModestoOpenInlineFillH', '', 'ModestoOpen-InlineFillH.php');
        $pdf->AddFont('ModestoOpenInlineFillM', '','ModestoOpen-InlineFillM.php');
        $pdf->AddFont('ModestoOpenInlineFill', '', 'ModestoOpenInlineFill.php');
        $pdf->SetMargins($this->startX, $this->startY, $this->startX);
        $pdf->SetAutoPageBreak(false);
        //$pdf = $this->loadPluginFonts($pdf);
        $pdf->SetFont($this->font, '', 16);

        $data = $this->loadItems();
        $count = 0;
        $sign = 0;
        //border rect is drawn outside width this accounts for the extra size in placemnt.
        //the fallowing two methods account for porder size when seting starting postions.
        $xOffset = $this->width + $this->borderLineWidth +1;
        $yOffset = $this->height + $this->borderLineWidth +1;
        foreach ($data as $item) {
            if ($count % 10 === 0) {
                $pdf->AddPage();
                $sign = 0;
                //$file = dirname(__FILE__) . '/../../../item/images/MemberAlminac2x4BG.jpg';
                //if (file_exists($file) && is_file($file)) {
                //    $pdf->Image($file, 45, 55, 700);
                //}
            }
            $row = floor($sign / 2);
            $column = $sign % 2;
            $info = $this->getExtraInfo($item['upc']);
            $y=$this->startY;
            $x=$this->startX;
            $textWidth = $this->width - ($this->width*.4) - 4;


            //draw border
            $pdf->SetDrawColor(30, 77, 44);
            $pdf->SetLineWidth($this->borderLineWidth);
            $y += $yOffset*$row;
            $x += $xOffset*$column;
            $pdf->Rect($x, $y, $this->width, $this->height);
            $pdf->SetLineWidth(1);
            
            //CAKE of the month.
            $fontSize = 21;
            $y += $this->borderLineWidth +1;
            $pdf->SetFont($this->fontHead, '',$fontSize);
            $pdf->SetTextColor(150,113,70);
            $pdf->SetXY($x,$y);
            $pdf->Cell($this->width, $fontSize, 'CAKE OF THE MONTH!',0,0,'C');

            $pdf->SetTextColor(30,77,44);
            //Made Right here logo
            $y += $fontSize +2;
            $imagePath = dirname(__FILE__) . '/../../../item/images/MadeRightHere.png';
            $imageWidth = 61;
            $imageStartX = $this->startX+$this->borderLineWidth +19;
            $pdf->Image($imagePath, $imageStartX+$xOffset*$column, $y, $imageWidth);
            //Member Banner
            $imagePath = dirname(__FILE__) . '/../../../item/images/MembersOnlybanner.png';
            $imageWidth = $this->width*.40;
            $imageStartX = $this->startX+2;
            $pdf->Image($imagePath, $imageStartX+$xOffset*$column, $y+43, $imageWidth);

            //price
            $x += 116; //space from edge to the start of the text elements.
            $y += 3; //space bewtten top and start of first element.
            $fontSize = 26;
            $pdf->SetFont($this->fontM,'',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($textWidth, $fontSize, sprintf('$%.2f', $item['normal_price']), 0, 0, 'C');
            //Member Alminac Price
            $y += $fontSize;
            $fontSize = 8.75; 
            $cellSize = 30; // for compression, need to chagne x by this amount and width to make the letters closer.
            $pdf->SetXY($x+$cellSize/2, $y);
            $pdf->SetFont($this->fontH,'',$fontSize);
            $pdf->CellFit($textWidth-$cellSize, $fontSize, "MEMBERS' ALMANAC PRICE!", 0, 0, 'C');

            //sale date.
            $y += $fontSize + .5;
            $fontSize = 9;
            $cellSize = 53;
            $pdf->SetFont($this->font,'',$fontSize);
            $pdf->SetXY($x+$cellSize/2, $y);
            $startDate = new \DateTime($item['startDate']);
            $endDate = new \DateTime($item['endDate']);
            $dateString = 'SALE '.$startDate->format('n/d').'-'.$endDate->format('n/d/y');
            $pdf->CellFit($textWidth-$cellSize, $fontSize, $dateString, 0, 0, 'C');


            //desciption
            $y += $fontSize + 1.5;
            $fontSize = 11;
            $pdf->SetFont($this->fontH,'',$fontSize);
            $pdf->SetXY($x, $y);
            $lines = $pdf->MultiCellRet($textWidth, $fontSize, $item['description'],0, 'C');
            $blankSpace = ($lines==1) ? $fontSize : 0;
            //$pdf->Cell($textWidth/2, $fontSize, sprintf('REG. $%.2f', $info['normal_price']), 0,0, 'L');
            //MultiCellRet
            
            //$pdf->Ln(1);
            //brand
            $brand = 'FRANKLIN COMMUNITY CO-OP';
            $y += $fontSize +1;
            $fontSize = 8.1;
            $spacing = 5;
            $pdf->SetFont($this->fontM,'',$fontSize);
            $pdf->SetXY($x, $y);            
            
            $pdf->Cell($textWidth, $fontSize, $brand, 0, 0, 'C');

            //reg price
            $y += $fontSize+$blankSpace+2;
            $fontSize = 8.72;
            $pdf->SetFont($this->fontM,'',$fontSize);
            $pdf->SetXY($x+3, $y);
            $pdf->Cell($textWidth/2 -3, $fontSize, sprintf('REG. $%.2f', $info['normal_price']), 0,0, 'L');
            //size
            $x += $textWidth/2;
            //$pdf->SetX($this->startX -2 + $xOffset*$column + $this->width/2);
            $pdf->SetXY($x, $y);
            $pdf->Cell($textWidth/2 -3, $fontSize, $item['size'].' CAKE', 0,0, 'R');

            //$info = $this->getExtraInfo($item['upc']);
            //$pdf->SetX(10 + ($this->width*$column));
            //$pdf->MultiCell($this->width-5, 4, str_replace('<br />', "\n", $info['long_text']), 0, 'L');

            //$pdf->SetX(10 + ($this->width*$column));
            //$pdf->SetFont($this->font, 'B', $this->MED_FONT);
            //$pdf->Cell($this->width-5, 9, $item['originName'], 0, 1, 'R');
/*
            $pdf->SetFont($this->font, '', $this->MED_FONT);
            $pdf->SetXY(10 + ($this->width*$column), 5 + ($row*$this->height) + $this->height - 10 - 1);
            $pdf->Cell($this->width-5, 7, '', 1); // blank box
            $pdf->SetXY(15 + ($this->width*$column), 5 + ($row*$this->height) + $this->height - 10);
            $pdf->Cell(20, 5, 'PLU#', 0, 0, 'L');
            $pdf->SetFont($this->font, 'B', $this->MED_FONT);
            $pdf->Cell(30, 5, ltrim($item['upc'], '0'), 0, 0, 'L');
            $pdf->SetFont($this->font, '', $this->MED_FONT);
            $pdf->Cell(20, 5, sprintf('$%.2f/lb.', $item['normal_price']), 0, 0, 'L');
            $file = dirname(__FILE__) . '/../../../item/images/nutrition-facts/' . $info['nutritionFacts'];
            if (file_exists($file) && is_file($file)) {
                $pdf->Image($file, 10+($this->width*$column), 11.5 + ($row*$this->height) + 52, 88.9);
            } */

            $count++;
            $sign++;
        }

        $pdf->Output('CakeOfTheMonth18upL.pdf', 'I');
        set_time_limit(30);
    }

    protected function getExtraInfo($upc)
    {
        $dbc = \FannieDB::get(\FannieConfig::config('OP_DB'));
        $prep = $dbc->prepare('SELECT * FROM products WHERE upc=?');
        return $dbc->getRow($prep, array($upc));
    }
}

