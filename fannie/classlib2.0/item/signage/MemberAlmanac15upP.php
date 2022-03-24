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

class MemberAlmanac15upP extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 29.37;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $font = 'ModestoOpenInlineFill';
    protected $fontH = 'ModestoOpenInlineFillH';
    protected $fontM = 'ModestoOpenInlineFillM';

    protected $width = 134;
    protected $height = 187.5;
    protected $startX = 45;
    protected $startY = 20;
    protected $borderLineWidth=5;
    public function drawPDF()
    {
        set_time_limit(660);
        //define('FPDF_FONTPATH', dirname(__FILE__) . '/../../../src/fpdf/font/');
        define('FPDF_FONTPATH',dirname(__FILE__) . '/../../../src/fpdf/font/proprietary/');
        $pdf = new \FpdfWithMultiCellCount('L', 'pt', 'Letter');
        $pdf->AddFont('ModestoOpenInlineFillH', '', 'ModestoOpen-InlineFillH.php');
        $pdf->AddFont('ModestoOpenInlineFillM', '','ModestoOpen-InlineFillM.php');
        $pdf->AddFont('ModestoOpenInlineFill', '', 'ModestoOpenInlineFill.php');
        $pdf->SetMargins(45, 20, 45);
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
            if ($count % 15 === 0) {
                $pdf->AddPage();
                $sign = 0;
                //$file = dirname(__FILE__) . '/../../../item/images/MemberAlminac2x4BG.jpg';
                //if (file_exists($file) && is_file($file)) {
                //    $pdf->Image($file, 45, 55, 700);
                //}
            }
            $row = floor($sign / 5);
            $column = $sign % 5;
            $info = $this->getExtraInfo($item['upc']);



            //draw border
            $pdf->SetDrawColor(30, 77, 44);
            $pdf->SetLineWidth($this->borderLineWidth);
            $pdf->Rect($this->startX + $xOffset*$column, $this->startY+$yOffset*$row, $this->width, $this->height);
            $pdf->SetLineWidth(1);
            $pdf->SetTextColor(30,77,44);

            //price
            $pdf->SetFont($this->fontM,'',29.37);
            $pdf->SetXY($this->startX  + $xOffset*$column, $this->startY + 21 + $row*$yOffset);
            $pdf->Cell($this->width, 29.37, sprintf('$%.2f', $item['normal_price']), 0, 0, 'C');
            //Member Alminac Price
            $pdf->SetXY($this->startX  + $xOffset*$column, $this->startY+51.37 + $row*$yOffset);
            $pdf->SetFont($this->fontM,'',6.33);
            $pdf->Cell($this->width, 6.33, "MEMBERS' ALMANAC PRICE!", 0, 0, 'C');

            //sale date.
            $pdf->SetFont($this->font);
            $pdf->SetFontSize(7);
            $startDate = new \DateTime($item['startDate']);
            $endDate = new \DateTime($item['endDate']);
            $dateString = 'SALE '.$startDate->format('n/d').'-'.$endDate->format('n/d/y');

            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY+58.7+$row*$yOffset);
            $pdf->Cell($this->width, 7, $dateString, 0, 0, 'C');

            //Logo
            $pdf->SetXY(76,137);
            $imagePath = dirname(__FILE__) . '/../../../item/images/MembersOnlySeal.png';
            $imageWidth = 80.77;
            $imageStartX = $this->startX + $this->width/2-$imageWidth/2;
            $pdf->Image($imagePath, $imageStartX+$xOffset*$column, $this->startY +66.7 + $row*$yOffset, $imageWidth);
            //desciption
            $pdf->SetFont($this->fontH,'',8);
            $pdf->SetXY($this->startX+$xOffset*$column, $this->startY + 146.47 + $row*$yOffset);
            $lines = $pdf->MultiCellRet($this->width, 8, $item['description'], 0, 'C');
            $blankSpace = ($lines==1) ? 8 : 16;
            //brand
            $pdf->SetFont($this->fontM,'',6.1);
            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY +147.47 + $blankSpace + $row*$yOffset);
            $brand = ($item['brand']) ? $item['brand'] : 'NEED BRAND INFO '.$lines;
            $pdf->Cell($this->width, 6.1, $brand, 0, 0, 'C');


            /*
                //desciption
            $y += $fontSize + .5;
            $fontSize = 8;
            $pdf->SetFont($this->fontH,'',$fontSize);
            $pdf->SetXY($x, $y);
            $lines = $pdf->MultiCellRet($textWidth, $fontSize, $item['description'], 0, 'C');
            $blankSpace = ($lines==1) ? $fontSize : 0;
            //$pdf->Ln(1);
            //brand
            $y += $fontSize*$lines;
            $fontSize = 6.1;
            $pdf->SetFont($this->fontM,'',$fontSize);
            $pdf->SetXY($x, $y);            
            $brand = ($item['brand']) ? $item['brand'] : 'NEED BRAND INFO '.$lines;
            $pdf->Cell($textWidth, $fontSize, $brand, 0, 0, 'C');

            */


            //upc
            $pdf->SetFont($this->font,'',7);
            $pdf->SetFontSize($this->SMALL_FONT);
            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY +170.57 + $row*$yOffset);
            $pdf->Cell($this->width, 7, 'UPC: '.$item['upc'], 0,0, 'C');

            //reg price
            $pdf->SetFont($this->fontM,'',6.72);
            $pdf->SetXY($this->startX +2 + $xOffset*$column, $this->startY + 177.57 + $row*$yOffset);
            $pdf->Cell($this->width/2, 6.72, sprintf('REG. $%.2f', $info['normal_price']), 0,0, 'L');
            //size
            $pdf->SetX($this->startX -2 + $xOffset*$column + $this->width/2);
            $pdf->Cell($this->width/2, 6.72,$item['size'], 0,0, 'R');

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

        $pdf->Output('MemberAlmanac15upL.pdf', 'I');
        set_time_limit(30);
    }

    protected function getExtraInfo($upc)
    {
        $dbc = \FannieDB::get(\FannieConfig::config('OP_DB'));
        $prep = $dbc->prepare('SELECT * FROM products WHERE upc=?');
        return $dbc->getRow($prep, array($upc));
    }
}

