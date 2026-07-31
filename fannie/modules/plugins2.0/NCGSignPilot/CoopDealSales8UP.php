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

    private function layoutFlags($pdf, $flagX, $flagY, $areaW, $areaH, $flags) {
        global $FANNIE_ROOT;
        if (\FannieConfig::factory()->get('FANNIE_COOP_ID') == 'FranklinCoop') {
            $flagW = $areaW;    
            $flagCount = 0;
            foreach ($flags as $flag => $show) {
                $imagePath = '';
                if($show) {
                    switch ($flag) {
                        case 'Local':
                            $imagePath = $FANNIE_ROOT.'src/images/local-V2.png';
                            break;
                        case 'Organic':
                            $imagePath = $FANNIE_ROOT.'src/images/organic-V2.png';
                            break;    
                        case 'Non_GMO':
                            $imagePath = $FANNIE_ROOT.'src/images/non-gmo-V2.png';
                            break;
                        //case 'Gluten Free':
                        //    $imagePath = $FANNIE_ROOT.'src/images/Gluten-Free-V2.png';
                        //    break;
                        case 'cv':
                            $imagePath = $FANNIE_ROOT.'src/images/cv.png';
                            break;
                        case 'glyphosate-free':
                            $imagePath = $FANNIE_ROOT.'src/images/glyphosate-free.png';
                            break;
                        default:
                            # do nothing.
                            break;
                    }
                    if ($imagePath) {
                        $pdf->Image($imagePath,$flagX, $flagY, $flagW,$flagW);
                        $flagY += $flagW+.01;
                        $flagCount++;
                    }
                }
            }
        } else {
            $imageHeight = $areaH;
                foreach ($NCGItem['attribute'] as $flag => $show) {
                $imagePath = '';
                if($show) {
                    switch ($flag) {
                        case 'Local':
                            $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/Local_Bug.jpg';
                            break;
                        case 'Organic':
                            $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/Organic_Bug.jpg';
                            break;    
                        default:
                            # do nothing.
                        break;
                    }
                    if ($imagePath) {
                        $pdf->Image($imagePath, $flagX,$flagY, $areaW/2, $areaH);
                        $flagY += $areaH/2 + .03;
                    }
                }
            }
        }
    

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
            * showBorders is for debugging, set to 0 to remove all cell borders and margin boxes.
            */
            $showBorders = 0;
            if ($showBorders) {
                $pdf->SetDrawColor(0,0,0);
                $pdf->SetFillColor(255,255,255);
                $pdf->Rect($x, $y, $this->width, $this->height,'DF');
                $pdf->Rect($x + $this->signMarginX, $y+ $this->signMarginY, $this->width - $this->signMarginX -$this->signMarginX, $this->height - $this->signMarginY - $this->signMarginX,'DF');
            }
 

            /*
            * TOP LINE
            * Orange Box,
            * Unit Price Label,
            * Item Price label
            * OGlogo (Size = .3475"w x .3475"h)
            */
            $x = $this->startX + $this->signMarginX + ($this->width  *$column);
            $y = $this->startY + $this->signMarginY  + ($this->height *$row);
            // Orange Box
            $orangeBoxH = 0.52;
	        $orangeBoxW = 0.75;
            $pdf->SetFillColor(255,161,0);
            $pdf->SetDrawColor(255,161,0);
            $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');

            ##### Unit Price Label #####
            $labelFontSize = 6;
            $labelFont = 'GillSansNova-Medium';
            $textHeight = $pdf->getCellHeight($labelFontSize,'in'); //$textHeight = .12;
            $y += .02;
            $pdf->SetXY($x, $y);
            $pdf->SetFont($labelFont, '', $labelFontSize);
            $pdf->Cell($orangeBoxW, $textHeight, 'UNIT PRICE', $showBorders, 1, 'C');
            ##### Item Price Label #####
            
            $imageHeight = .3475;
            $imageWidth = .3475;
            if (\FannieConfig::factory()->get('FANNIE_COOP_ID') == 'FranklinCoop') {
                $imageHeight = $imageHeight/5;
                $imageWidth = $imageWidth/5;
            }
            $remainingWidth = $this->width - (2*$this->signMarginX) - $orangeBoxW - $imageWidth; 
            $x += $orangeBoxW;
            $pdf->SetXY($x, $y);
            $pdf->SetFont($labelFont, '', $labelFontSize);
            $pdf->Cell($remainingWidth, $textHeight, 'ITEM PRICE', $showBorders, 1, 'C');
            ##### Local & Organic Images #####
            $x += $remainingWidth;
            $imageY = $y;
            $this->layoutFlags($pdf, $x,$y,$imageWidth*2,$imageHeight, $NCGItem['attribute']);

            // reset the draw colors to black and white mostly for trouble shooting if I need to draw borders.
            if ($showBorders == 1) {
                $pdf->SetFillColor(255,255,255);
                $pdf->SetDrawColor(0,0,0);
            }

            /*
            * LINE 2
            * unit_price (Gill Sans Nova Condensed Medium @ 25pt) the dollar sign is 14.57,
            * price (Gill Sans Nova Condensed Semibold @ 38pt) the dollar sign is 22.15
            */
            $unitPriceStr = $NCGItem['unitPrice'];
            $unitPriceFontSize = 25;
            $dollarFontSize = 14.57;
            $priceFont = 'GillSansNova-CnMedium';
            $yManualOffset = .06;
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight;
            
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont($priceFont, '', $unitPriceFontSize);
            $cellPW = $pdf->GetStringWidth($unitPriceStr);
            
            $textHeight = $pdf->getCellHeight($unitPriceFontSize,'in');
            $cellW = $orangeBoxW - $cellDW;
            $pdf->SetXY($x + $cellDW,$y);
            $pdf->Cell($cellW, $textHeight, $unitPriceStr, $showBorders, 1, 'C');

            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $pdf->SetXY($x + (($orangeBoxW/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+$yManualOffset, '$', $showBorders, 1, 'L');

            ##### Tag Price #####
            $tagPriceString = $NCGItem['normalPrice'];
            $priceFontSize = 38;
            $dollarFontSize = 22.15;
            $priceFont = 'GillSansNova-CnMedium';
            $yManualOffset = .06;
            $x += $orangeBoxW;
            //We need these widths to make the dollar sign smaller and aligned to the price number
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont($priceFont, '', $priceFontSize);
            $cellPW = $pdf->GetStringWidth($tagPriceString);
            // align the price number x and width offset by the width of the dollar sign text.
            $pdf->SetFont($priceFont, '', $priceFontSize);
            $cellW = $remainingWidth - $cellDW;
            $pdf->SetXY($x + $cellDW,$y);
            $textHeight = $pdf->getCellHeight($priceFontSize ,'in');;
            $pdf->Cell($cellW, $textHeight, $tagPriceString, $showBorders, 1, 'C');
            // place dollar sign in front of the price.
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $pdf->SetXY($x + (($remainingWidth/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+$yManualOffset, '$', $showBorders, 1, 'L');
            /*
            * LINE 3
            * Unit_mesure (Gill Sans Nova Medium) @ 6pt,
            * 
            */
            ##### Tag Unit of Measure #####
            $unitFontSize = 6;
            $unitFont = 'GillSansNova-Medium';
            $unit = $NCGItem['unitOfMeasure'];
            $yManualAdjust = -0.02;
            $unitString = strtoupper("per ".$unit);
            
            $textHeight = $pdf->getCellHeight($unitPriceFontSize,'in');
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight + $yManualAdjust;

            $textHeight = $pdf->getCellHeight($unitFontSize,'in');
            $pdf->SetXY($x, $y);
            $pdf->SetFont($unitFont, '',  $unitFontSize);
            $pdf->Cell($orangeBoxW, $textHeight, $unitString, $showBorders, 1, 'C');

            /*
            * LINE 4 brand_name Gill Sans Nova Semibold @ 8.5pt
            */
            ##### Brand Name #####
            $brandStr = $NCGItem['brand'];
            $brandFontSize = 8.5;
            $brandFont = 'GillSansNova-SemiBold';
            $cellW = $this->width - (2*$this->signMarginX)- $imageWidth;
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight($brandFontSize,'in');
            $pdf->SetFont($brandFont, '', $brandFontSize);
            $pdf->Cell($cellW,$textHeight, $pdf->TruncateToCell($brandStr, $cellW, ''), $showBorders, 1, 'L');

            /*
            * LINE 5 description
            */
            ##### Description #####
            $itemName = $NCGItem['description'];
            $descFontSize = 8.5;
            $descFont = 'GillSansNova-SemiBold';
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight($descFontSize,'in');
            $pdf->SetFont($descFont, '', $descFontSize);

            $itemName = $pdf->TruncateToCell($itemName, $cellW, '');
            $pdf->Cell($cellW,$textHeight, $itemName, $showBorders, 1, 'L');
            
            /*
            * LINE 6 Itme Size(Gill Sans Nova Medium @ 5pt), Unity Qty(Gill Sans Nova Medium @ 5pt), 
            * Barcode Image
            */
            ##### Item Size #####
            $textString = strtoupper('ITEM SIZE: '.$NCGItem['unitSize']); //'ITEM SIZE 20 CHARCT - UNIT QTY 10'
            $barcodeW = 1;
            $sizeFontSize = 5;
            $sizeFont = 'GillSansNova-Medium';
            $cellW = $this->width - (2*$this->signMarginX) - $barcodeW;
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight +.01;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight(5,'in');;

            $pdf->SetFont('GillSansNova-Medium', '', 5);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');
            ##### Barcode/UPC #####
            $upc = $NCGItem['upc'];
            $pdf->SetDrawColor(0,0,0);
            $pdf->SetFillColor(0,0,0);
            $args = array(
                'height'=>0.18, 
                'width'=>$barcodeW/95,
                'valign' => 'B',
                'align'=>'C',
                'textheight'=> $textHeight,
                'font'=>'GillSansNova-Medium',
                'fontsize'=> 5);
            $this->drawBarcode($upc, $pdf, $x + $cellW, $y, $args);
            
            /*
            * LINE 7
            * Department Name(Gill Sans Nova Medium @ 5pt), 
            * DeptNo(Gill Sans Nova Medium @ 5pt)
            */
            ##### Department Name/Subdepartment #####
            $textString = strtoupper($NCGItem['superDeptName'].' - '.$NCGItem['dept_name']); //'DEPARTMENT 8, Category - 00/00/00'
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textString = $pdf->TruncateToCell($textString, $cellW, '');
            $pdf->Cell($cellW, $textHeight,  $textString, $showBorders, 0, 'L');

            /*
            * LINE 8 
            * Vendor,
            * Vendor SKU
            */ 
            ##### Vendor/SKU #####
            $textString = strtoupper($NCGItem['vendor'].' - '.$NCGItem['sku']); //'SUPPLIER NAME TWENTY – 0123456789'
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');

            /*
            * LINE 9 
            * saleprice (Gill Sans Nova Semibold @ 60pt) $ = 34.98pt
            * OR BOGO LOGO (BOGO_Lockup.png 1.62" x. 62")
            */
            ##### Sale Price #####
            $priceFontSize = 60;
            $dollarFontSize = 34.98;
            $priceFont = 'GillSansNova-CnSemiBold';
            $x = $this->startX + $this->signMarginX + ($this->width  *$column);
            $yManualAdjust = 1.25 + .95;  //shelf channel height + space between the top of the hanger and text
            $y = $this->startY  + ($this->height *$row) + $yManualAdjust;
            $textHeight = $pdf->getCellHeight($priceFontSize,'in');
            $cellW = $this->width - (2*$this->signMarginX);
            switch ($NCGItem['signPriceType']) {
                case NCGSignage::PRICE_TYPE_BOGO:
                    ##### BOGO Image #####
                    $imageWidth = 1.62;
                    $imageHeight = .62;
                    $bogoX = $x + ($cellW - $imageWidth)/2;
                    $yManualAdjust = 1.25 + 1.05;  //shelf channel height + space between the top of the hanger and text
                    $y = $this->startY  + ($this->height *$row) + $yManualAdjust;
                    $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/BOGO_Lockup.png';
                    $pdf->Image($imagePath, $bogoX,$y, $imageWidth, $imageHeight);
                    break;
                case NCGSignage::PRICE_TYPE_SPLIT:
                    $textString = $NCGItem['priceDevider'].'/$'.$NCGItem['multiPrice'];
                    $priceFontSize = 60;
                    $pdf->SetFont($priceFont, '', $priceFontSize);
                    $pdf->setXY($x ,$y);
                    $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'C');
                    break;
                default:
                    ##### Regular Sale Price
                    $textString = $NCGItem['salePrice'];
                    //We need these widths to make the dollar sign smaller and aligned to the price number
                    $pdf->SetFont($priceFont, '', $dollarFontSize);
                    $cellDW = $pdf->GetStringWidth('$');
                    $pdf->SetFont($priceFont, '', $priceFontSize);
                    $cellPW = $pdf->GetStringWidth($textString);
                    // align the price number x and width offset by the width of the dollar sign text.
                    $pdf->setXY($x +$cellDW ,$y);
                    $cellW = $this->width - (2*$this->signMarginX) - $cellDW;
                    $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'C');
                    // align the dollar sign to the left of the price.
                    $pdf->SetFont($priceFont, '', $dollarFontSize);
                    $pdf->SetXY(($x+($cellW - $cellPW)/2),$y);
                    $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+.1, '$', $showBorders, 1, 'C');
                    break;
            }

            switch ($NCGItem['signPriceType']) {
                case NCGSignage::PRICE_TYPE_BOGO:
                    /*
                    * LINE 10 
                    * You Saved Box (Size = 1.125”w x .25”h Color (RGB) = 255/161/0 @ 10%),
                    * You Saved Label (Gill Sans Nova Condensed Semibold @ 15pt)
                    */
                    $yousavedH = .25;
                    $yousavedW = 1.125;
                    $x = $this->startX + $this->signMarginX + ($this->width  *$column);
                    $x += ($cellW - $yousavedW)/2; //center the box
                    $yManualAdjust = .24;
                    $y += $imageHeight + $yManualAdjust;
                    $pdf->SetXY($x, $y);

                    $pdf->SetFillColor(255,161,0);
                    $pdf->SetDrawColor(255,161,0);
                    $pdf->SetAlpha(0.1);
                    $pdf->Rect($x, $y, $yousavedW, $yousavedH,'DF');
                    $pdf->SetAlpha(1);                    
                    // reset the draw colors to black and white mostly for trouble shooting if I need to draw borders.
                    if ($showBorders == 1) {
                        $pdf->SetFillColor(255,255,255);
                        $pdf->SetDrawColor(0,0,0);
                    }
                    ##### You Saved Value #####
                    $youSavedFontSize = 15;
                    $youSavedFont = 'GillSansNova-CnSemiBold';
                    $pdf->SetFont($youSavedFont, '', $youSavedFontSize);
                    $textHeight = $pdf->getCellHeight($youSavedFontSize ,'in');
                    $textString = $this->calculateSaved($NCGItem['normalPrice'],$NCGItem['salePrice']);
                    $pdf->Cell($yousavedW, $yousavedH, $textString, $showBorders, 1, 'C');

                    /*
                    * LINE 11 
                    * Sale Dates (Gill Sans Nova Medium @ 5.5pt) ON SALE 00/00/00 – 00/00/00
                    */
                                        ##### Sale Dates #####
                    $dateFontSize = 5.5;
                    $dateFont = 'GillSansNova-Medium';
                    $yManualAdjust = .06;
                    $y += + $textHeight+$yManualAdjust;
                    $textHeight = $pdf->GetCellHeight(5,'in');
                    $startDate = new DateTime($NCGItem['startDate']);
                    $endDate = new DateTime($NCGItem['endDate']);
                    $textString = "ON SALE\n".$startDate->format('m/d/y').' - '.$endDate->format('m/d/y');
                    $pdf->SetFont($dateFont , '', $dateFontSize);
                    $pdf->SetXY($x, $y);
                    $pdf->MultiCell($yousavedW, $textHeight, $textString, $showBorders, 'C',0);
                    break;
                default:
                    /*
                    * LINE 10
                    * 'Unit Price' label,
                    * Orange box
                    */
                    #### Orange Box #####
                    $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
                    $y += $textHeight;
                    $pdf->setXY($x,$y);
                    $pdf->SetFillColor(255,161,0);
                    $pdf->SetDrawColor(255,161,0);
                    $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');
                    ##### Sale Unit Price Label #####
                    $labelFontSize = 6;
                    $labelFont = 'GillSansNova-Medium';
                    $textHeight = $pdf->getCellHeight($labelFontSize ,'in');
                    $yManualAdjust = .02;
                    $y += $yManualAdjust;
                    $pdf->setXY($x,$y);
                    $pdf->SetFont($labelFont, '', $labelFontSize );
                    
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
                    $pdf->Cell($orangeBoxW, $textHeight, $unitString, $showBorders, 1, 'C');

                    $textHeight = $pdf->GetCellHeight(5.5,'in');
                    $startDate = new DateTime($NCGItem['startDate']);
                    $endDate = new DateTime($NCGItem['endDate']);
                    $textString = 'ON SALE '.$startDate->format('m/d/y').' - '.$endDate->format('m/d/y');
                    $pdf->SetFont('GillSansNova-Medium', '', 5.5);
                    $x += 1.25;
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($yousavedW, $textHeight, $textString, $showBorders, 1, 'C');
                    break;
            }

            $count++;
            $sign++;
        }

        $pdf->Output('CoopDealsSales8UP.pdf', 'I');
    }
}

}

