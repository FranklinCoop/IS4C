<?php
/*******************************************************************************

    Copyright 2021 Franklin Community co-op

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

class WellnessSpecial9upP extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 29.37;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $fontHead = 'ModestoIOpenPrimary';
    protected $font = 'ModestoOpenInlineFill';
    protected $fontH = 'ModestoOpenInlineFillH';
    protected $fontM = 'ModestoOpenInlineFillM';

    protected $width = 153;
    protected $height = 198;
    protected $startX = 36;
    protected $startY = 63;
    protected $borderLineWidth=4.25;
    //protected $outerBorderWidth=5;
    //protected $innderBorderWidth=0;
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
        $pdf->AddFont('ModestoOpen-InlineFill', '', 'ModestoOpen-InlineFill.php');
        $pdf->AddFont('ModestoCond-Bold', '', 'ModestoCond-Bold.php');
        $pdf->AddFont('ModesExp', '', 'ModesExp.php');
        $pdf->AddFont('ModesReg', '', 'ModesReg.php');
        $pdf->AddFont('ModesMedTex', '', 'ModesMedTex.php');
        $pdf->AddFont('ModesLigTex', '', 'ModesLigTex.php');
        $pdf->AddFont('ModesBolTex', '', 'ModesBolTex.php');
        $pdf->AddFont('ModestoText-LightItalic', '', 'ModestoText-LightItalic.php');
        
        $pdf->SetMargins($this->startX, $this->startY, $this->startX);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont($this->font, '', 16);

        $data = $this->loadItems();
        

        $count = 0;
        $sign = 0;
        //fpdf draws the border rect is outside of the width this accounts for the extra size in placemnt.
        //the fallowing two methods account for porder size when seting starting postions.
        $xOffset = $this->width + $this->borderLineWidth +$this->startX ;
        $yOffset = $this->height + $this->borderLineWidth +40.5 ;
        foreach ($data as $item) {
            // number of signs per page.
            if ($count % 9 === 0) {
                $pdf->AddPage();
                $sign = 0;
                //$file = dirname(__FILE__) . '/../../../item/images/MemberAlminac2x4BG.jpg';
                //if (file_exists($file) && is_file($file)) {
                //    $pdf->Image($file, 45, 55, 700);
                //}
            }


            $row = floor($sign / 3);
            $column = $sign % 3;
            $info = $this->getExtraInfo($item['upc']);
            $flags = $this->getProdFlags($item['upc']);
            $y=$this->startY;
            $x=$this->startX;
            $textWidth = $this->width - ($this->width*.4) - 4;

        /*** draw gradient */
            //set colors for gradients (r,g,b) or (grey 0-255)
            $red=array(94,114,136);
            $blue=array(199,229,230);

            //set the coordinates x1,y1,x2,y2 of the gradient (see linear_gradient_coords.jpg)
            $coords=array(0,1,0,0);

            //paint a linear gradient
            $pdf->LinearGradient(($xOffset*$column)+$this->startX,($yOffset*$row)+$this->startY,$this->width,$this->height,$red,$blue,$coords);

        /***   draw border   */
            $pdf->SetDrawColor(38, 55, 88);
            $pdf->SetLineWidth($this->borderLineWidth);
            $w = $this->width - $this->borderLineWidth/2;
            $h = $this->height - $this->borderLineWidth/2;
            $pdf->Rect($this->startX + $xOffset*$column, $this->startY+$yOffset*$row, $w, $h);
            

            /*** Top SwoopWellness Special .43 inch 31pt */
            $pdf->SetLineWidth(1);
            $w = 108;
            $h = 36;
            $start_x = ($xOffset*$column) + $this->startX+($this->borderLineWidth/2)-0.5;
            $start_y = ($yOffset*$row) + $this->startY-($this->borderLineWidth/2)-0.5 +$h;
            $end_x = ($xOffset*$column) + $this->startX + $this->borderLineWidth +$w;
            $end_y = ($yOffset*$row) +$this->startY+($this->borderLineWidth/2);
            $eq_x1 = $start_x+10;
            $eq_y1 = $start_y-10;
            $eq_x2 = $end_x-60;
            $eq_y2 = $end_y+40;
            $pdf->bottomCurveRect($start_x,$start_y,$eq_x1,$eq_y1,$eq_x2,$eq_y2,$end_x,$end_y,$w, $h,'FD', null, array(27,51,88));
            

        /***  Bike Logo Image  */
            //image is displayed at 49.5pts by 31.5pts
            $imagePath = dirname(__FILE__) . '/../../../item/images/bike.png';
            $imageWidth = 49.5;
            $x = $this->startX + ($this->width/2)-($imageWidth/2)+$xOffset*$column;
            $y = $this->height + $this->startY + $row*$yOffset - $this->borderLineWidth/2 -32.5;
            //$imageStartX = $this->startX + $this->width/2-$imageWidth/2;
            $pdf->Image($imagePath, $x, $y, $imageWidth);
            
        /***   WELLNESS SPECIAL */
            $fontSize = 12;
            $pdf->SetFont('ModestoOpenInlineFillH', '', $fontSize);
            $x = $this->startX + $xOffset*$column;
            $y = $this->startY + $yOffset*$row + $this->borderLineWidth/2;
            $w += - $this->borderLineWidth*2 -25;

            $pdf->SetXY($x-.5,$y+.5);
            $pdf->SetTextColor(0,0,0);
            $pdf->CellFit($w, $fontSize, 'WELLNESS',0,0,'L');
            $pdf->SetXY($x,$y);
            $pdf->SetTextColor(119, 184, 181);
            $pdf->CellFit($w, $fontSize, 'WELLNESS',0,0,'L');
            $pdf->SetXY($x+0.5,$y);
            $pdf->CellFit($w, $fontSize, 'WELLNESS',0,0,'L');
           
            $x += 0.5;
            $y += $fontSize;
            $w += -20;
            $pdf->SetXY($x-.5,$y+.5);
            $pdf->SetTextColor(0,0,0);
            $pdf->CellFit($w, $fontSize, 'SPECIAL',0,0,'L');
            $pdf->SetXY($x,$y);
            $pdf->SetTextColor(205, 228, 236);
            $pdf->CellFit($w, $fontSize, 'SPECIAL',0,0,'L');
            $pdf->SetXY($x+0.5,$y);
            $pdf->CellFit($w, $fontSize, 'SPECIAL',0,0,'L');

        /***   % Off    */
        /***   Fill with orgin text or auto calculated % off if over 10% */
            $origin = $item['originName'];
            $off = '';
            if ($origin == '') {
                $percentDisc = (1 - $item['normal_price']/$info['normal_price'])*100;
                $origin = ($percentDisc >= 10) ? sprintf('%d%%', $percentDisc) : '' ;
                $off = ($percentDisc >= 10) ? 'off' : '';
                //$origin = $percentDisc;
            }
            
            $x += 108;
            $y = $this->startY + $row*$yOffset +9 - $this->borderLineWidth;
            $w = $this->width - 108;
            $fontSize = 18;
            $pdf->SetFont('ModesReg', '', $fontSize);
            $pdf->SetTextColor(254,254,254);
            $pdf->SetXY($x,$y);
            $pdf->Cell($w, $fontSize, $origin,0,0,'L');

            $pdf->SetTextColor(119, 184, 181);
            $x += 9;
            $fontSize =12.35;
            $y += $fontSize+2;
            $fontSize =12.35;
            $pdf->setFontSize($fontSize);
            $pdf->SetXY($x,$y);
            $pdf->Cell($w, $fontSize, $off,0,0,'L');


        /***   Brand */
            $brand = strtoupper($item['brand']);
            $pdf->SetTextColor(0, 0, 0);
            $x = $this->startX + $xOffset*$column + $this->borderLineWidth/2;
            $y = $this->startY + $yOffset*$row + 40;
            $fontSize = 9;
            $w = $this->width - $this->borderLineWidth;
            $h = $fontSize;
            $spacing = 5;
            $pdf->SetFont('ModesBolTex','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $brand, 0, 0, 'C');    

        /***   Name */
            $description = $item['description'];
            $y += $fontSize;
            $fontSize = 20;
            $pdf->SetTextColor(254, 254, 254);
            $pdf->SetFont('ModesReg','',$fontSize);
            
            $pdf->SetXY($x, $y);
            $lines = $pdf->MultiCellRet($w, $fontSize, $description, 0, 'C');
            $blankSpace = ($lines==1) ? $fontSize : $fontSize*$lines;

        /***  Sale Price */
            $pdf->SetTextColor(0, 0, 0);
            $price ='';
            //if($item['normal_price'] > 1) {
                $price = sprintf('$%.2f', $item['normal_price']);
            //} else  {
            //    $price = ltrim(sprintf('¢%d', $item['special_price']*100),'A');
            //}
            
            $y += $blankSpace; //space bewtten top and start of first element.
            $h = $fontSize;
            $pdf->SetFont('ModesBolTex','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $price, 0, 0, 'C');

        /***   Regular Price */
            $y += $fontSize;
            $fontSize = 9;
            $regPrice = sprintf('$%.2f', $info['normal_price']);
            $pdf->SetFont('ModesLigTex','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w/2, $fontSize, 'reg', 0,0, 'R');
            $pdf->SetXY($x, $y+$fontSize);
            $pdf->Cell($w/2, $fontSize, 'price', 0,0, 'R');
            $pdf->SetXY($x+($w/2), $y +4);
            $fontSize = 12;
            $pdf->setFontSize($fontSize);
            $pdf->Cell($w/2, $fontSize, $regPrice, 0,0, 'L');
            
        /***   sale date.   */
            $x += 0;
            $y += 30;
            $fontSize = 9;
            $pdf->SetFont('ModestoText-LightItalic','',$fontSize);
            $startDate = new \DateTime($item['startDate']);
            $endDate = new \DateTime($item['endDate']);
            $dateString = $startDate->format('n/d/y').' - '.$endDate->format('n/d/y');
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($this->width, 7, $dateString, 0, 0, 'C');

            $memberSale = $info['discounttype'];
            if ($memberSale == 2) {
                $pdf->SetXY($x +30, $y);
                $pdf->Cell($this->width, 7, 'Member Only', 0, 0, 'C');
            }

            /*
            
            $pdf->SetTextColor(30, 77, 44);
            //PLU or UPC
            $plu = $this->barcodeText($item['upc'], strlen($item['upc']));
            $fontSize = 10;
            $x += 0;
            $y += 3;
            $w = $this->width;
            $h = $fontSize;
            $pdf->SetFont('ModestoOpenInlineFillH','',$fontSize);
            $pdf->SetFontSize($fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w,$h, $plu, 0,0, 'L');
            //Origin
            $origin = ($item['originName'] == '') ? 'ORIGIN:                       ' : $item['originName'] ;
            $x += 0;
            $y += 0;
            $w = $this->width - $this->borderLineWidth*2 -9;
            $h = $fontSize;
            //$pdf->SetFont($this->font,'',7);
            //$pdf->SetFontSize($this->SMALL_FONT);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $origin, 0,0, 'R');


            //varity
            $descParts = explode(', ',$item['description']);
            $varity = '';
            if (sizeof(descParts) > 1) {
                $varity = $descParts[1];
            }
            $description = $descParts[0];
            $x = $this->startX + $xOffset*$column + $this->outerBorderWidth/2;
            $y += $fontSize +10;
            $fontSize = 14;
            $w = $this->width - $this->outerBorderWidth;
            $h = $fontSize;
            $spacing = 5;
            $pdf->SetFont('ModestoIOpenPrimary','',$fontSize);
            $pdf->SetXY($x, $y);            
            
            $pdf->Cell($w, $h, $varity, 0, 0, 'C');
            //Description
            $y += $fontSize + 1.5;
            $fontSize = 26;
            $h = $fontSize;
            $pdf->SetFont('ModestoIOpenPrimary','',$fontSize);
            $pdf->SetXY($x, $y);
            $lines = $pdf->MultiCellRet($w, $h, $description,0, 'C');
            $blankSpace = ($lines==1) ? $fontSize : 0;
            // Vendor/Farm
            $brand = $item['brand'];
            $x = $this->startX + $xOffset*$column + $this->outerBorderWidth/2;
            $y += $fontSize +1;
            $fontSize = 12;
            $w = $this->width - $this->outerBorderWidth;
            $h = $fontSize;
            $spacing = 5;
            $pdf->SetFont('ModestoOpenInlineFillH','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $brand, 0, 0, 'C');           

            //price
            $price ='';
            //if($item['normal_price'] > 1) {
                $price = sprintf('$%.2f', $item['normal_price']);
            //} else  {
             //   $price = ltrim(sprintf('¢%d', $item['normal_price']*100),'A');
            //}
            
            //$x -= 10;
            $y += $fontSize + 10; //space bewtten top and start of first element.
            $fontSize = 46;
            $h = $fontSize;
            $pdf->SetFont('ModestoOpenInlineFillH','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $price, 0, 0, 'C');

            //units
            $units = '';
            if ($info['scale'] == 1) {
                $units = 'per Pound';
            } else {
                $units = 'per Each';
            }
            //$x += 10;
            $y += $fontSize; //space bewtten top and start of first element.
            $fontSize = 14;
            $h = $fontSize;
            $pdf->SetFont('ModestoOpenInlineFillH','',$fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $units, 0, 0, 'C');
        */

            $count++;
            $sign++;
        }

        $pdf->Output('WellnessSpecial9upP.pdf', 'I');
        set_time_limit(30);
    }

    protected function getProdFlags($upc) {
        $dbc = \FannieDB::get(\FannieConfig::config('OP_DB'));
        $query = "
            SELECT f.description,
                f.bit_number,
                (1<<(f.bit_number-1)) & p.numflag AS flagIsSet
            FROM products AS p, 
                prodFlags AS f
            WHERE p.upc=?
                " . (\FannieConfig::config('STORE_MODE') == 'HQ' ? ' AND p.store_id=? ' : '') . "
                AND f.active=1";
        $args = array($upc);
        if (\FannieConfig::config('STORE_MODE') == 'HQ') {
            $args[] = \FannieConfig::config('STORE_ID');
        }
        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep,$args);
        
        if ($dbc->numRows($res) == 0){
            // item does not exist
            $prep = $dbc->prepare('
                SELECT f.description,
                    f.bit_number,
                    0 AS flagIsSet
                FROM prodFlags AS f
                WHERE f.active=1');
            $res = $dbc->execute($prep);
        }//please use the order  "Local, Organic, NONGMO, Gluten Free
        //please use the order  "Local, Organic, NONGMO, Gluten Free
        $flags = array('Local'=> false, 'Organic' => false, 'Non_GMO' => false, 'Gluten Free'=>false);
        
        while($info = $dbc->fetchRow($res)){
                $flags[$info['description']] = $info['flagIsSet'];
       }
       $showLocal = $flags['Local'];
       $showOrganic = $flags['Organic'];
       $showNONGMO = $flags['Non_GMO'];
       $showGlutenFree = $flags['Gluten Free'];

       return $flags; 
    }

    protected function getExtraInfo($upc)
    {
        $dbc = \FannieDB::get(\FannieConfig::config('OP_DB'));
        $prep = $dbc->prepare('SELECT * FROM products WHERE upc=?');
        return $dbc->getRow($prep, array($upc));
    }

    private function barcodeText($barcode,$len)
    {
        if($len ==12) {
            $barText = 'UPC: '.substr($barcode,0,2)."-".substr($barcode,2,5)."-".substr($barcode,7,5)."-".substr($barcode,12);
            $len+=3;
        } else {
            $barText = 'PLU: '.ltrim($barcode,'0');
        }
        return $barText;
    }

}

