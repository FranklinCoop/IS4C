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

class CoopDealSales15UP extends \COREPOS\Fannie\Plugin\NCGSignPilot\NCGSignage
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

    protected $width = 1.5;
    protected $height = 3.375;
    protected $startY = .4375;
    protected $startX = .5;
    protected $signMarginY = .125;
    protected $signMarginX = .03125;
    protected $marginTop=0.4375;
    protected $marginLeft=0.5; // same as margin right

    protected function createPDF()
    {
        define('FPDF_FONTPATH',dirname(__FILE__) . '/noauto/fonts/');
        $pdf = new lib\FPDF_Extended('P', 'in', 'Letter');
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
            $flagW = .1;
            $flagX = $flagX - .03;
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
                        $flagX += $flagW+.01;
                        if ($flagCount == 2) {
                            $flagCount = 0;
                            $flagX = $flagX - ($flagW+.01) * 3;
                            $flagY += $flagW+.01;
                        }
                        $flagCount++;
                    }
                }
            }
        } else {
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
                            $pdf->Image($imagePath, $x,$y, $imageHeight, $imageHeight);
                            $x += $imageHeight + .03;
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

            if ($count % 15 == 0) {
                $pdf->AddPage();
                $sign = 0;
            }

            $row = floor($sign / 5);
            $column = $sign % 5;

            

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
            * Unit Price Label (Gill Sans Nova Medium @ 5pt), 
            * Item Price label (Gill Sans Nova Medium @ 5pt)
            */
            $x = $this->startX + $this->signMarginX + ($this->width  *$column);
            $y = $this->startY + $this->signMarginY  + ($this->height *$row);
            ##### Orange Box #####
            $orangeBoxH = 0.45;
	        $orangeBoxW = 0.687;
            $pdf->SetFillColor(255,161,0);
            $pdf->SetDrawColor(255,161,0);
            $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');

            ##### Unit Price Label #####
            $labelFontSize = 5;
            $labelFont = 'GillSansNova-Medium';
            $textHeight = $pdf->getCellHeight($labelFontSize,'in');
            $y += 0.02;
            $pdf->SetXY($x, $y);
            $pdf->SetFont($labelFont, '', $labelFontSize);
            $pdf->Cell($orangeBoxW, $textHeight, 'UNIT PRICE', $showBorders, 1, 'C');
            
            ##### Item Price Label #####
            $remainingWidth = $this->width - (2*$this->signMarginX) - $orangeBoxW; 
            $x += $orangeBoxW;
            $pdf->SetXY($x, $y);
            $pdf->SetFont($labelFont, '', $labelFontSize);
            $pdf->Cell($remainingWidth, $textHeight, 'ITEM PRICE', $showBorders, 1, 'C');
            // reset the draw colors to black and white mostly for trouble shooting if I need to draw borders.
            if ($showBorders == 1) {
                $pdf->SetFillColor(255,255,255);
                $pdf->SetDrawColor(0,0,0);
            }

            /*
            * LINE 2
            * unit_price (Gill Sans Nova Condensed Medium @ 25pt) the dollar sign is 14.57,
            * price (Gill Sans Nova Condensed Semibold @ 27pt) the dollar sign is 15.75
            */
            ##### Normal Unit PRice #####
            $unitPriceStr = $NCGItem['unitPrice'];
            $priceFontSize = 25;
            $dollarFontSize = 14.57;
            $priceFont = 'GillSansNova-CnMedium';
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight;
            
            //We need these values to make the dollar sign smaller and aligned to the price number
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $cellDW = $pdf->GetStringWidth('$');
            $pdf->SetFont($priceFont, '', $priceFontSize);
            $cellPW = $pdf->GetStringWidth($unitPriceStr);
            
            // align the price number x and width offset by the width of the dollar sign text.
            $textHeight = $pdf->getCellHeight($priceFontSize,'in');
            $cellW = $orangeBoxW - $cellDW;
            $pdf->SetXY($x +$cellDW,$y);
            $pdf->Cell($cellW, $textHeight, $unitPriceStr, $showBorders, 1, 'C');
            // place dollar sign in front of the price.
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $pdf->SetXY($x + (($orangeBoxW/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+.05, '$', $showBorders, 1, 'L');

            ##### Tag Price #####
            $tagPriceString = $NCGItem['normalPrice'];
            $priceFontSize = 27;
            $dollarFontSize = 15.75;
            $priceFont = 'GillSansNova-CnMedium';
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
            $textHeight = $pdf->getCellHeight($priceFontSize ,'in');
            $pdf->Cell($cellW, $textHeight, $tagPriceString, $showBorders, 1, 'C');
            // place dollar sign in front of the price.
            $pdf->SetFont($priceFont, '', $dollarFontSize);
            $pdf->SetXY($x + (($remainingWidth/2) -($cellPW/2)) - ($cellDW),$y);
            $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+.05, '$', $showBorders, 1, 'L');
            /*
            * LINE 3
            * Unit_mesure (Gill Sans Nova Medium @ 5pt),
            */
            ##### Tag Unit of Measure #####
            $unitFontSize = 5;
            $unitFont = 'GillSansNova-Medium';
            $unit = $NCGItem['unitOfMeasure'];
            $yManualAdjust = -0.085;
            $unitString = strtoupper("per ".$unit);

            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight + $yManualAdjust;
            $textHeight = $pdf->getCellHeight($unitFontSize,'in');
            $pdf->SetXY($x, $y);
            $pdf->SetFont($unitFont, '', $unitFontSize);
            $pdf->Cell($orangeBoxW, $textHeight, $unitString, $showBorders, 1, 'C');

            /*
            * LINE 4 
            * brand_name (Gill Sans Nova Semibold @ 6.5pt)
            */
            ##### Brand Name #####
            $brandStr = $item['brand'];
            $brandFontSize = 6.5;
            $brandFont = 'GillSansNova-SemiBold';
            $cellW = $this->width - (2*$this->signMarginX);
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight($brandFontSize,'in');
            $pdf->SetFont($brandFont, '', $brandFontSize);
            $pdf->Cell($cellW,$textHeight, $brandStr, $showBorders, 1, 'L');

            /*
            * LINE 5
            * description (Gill Sans Nova Semibold @ 6.5pt)
            */
            ##### Description #####
            $itemName = $NCGItem['description'];
            $descFontSize = 6.5;
            $descFont = 'GillSansNova-SemiBold';
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight($descFontSize,'in');
            $pdf->SetFont($descFont, '', $descFontSize);
            $pdf->Cell($cellW,$textHeight, $itemName, $showBorders, 1, 'L');
            
            /*
            * LINE 6 
            *Itme Size(Gill Sans Nova Medium @ 5pt), Unity Qty(Gill Sans Nova Medium @ 5pt),  
            */
            ##### Item Size #####
            $textString = $NCGItem['unitSize']; //'ITEM SIZE 20 CHARCT - UNIT QTY 10'
            $sizeFontSize = 5;
            $sizeFont = 'GillSansNova-Medium';
            $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $textHeight = $pdf->getCellHeight($sizeFontSize,'in');;

            $pdf->SetFont($sizeFont, '', $sizeFontSize);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');

            /*
            * LINE 7 
            * Department Name (Gill Sans Nova Medium @ 5pt)
            * DeptNo (Gill Sans Nova Medium @ 5pt)
            */
            ##### Department Name #####
            $textString = strtoupper($NCGItem['superDeptName'].' - '.$NCGItem['dept_name']);//'DEPARTMENT 8, Category - 00/00/00'
            $x = $this->startX + $this->signMarginX   + ($this->width  *$column);
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $pdf->Cell($cellW, $textHeight,  $textString, $showBorders, 0, 'L');

            /*
            * LINE 8 
            * Vendor (Gill Sans Nova Medium @ 5pt), 
            * Vendor SKU (Gill Sans Nova Medium @ 5pt)
            */ 
            ##### Vendor/SKU #####
            $textString = strtoupper($NCGItem['vendor'].' - '.$NCGItem['sku']); //'SUPPLIER NAME TWENTY – 0123456789'
            $y += $textHeight;
            $pdf->setXY($x,$y);
            $pdf->Cell($cellW, $textHeight, $textString , $showBorders, 0, 'L');
            
            /*
            * LINE 9
            * Barcode
            * Local Image (Size =  .2”w x .2”h)
            * OG Logo (Size =  .2”w x .2”h)
            */
            ##### Barcode #####
            $y += $textHeight;
            $cellW = $this->width - (2*$this->signMarginX);
            $imageHeight = .2;
            $barcodeW = $cellW - (2*$imageHeight);
            $upc = $NCGItem['upc'];
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
            $this->drawBarcode($upc, $pdf, $x, $y, $args);

            ##### Local & Organic Image #####
            $x += $barcodeW - .07;
            $this->layoutFlags($pdf, $x,$y,$imageHeight*2,$imageHeight, $NCGItem['attribute']);
            
            /*
            * LINE 10
            * saleprice (Gill Sans Nova Condensed Semibold @ 48pts.) $ = 27.98pt
            */
            ##### Sale Price #####
            $priceFontSize = 48;
            $dollarFontSize = 27.98;
            $priceFont = 'GillSansNova-CnSemiBold';
            $x = $this->startX  + ($this->width  *$column);
            $yManualAdjust = 1.25 + 0.88;  //shelf channel height + space between the top of the hanger and text
            $y = $this->startY  + ($this->height *$row) + $yManualAdjust;
            $textHeight = $pdf->getCellHeight($priceFontSize,'in');
            $cellW = $this->width - (2*$this->signMarginX);
            switch ($NCGItem['signPriceType']) {
                case NCGSignage::PRICE_TYPE_BOGO:
                    $imageWidth = 1.25;
                    $imageHeight = .475;
                    $bogoX = $x + ($cellW - $imageWidth)/2;
                    $yManualAdjust = 1.25 + .95;  //shelf channel height + space between the top of the hanger and text
                    $y = $this->startY  + ($this->height *$row) + $yManualAdjust;
                    $imagePath = $FANNIE_ROOT.'modules/plugins2.0/NCGSignPilot/noauto/images/BOGO_Lockup.png';
                    $pdf->Image($imagePath, $bogoX,$y, $imageWidth, $imageHeight);
                    break;
                case NCGSignage::PRICE_TYPE_SPLIT:
                    $textString = $NCGItem['priceDevider'].'/$'.$NCGItem['multiPrice'];
                    $priceFontSize = 48;
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
                    * LINE 11
                    * you saved boxx
                    * you saved text
                    */
                    ##### You Saved Box #####
                    $x += .36;
                    $yManualAdjust = .14;
                    $y += $imageHeight + $yManualAdjust;
                    $pdf->SetXY($x, $y);

                    $yousavedH = .1875;
                    $yousavedW = 0.719;
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
                    $youSavedFontSize = 10;
                    $youSavedFont = 'GillSansNova-CnSemiBold';
                    $pdf->SetFont($youSavedFont, '', $youSavedFontSize);
                    $textHeight = $pdf->getCellHeight($youSavedFontSize ,'in');
                    $textString = $this->calculateSaved($NCGItem['normalPrice'],$NCGItem['salePrice']);
                    $pdf->Cell($yousavedW, $yousavedH, $textString, $showBorders, 1, 'C');
                    /*
                    * LINE 12 
                    * UnitPrice (Gill Sans Nova Condensed Medium @ 25pt),
                    * You Saved Box (Size = 1.125”w x .25”h Color (RGB) = 255/161/0 @ 10%),
                    * You Saved Label (Gill Sans Nova Condensed Semibold @ 10pts).
                    */
                    ##### Sale Dates #####
                    $dateFontSize = 5;
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
                    * LINE 11
                    * 'Unit Price' label,
                    * Orange box
                    */
                    #### Orange Box #####
                    $yManualAdjust = 0;
                    $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
                    $y += $textHeight + $yManualAdjust;
                    $pdf->setXY($x,$y);
                    $pdf->SetFillColor(255,161,0);
                    $pdf->SetDrawColor(255,161,0);
                    $pdf->Rect($x, $y, $orangeBoxW, $orangeBoxH,'DF');
                    // reset the draw colors to black and white mostly for trouble shooting if I need to draw borders.
                    if ($showBorders == 1) {
                        $pdf->SetFillColor(255,255,255);
                        $pdf->SetDrawColor(0,0,0);
                    }
                    ##### Sale Unit Price Label #####
                    $labelFontSize = 5;
                    $labelFont = 'GillSansNova-Medium';
                    $textHeight = $pdf->getCellHeight($labelFontSize ,'in');
                    $y += 0.02;
                    $pdf->setXY($x,$y);
                    $pdf->SetFont($labelFont, '', $labelFontSize );
                    
                    $pdf->Cell($orangeBoxW, $textHeight, 'UNIT PRICE', $showBorders, 1, 'C');

                    /*
                    * LINE 12 
                    * UnitPrice (Gill Sans Nova Condensed Medium @ 25pt),
                    * You Saved Box (Size = 1.125”w x .25”h Color (RGB) = 255/161/0 @ 10%),
                    * You Saved Label (Gill Sans Nova Condensed Semibold @ 10pts).
                    */
                    ##### Sale Unit Price #####
                    $textString = ltrim($NCGItem['saleUnitPrice'], '$ ');
                    $priceFontSize = 25;
                    $dollarFontSize = 14.57;
                    $priceFont = 'GillSansNova-CnMedium';
                    $y += $textHeight;
                    $pdf->SetXY($x,$y);

                    //We need these values to make the dollar sign smaller and aligned to the price number
                    $pdf->SetFont($priceFont, '', $dollarFontSize);
                    $cellDW = $pdf->GetStringWidth('$');
                    $pdf->SetFont($priceFont, '', $priceFontSize);
                    $cellPW = $pdf->GetStringWidth($textString);
                    // align the price number x and width offset by the width of the dollar sign text.
                    $textHeight = $pdf->GetCellHeight($priceFontSize,'in');
                    $cellW = $orangeBoxW - $cellDW;
                    $pdf->SetXY($x + $cellDW,$y);
                    $pdf->Cell($cellW, $textHeight, $textString, $showBorders, 1, 'C');
                    // place dollar sign in front of the price.
                    $pdf->SetFont($priceFont, '', $dollarFontSize);
                    $pdf->SetXY($x + (($orangeBoxW/2) -($cellPW/2)) - ($cellDW),$y);
                    $pdf->Cell($cellDW, $pdf->getCellHeight($dollarFontSize, 'in')+.05, '$', $showBorders, 1, 'L');

                    ##### You Saved Box #####
                    $x += $orangeBoxW +.03;
                    $pdf->SetXY($x, $y);

                    $yousavedH = .1875;
                    $yousavedW = 0.719;
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
                    $youSavedFontSize = 10;
                    $youSavedFont = 'GillSansNova-CnSemiBold';
                    $pdf->SetFont($youSavedFont, '', $youSavedFontSize);
                    $textString = $this->calculateSaved($NCGItem['normalPrice'],$NCGItem['salePrice']);
                    $pdf->Cell($yousavedW, $yousavedH, $textString, $showBorders, 1, 'C');

                    /*
                    * LINE 13
                    * UnitMesure,
                    * Sale Dates (Gill Sans Nova Medium @ 5pts.) ON SALE 00/00/00 – 00/00/00
                    */
                    ##### Unit of Mesure For Sale #####
                    $yManualAdjust = -0.06;
                    $x = $this->startX  + $this->signMarginX  + ($this->width  *$column);
                    $y += $textHeight + $yManualAdjust;
                    $pdf->SetXY($x, $y);
                    $textHeight = $pdf->GetCellHeight($unitFontSize,'in');
                    $pdf->SetFont($unitFont, '',$unitFontSize);
                    $pdf->Cell($orangeBoxW, $textHeight, $unitString, $showBorders, 1, 'C');

                    ##### Sale Dates #####
                    $dateFontSize = 5;
                    $dateFont = 'GillSansNova-Medium';
                    $x += $orangeBoxW +.03;
                    $y += 0-$textHeight-0.01;
                    $textHeight = $pdf->GetCellHeight(5,'in');
                    $startDate = new DateTime($NCGItem['startDate']);
                    $endDate = new DateTime($NCGItem['endDate']);
                    $textString = "ON SALE\n".$startDate->format('m/d/y').' - '.$endDate->format('m/d/y');
                    $pdf->SetFont($dateFont , '', $dateFontSize);
                    $pdf->SetXY($x, $y);
                    $pdf->MultiCell($yousavedW, $textHeight, $textString, $showBorders, 'C',0);
            }
            $count++;
            $sign++;
        }

        $pdf->Output('CoopDealsSales15UP.pdf', 'I');
    }
}

}

