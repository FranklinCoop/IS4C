<?php
/*******************************************************************************

    Copyright 2018 Franklin Community co-op

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

class FCCProduce6upP extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 29.37;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 7;

    protected $fontHead = 'ModestoIOpenPrimary';
    protected $font = 'ModestoOpenInlineFill';
    protected $fontH = 'ModestoOpenInlineFillH';
    protected $fontM = 'ModestoOpenInlineFillM';

    protected $width = 288;
    protected $height = 216;
    protected $startX = 12;
    protected $startY = 36;
    protected $borderLineWidth=12;
    protected $outerBorderWidth=9;
    protected $innderBorderWidth=3;
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
        //fpdf draws the border rect is outside of the width this accounts for the extra size in placemnt.
        //the fallowing two methods account for porder size when seting starting postions.
        $xOffset = $this->width + $this->borderLineWidth ;
        $yOffset = $this->height + $this->borderLineWidth ;
        foreach ($data as $item) {
            if ($count % 6 === 0) {
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
            $flags = $this->getProdFlags($item['upc']);
            $y=$this->startY;
            $x=$this->startX;
            $textWidth = $this->width - ($this->width*.4) - 4;


            //draw border
            $pdf->SetDrawColor(118,185,181);
            $pdf->SetLineWidth($this->outerBorderWidth);
            $y += $yOffset*$row;
            $x += $xOffset*$column;
            $pdf->Rect($x, $y, $this->width, $this->height);
            $pdf->SetDrawColor(30, 77, 44);
            $pdf->SetLineWidth($this->innderBorderWidth);
            $y += $this->outerBorderWidth/2;
            $x += $this->outerBorderWidth/2;
            $pdf->Rect($x, $y, $this->width-$this->outerBorderWidth, $this->height-$this->outerBorderWidth);
            $pdf->SetLineWidth(1);
            



            if ($flags['Local']&&$flags['Organic']) {
                //LOCAL
                $start_x = ($xOffset*$column) + $this->startX+($this->borderLineWidth/2)-0.5;
                $start_y = ($yOffset*$row) + $this->startY-($this->borderLineWidth/2) + $this->height -59.14;
                $end_x = ($xOffset*$column) + $this->width+1+$this->startX-($this->borderLineWidth/2)-.5;
                $end_y = ($yOffset*$row) + $this->height+$this->startY-($this->borderLineWidth/2) -20;
                $eq_x1 = $start_x+200;
                $eq_y1 = $start_y;
                $eq_x2 = $end_x;
                $eq_y2 = $end_y;
                $w = $this->width-11;
                $h = 60.14;
                $pdf->topCurveRect($start_x,$start_y,$eq_x1,$eq_y1,$eq_x2,$eq_y2,$end_x,$end_y,$w, $h,'FD', null, array(151,174,103));
                $fontSize = 28;
                $pdf->SetTextColor(30, 77, 44);
                $pdf->SetFont('ModestoOpenInlineFillH', '', $fontSize);
                $pdf->SetXY($start_x,$start_y+2);
                $pdf->CellFit($this->width-170, $fontSize, 'LOCAL',0,0,'L');
                // ORGANIC
                $start_x = ($xOffset*$column) + $this->startX+($this->borderLineWidth/2)-1;
                $start_y = ($yOffset*$row) + $this->startY-($this->borderLineWidth/2) + $this->height -30.4;
                $end_x = ($xOffset*$column) + $this->width+$this->startX-($this->borderLineWidth/2);
                $end_y = ($yOffset*$row) + $this->height+$this->startY-($this->borderLineWidth/2)-5;
                $eq_x1 = $start_x+200;
                $eq_y1 = $start_y-5;
                $eq_x2 = $end_x;
                $eq_y2 = $end_y;
                $w = $this->width-11;
                $h = 31.4;
                $pdf->topCurveRect($start_x,$start_y,$eq_x1,$eq_y1,$eq_x2,$eq_y2,$end_x,$end_y,$w, $h,'FD', null, array(30, 77, 44));
                $fontSize = 28;
                $pdf->SetFont('ModestoOpenInlineFillH', '', $fontSize);
                $pdf->SetTextColor(255,255,255);
                $pdf->SetXY($start_x,$start_y+5);
                $pdf->CellFit($w-130, $fontSize, 'ORGANIC',0,0,'L');
            } elseif ($flags['Local']) {
                //LOCAL .43 inch 31pt
                $start_x = ($xOffset*$column) + $this->startX+($this->borderLineWidth/2)-0.5;
                $start_y = ($yOffset*$row) + $this->startY-($this->borderLineWidth/2) + $this->height -31.4;
                $end_x = ($xOffset*$column) + $this->width+$this->startX-($this->borderLineWidth/2);
                $end_y = ($yOffset*$row) + $this->height+$this->startY-($this->borderLineWidth/2);
                $pdf->topCurveRect($start_x,$start_y,$start_x+200,$start_y,$end_x,$end_y,$end_x,$end_y,$this->width-11, 32.4,'FD', null, array(151,174,103));
                $fontSize = 28;
                $pdf->SetTextColor(30, 77, 44);
                $pdf->SetFont('ModestoOpenInlineFillH', '', $fontSize);
                $pdf->SetXY($start_x,$start_y+4);
                $pdf->Cell($this->width-170, $fontSize, 'LOCAL',0,0,'L');
            } elseif ($flags['Organic']) {
                // ORGANIC
                $start_x = ($xOffset*$column) + $this->startX+($this->borderLineWidth/2)-0.5;
                $start_y = ($yOffset*$row) + $this->startY-($this->borderLineWidth/2) + $this->height -32.4;
                $end_x = ($xOffset*$column) + $this->width+$this->startX-($this->borderLineWidth/2);
                $end_y = ($yOffset*$row) + $this->height+$this->startY-($this->borderLineWidth/2)+1;
                $pdf->topCurveRect($start_x,$start_y,$start_x+200,$start_y,$end_x,$end_y,$end_x,$end_y,$this->width-11, 33.4,'FD', null, array(30, 77, 44));
                $fontSize = 28;
                $w = $this->width-8;
                $pdf->SetFont('ModestoOpenInlineFillH', '', $fontSize);
                $pdf->SetTextColor(255,255,255);
                $pdf->SetXY($start_x,$start_y+7);
                $pdf->CellFit($w-130, $fontSize, 'ORGANIC',0,0,'L');
            }
            
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


            $count++;
            $sign++;
        }

        $pdf->Output('CakeOfTheMonth6upP.pdf', 'I');
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

