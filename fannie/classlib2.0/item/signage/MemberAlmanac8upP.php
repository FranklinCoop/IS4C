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
if (!class_exists('PDF_ImageAlpha')) {
    include(dirname(__FILE__) . '/../FpdfWithMultiCellCount.php');
}

class MemberAlmanac8upP extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 36;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $font = 'ModestoOpenInlineFill';
    protected $fontH = 'ModestoOpenInlineFillH';
    protected $fontM = 'ModestoOpenInlineFillM';

    protected $width = 168;
    protected $height = 232+7;
    protected $startX = 45;
    protected $startY = 55;
    protected $borderLineWidth=7;
    public function drawPDF()
    {
        set_time_limit(660);
        //define('FPDF_FONTPATH', dirname(__FILE__) . '/../../../src/fpdf/font/');
        define('FPDF_FONTPATH',dirname(__FILE__) . '/../../../src/fpdf/font/proprietary/');
        $pdf = new \FpdfWithMultiCellCount('L', 'pt', 'Letter');
        $pdf->AddFont('ModestoOpenInlineFillH', '', 'ModestoOpen-InlineFillH.php');
        $pdf->AddFont('ModestoOpenInlineFillM', '','ModestoOpen-InlineFillM.php');
        $pdf->AddFont('ModestoOpenInlineFill', '', 'ModestoOpenInlineFill.php');
        $pdf->SetMargins(45, 55, 45);
        $pdf->SetAutoPageBreak(false);
        $pdf = $this->loadPluginFonts($pdf);
        $pdf->SetFont($this->font, '', 16);

        $data = $this->loadItems();
        $count = 0;
        $sign = 0;
        //border rect is drawn outside width this accounts for the extra size in placemnt.
        //the fallowing two methods account for porder size when seting starting postions.
        $xOffset = $this->width + $this->borderLineWidth +2;
        $yOffset = $this->height + $this->borderLineWidth +2;//
        foreach ($data as $item) {
            if ($count % 8 === 0) {
                $pdf->AddPage();
                $sign = 0;
                //$file = dirname(__FILE__) . '/../../../item/images/MemberAlminac2x4BG.jpg';
                //if (file_exists($file) && is_file($file)) {
                //    $pdf->Image($file, 45, 55, 700);
                //}
            }
            $row = floor($sign / 4);
            $column = $sign % 4;
            $info = $this->getExtraInfo($item['upc']);



            //draw border
            $pdf->SetDrawColor(30, 77, 44);
            $pdf->SetLineWidth($this->borderLineWidth);
            $pdf->Rect($this->startX + $xOffset*$column, $this->startY+$yOffset*$row, $this->width, $this->height);
            $pdf->SetLineWidth(1);
            $pdf->SetTextColor(30,77,44);

            //price
            $pdf->SetFont($this->fontM,'',$this->BIG_FONT);
            $pdf->SetXY($this->startX  + $xOffset*$column, $this->startY + 20 + $row*$yOffset);
            $pdf->Cell($this->width, $this->BIG_FONT, sprintf('$%.2f', $item['normal_price']), 0, 0, 'C');
            //Member Alminac Price
            $pdf->SetXY($this->startX  + $xOffset*$column, $this->startY+55 + $row*$yOffset);
            $pdf->SetFontSize($this->MED_FONT);
            $pdf->Cell($this->width, $this->MED_FONT, "Members' Almanac Price!", 0, 0, 'C');

            //sale date.
            $pdf->SetFont($this->font);
            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY+65+$row*$yOffset);
                        $startDate = new \DateTime($item['startDate']);
            $endDate = new \DateTime($item['endDate']);
            $dateString = 'SALE '.$startDate->format('n/d').'-'.$endDate->format('n/d/y');

            $pdf->Cell($this->width, $this->MED_FONT, $dateString, 0, 0, 'C');

            //Logo
            $pdf->SetXY(76,137);
            $imagePath = dirname(__FILE__) . '/../../../item/images/MembersOnlySeal.png';
            $imageWidth = 114;
            $imageStartX = $this->startX + $this->width/2-$imageWidth/2;
            $pdf->Image($imagePath, $imageStartX+$xOffset*$column, $this->startY +75 + $row*$yOffset, $imageWidth);


            //desciption
            $pdf->SetFont($this->fontH);
            $pdf->SetXY($this->startX+$xOffset*$column, $this->startY + 185 + $row*$yOffset);
            $lines = $pdf->MultiCellRet($this->width, $this->MED_FONT, $item['description'], 0, 'C');
            $blankSpace = ($lines==1) ? $this->MED_FONT : $this->MED_FONT*2;
            //brand
            $pdf->SetFont($this->fontM);
            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY +185 +$blankSpace + $row*$yOffset);
            $brand = ($item['brand']) ? $item['brand'] : 'PLACE HOLDER';
            $pdf->Cell($this->width, $this->MED_FONT, $brand, 0, 0, 'C');


            //upc
            $pdf->SetFont($this->font);
            $pdf->SetFontSize($this->SMALL_FONT);
            $pdf->SetXY($this->startX + $xOffset*$column, $this->startY +215 + $row*$yOffset);
            $pdf->Cell($this->width, $this->SMALL_FONT, 'UPC: '.$item['upc'], 0,0, 'C');

            //reg price
            $pdf->SetFont($this->fontM);
            $pdf->SetFontSize($this->MED_FONT);
            $pdf->SetXY($this->startX +2 + $xOffset*$column, $this->startY + 225 + $row*$yOffset);
            $pdf->Cell($this->width/2, $this->MED_FONT, sprintf('REG. $%.2f', $info['normal_price']), 0,0, 'L');
            //size
            $pdf->SetX($this->startX -2 + $xOffset*$column + $this->width/2);
            $pdf->Cell($this->width/2, $this->MED_FONT,$item['size'], 0,0, 'R');

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

        $pdf->Output('MemberAlmanac2x8L.pdf', 'I');
        set_time_limit(30);
    }

    protected function getExtraInfo($upc)
    {
        $dbc = \FannieDB::get(\FannieConfig::config('OP_DB'));
        $prep = $dbc->prepare('SELECT * FROM products WHERE upc=?');
        return $dbc->getRow($prep, array($upc));
    }
}

