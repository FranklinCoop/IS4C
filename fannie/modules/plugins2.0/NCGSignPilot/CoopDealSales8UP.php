<?php
/*******************************************************************************

    Copyright 2026 Franklin Community Coop

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

namespace COREPOS\Fannie\Plugin\NCGSignPilot  {
use DateTime;

class CoopDealSales8UP extends \COREPOS\Fannie\Plugin\NCGSignPilot\NCGSignage
{

    protected $BIG_FONT = 85;
    protected $MED_FONT = 21;
    protected $SMALL_FONT = 17;
    protected $SMALLER_FONT = 16;
    protected $SMALLEST_FONT = 11;
    protected $DATE_FONT = 8;
    protected $BOGO_BIG_FONT = 80;
    protected $BOGO_MED_FONT = 23;

    protected $font = 'GillSansNova-Medium';
    protected $alt_font = 'GillSansNova-CnMedium';

    protected $width = 2.5;
    protected $height = 3.8438;
    protected $startY = .4063;
    protected $startX = .5;
    protected $signMarginY = .125;
    protected $signMarginX = .0625;
    protected $marginTop=0.4063;
    protected $marginLeft=0.50; // same as margin right

    protected function createPDF()
    {
        define('FPDF_FONTPATH',dirname(__FILE__) . '/noauto/fonts/');
        $pdf = new lib\FPDF_Extended('L', 'in', 'Letter');
        $pdf->AddFont('GillSansNova-CnMedium','','GillSansNova-CnMedium.php');
        $pdf->AddFont('GillSansNova-CnSemiBold','','GillSansNova-CnSemiBold.php');
        $pdf->AddFont('GillSansNova-Medium','','GillSansNova-Medium.php');
        $pdf->AddFont('GillSansNova-SemiBold','','GillSansNova-SemiBold.php');
        $pdf->SetMargins(0.5, 0.4063, 0.5);
        $pdf->SetAutoPageBreak(false);
        //$pdf = $this->loadPluginFonts($pdf);
        $pdf->SetFont($this->font, '', 16);

        return $pdf;
    }

    public function drawPDF()
    {
        global $FANNIE_ROOT;
        $pdf = $this->createPDF();

        $data = $this->loadItems();
        $count = 0;
        $sign = 0;
        foreach ($data as $item) {

            if ($count % 8 == 0) {
                $pdf->AddPage();
                $sign = 0;
            }

            $row = floor($sign / 4);
            $column = $sign % 4;

            

            $x = $this->startX  + ($this->width  *$column);
            $y = $this->startY  + ($this->height *$row);

            $NCGItem = $this->getNCGSignData($item);
            /*
            * The next four lines are drawing boxes for layout troubleshooting they should be removed or commented out.
            */
            //$pdf->SetDrawColor(0,0,0);
            //$pdf->SetFillColor(255,255,255);
            //$pdf->Rect($x, $y, $this->width, $this->height,'DF');
            //$pdf->Rect($x + $this->signMarginX, $y+ $this->signMarginY, $this->width - $this->signMarginX -$this->signMarginX, $this->height - $this->signMarginY - $this->signMarginX,'DF');
            $showBorders = 0; // This is also for layout troubleshooting, set to zero in production.

            /*
            * TOP LINE
            * Orange Box,
            * Unit Price Label,
            * Item Price label
            */
            $x = $this->startX + $this->signMarginX + ($this->width  *$column);
            $y = $this->startY + $this->signMarginY  + ($this->height *$row);
            // Orange Box
            $orangeBoxH = 0.52;
	        $orangeBoxW = 0.75;
            $pdf->SetFillColor(255,161,0);
            $pdf->SetDrawColor(255,161,0);
            $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');


            $textHeight = .12;
            $pdf->SetXY($x, $y);
            $pdf->SetFont('GillSansNova-Medium', '', 6);
            $pdf->Cell($orangeBoxW, $textHeight, 'UNIT PRICE', $showBorders, 1, 'C');
            
            $x += $orangeBoxW;
            $pdf->SetXY($x, $y);
            $pdf->SetFont('GillSansNova-Medium', '', 6);
            $pdf->Cell(1.225, $textHeight, 'ITEM PRICE', $showBorders, 1, 'C');

            $x += 1.225;
            $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/Local_Bug.jpg';
            $imageHeight = .3475;
            $pdf->Image($imagePath, $x,$y, $imageHeight, $imageHeight);

            /*
            * LINE 2
            * unit_price (Gill Sans Nova Condensed Medium @ 25pt) the dollar sign is 14.57,
            * price (Gill Sans Nova Condensed Semibold @ 38pt) the dollar sign is 22.15
            */
            //$num_unit = $item['pricePerUnit'];
            $num_unit = $NCGItem['unitPrice'];

            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += 0.0833333;
            
            $pdf->SetFont('GillSansNova-CnMedium', '', 14.57);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont('GillSansNova-CnMedium', '', 25);
            $cellPW = $pdf->GetStringWidth($num_unit);
            
            $textHeight = 0.375;
            $cellW = $orangeBoxW - $cellDW;
            $pdf->SetXY($x + $cellDW,$y);
            $pdf->Cell($cellW, $textHeight, $num_unit, $showBorders, 1, 'C');

            $pdf->SetFont('GillSansNova-CnMedium', '', 14.57);
            $pdf->SetXY($x + ((0.75/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, 0.275, '$', $showBorders, 1, 'L');

            ## Tag Price
            $price = $NCGItem['normalPrice'];
            $x += $orangeBoxW;
            
            $pdf->SetFont('GillSansNova-CnMedium', '', 22.15);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont('GillSansNova-CnMedium', '', 38);
            $cellPW = $pdf->GetStringWidth($price);


            $pdf->SetFont('GillSansNova-CnMedium', '', 38);
            $cellW = 1.225 - $cellDW;
            $pdf->SetXY($x + $cellDW,$y);
            $textHeight = 0.555556;
            $pdf->Cell($cellW, $textHeight, $price, $showBorders, 1, 'C');

            $pdf->SetFont('GillSansNova-CnMedium', '', 22.15);
            $pdf->SetXY($x + ((1.25/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, 0.375, '$', $showBorders, 1, 'L');
            /*
            * LINE 3
            * Unit_mesure (Gill Sans Nova Medium) @ 6pt,
            * OGlogo (Size = .3475"w x .3475"h)
            */
            $unit = $NCGItem['unitOfMesure'];
            
            $alpha_unit = "per ".$unit;

            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += 0.32;

            $textHeight = .12;
            $pdf->SetXY($x, $y);
            $pdf->SetFont('GillSansNova-Medium', '', 6);
            $pdf->Cell($orangeBoxW, $textHeight, $alpha_unit, $showBorders, 1, 'C');
            

            $x += 1.225 + $orangeBoxW;
            $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/Organic_Bug.jpg';
            $imageHeight = .3475;
            $pdf->Image($imagePath, $x,$y, $imageHeight, $imageHeight);

            /*
            * LINE 4 brand_name Gill Sans Nova Semibold @ 8.5pt
            */

            $brand = $item['brand'];
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight +.01;
            $pdf->setXY($x,$y);
            $textHeight = 0.118056;
            $pdf->SetFont('GillSansNova-SemiBold', '', 8.5);
            $pdf->Cell($this->width - $imageHeight*2,$textHeight, $brand, $showBorders, 1, 'L');

            /*
            * LINE 5 description
            */
            $itemName = $NCGItem['description'];
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight+.01;
            $pdf->setXY($x,$y);
            $textHeight = 0.118056;
            $pdf->SetFont('GillSansNova-SemiBold', '', 8.5);
            $pdf->Cell($this->width - $imageHeight*2,$textHeight, $itemName, $showBorders, 1, 'L');
            
            /*
            * LINE 6 Itme Size(Gill Sans Nova Medium @ 5pt), Unity Qty(Gill Sans Nova Medium @ 5pt), 
            * Barcode Image
            */
            $textString = $NCGItem['unitSize']; //'ITEM SIZE 20 CHARCT - UNIT QTY 10'
            $barcodeW = 1.06;
            $cellW = $this->width - $barcodeW;
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight +.01;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight(5,'in');;

            $pdf->SetFont('GillSansNova-Medium', '', 5);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');

            $upc = $item['upc'];
            $pdf->SetDrawColor(0,0,0);
            $pdf->SetFillColor(0,0,0);
            $args = array(
                'height'=>0.18, 
                'width'=>$barcodeW/108,
                'valign' => 'B',
                'align'=>'C',
                'textheight'=> $textHeight,
                'font'=>'GillSansNova-Medium',
                'fontsize'=> 5);
            $this->drawBarcode($upc, $pdf, $x + $cellW, $y, $args);

            /*
            * LINE 7 Department Name(Gill Sans Nova Medium @ 5pt), DeptNo(Gill Sans Nova Medium @ 5pt)
            */
            $textString = $NCGItem['dept_name']; //'DEPARTMENT 8, Category - 00/00/00'
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight +.01;
            $pdf->setXY($x,$y);

            $pdf->Cell($cellW, $textHeight,  $textString, $showBorders, 0, 'L');


            /*
            * LINE 8 Vendor, Vendor SKU
            */ 
            //$x = $this->startX  + ($this->width  *$column);
            $textString = $NCGItem['vendor'].' - '.$NCGItem['sku']; //'SUPPLIER NAME TWENTY – 0123456789'
            $y += $textHeight +.01;
            $pdf->setXY($x,$y);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');
            /*
            * LINE 9 saleprice (Gill Sans Nova Semibold @ 60pt) $ = 34.98pt
            */
            $textString = $NCGItem['salePrice'];
            $y += $textHeight +1.20;
            
            $pdf->SetFont('GillSansNova-SemiBold', '', 34.98);
            $cellDW = $pdf->GetStringWidth('$');
            
            $pdf->SetFont('GillSansNova-SemiBold', '', 60);
            $cellPW = $pdf->GetStringWidth('00.00');

            $pdf->setXY($x + $cellDW ,$y);

            $textHeight = $pdf->getCellHeight(60,'in');
            $cellW = $this->width;
            //$pdf->SetCharSpacing(-0.0125);
            $pdf->Cell($cellPW, $textHeight, $textString , $showBorders, 0, 'L');
            //$pdf->SetCharSpacing(0);

            $pdf->SetFont('GillSansNova-SemiBold', '', 34.98);

            $pdf->SetXY($x + (2.25 -$cellPW) - ($cellDW),$y + 0.09);
            $pdf->Cell($cellDW, 0.375, '$', $showBorders, 1, 'L');
            /*
            * LINE 10 'Unit Price' label, Orange box
            */
            $y += $textHeight + .085;
            $pdf->setXY($x,$y);
            $pdf->SetFillColor(255,161,0);
            $pdf->SetDrawColor(255,161,0);
            $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');

            $textHeight = .12;
            $pdf->setXY($x,$y);
            $pdf->SetFont('GillSansNova-Medium', '', 6);
            
            $pdf->Cell($orangeBoxW, $textHeight, 'UNIT PRICE', $showBorders, 1, 'C');

            /*
            * LINE 11 
            * UnitPrice (Gill Sans Nova Condensed Medium @ 25pt),
            * You Saved Box (Size = 1.125”w x .25”h Color (RGB) = 255/161/0 @ 10%),
            * You Saved Label (Gill Sans Nova Condensed Semibold @ 15pt)
            */

            $textString = ltrim($NCGItem['saleUnitPrice'], '$ ');
            $y += $textHeight;
            $pdf->SetXY($x,$y);
                        //$y += 0.0833333;
            
            $pdf->SetFont('GillSansNova-CnMedium', '', 14.57);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont('GillSansNova-CnMedium', '', 25);
            $cellPW = $pdf->GetStringWidth($textString);
            ///Sale Unit Price
            $textHeight = 0.375;// $pdf->GetCellHeight(25,'in');
            $cellW = $orangeBoxW - $cellDW;
            $pdf->SetXY($x + $cellDW,$y);
            $pdf->Cell($cellW, $textHeight, $textString, $showBorders, 1, 'C');
            //Dollar Sign
            $pdf->SetFont('GillSansNova-CnMedium', '', 14.57);
            $pdf->SetXY($x + (($orangeBoxW/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, 0.275, '$', $showBorders, 1, 'L');

            $x += 1.25;
            $pdf->SetXY($x, $y);

            $pdf->SetFont('GillSansNova-CnSemiBold', '', 15);
            
            $textString = $this->calculateSaved($NCGItem['normalPrice'],$NCGItem['salePrice']);
            $yousavedH = .25;
            $yousavedW = 1.125;
            $pdf->Cell($yousavedW, $yousavedH, $textString, $showBorders, 1, 'C');
            $pdf->SetFillColor(255,161,0);
            $pdf->SetDrawColor(255,161,0);
            $pdf->SetAlpha(0.1);
            $pdf->Rect($x, $y, $yousavedW, $yousavedH,'DF');
            $pdf->SetAlpha(1);


            /*
            * LINE 12
            * UnitMesure,
            * Sale Dates (Gill Sans Nova Medium @ 5.5pt) ON SALE 00/00/00 – 00/00/00
            */
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += 0.32;

            $textHeight = $pdf->GetCellHeight(6,'in');
            $pdf->SetXY($x, $y);
            $pdf->SetFont('GillSansNova-Medium', '', 6);
            $pdf->Cell($orangeBoxW, $textHeight, $alpha_unit, $showBorders, 1, 'C');

            $textHeight = $pdf->GetCellHeight(5.5,'in');
            $startDate = new DateTime($NCGItem['startDate']);
            $endDate = new DateTime($NCGItem['endDate']);
            $textString = 'ON SALE '.$NCGItem['st'].$startDate->format('m/d/y').' - '.$endDate->format('m/d/y');
            $pdf->SetFont('GillSansNova-Medium', '', 5.5);
            $x += 1.25;
            $pdf->SetXY($x, $y);
            $pdf->Cell($yousavedW, $textHeight, $textString, $showBorders, 1, 'C');

            $count++;
            $sign++;
        }

        $pdf->Output('CoopDealsSales8UP.pdf', 'I');
    }
}

}

