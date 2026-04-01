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

namespace COREPOS\Fannie\API\item\signage {

class Signage12UpL extends \COREPOS\Fannie\API\item\FannieSignage 
{
    protected $BIG_FONT = 36;
    protected $MED_FONT = 9;
    protected $SMALL_FONT = 10;
    protected $SMALLER_FONT = 8;
    protected $SMALLEST_FONT = 6;

    protected $font = 'Arial';
    protected $alt_font = 'Arial';

    protected $width = 68.67;
    protected $height = 70.5;
    protected $top = 17;
    protected $left = 8.5;

    protected function drawItem($pdf, $item, $row, $column)
    {
        $item['description'] = preg_replace("/[^\x01-\x7F]/"," ", $item['description']);
        $item['description'] = str_replace("  ", " ", $item['description']);
        $effective_width = $this->width - $this->left;

        $price = $this->printablePrice($item);

        $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($row*$this->height) + 6);
        $pdf->SetFont($this->font, 'B', $this->SMALL_FONT);
        $pdf = $this->fitText($pdf, $this->SMALL_FONT, 
            strtoupper($item['brand']), array($column, 6, 1));
        $pdf->AddFont('steelfish');

        $pdf->SetFont($this->font, '', $this->MED_FONT);
        $pdf = $this->fitText($pdf, $this->MED_FONT, 
            $item['description'], array($column, 6, 2));

        $pdf->SetX($this->left + ($this->width*$column));
        $pdf->SetFont($this->alt_font, '', $this->SMALLER_FONT);
        $item['size'] = $this->formatSize($item['size'], $item);
        $pdf->Cell($effective_width, 3, $item['size'], 0, 1, 'C');

        if (!isset($item['signMultiplier']) || $item['signMultiplier'] != -3) {
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - 42));
            $pdf->SetFont($this->font, '', $this->BIG_FONT);
            $pdf->Cell($effective_width, 11, $price, 0, 1, 'C');
        } else {
            $pdf->SetXY(-5 + $this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - 42));
            if (strpos($price, 'FREE') != false) {
                $pdf->SetTextColor(244, 116, 30);

                $pdf->SetXY($this->left + ($this->width*$column) - 3, $this->top + ($this->height*$row) + ($this->height - 46));
                $pdf->SetFont($this->font, 'B', 12);
                $pdf->Cell($this->width, 12, 'Buy One, Get One', 0, 1, 'C');

                $pdf->SetXY($this->left + ($this->width*$column) - 3, $this->top + ($this->height*$row) + ($this->height - 37));
                $pdf->SetFont($this->font, 'B', $this->BIG_FONT);
                $pdf->Cell($this->width, 12, 'FREE', 0, 1, 'C');

                $pdf->SetTextColor(0, 0, 0);

                // BOGO limit
                if ($item['transLimit'] > 0) {
                    $pdf->SetFont($this->font, '', $this->SMALLEST_FONT);
                    $pdf->SetXY($this->left + ($this->width*$column) - 3, $this->top + ($this->height*$row) + ($this->height - 29));
                    $pdf->Cell($this->width, 12, 'Limit ' . $item['transLimit'] / 2 . ' per customer', 0, 1, 'C');
                }

            }
        }

        if ($item['originShortName'] != '') {
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - 35.5));
            $pdf->SetFont($this->alt_font, '', $this->SMALLEST_FONT);
            $pdf->Cell($effective_width, 20, $item['originShortName'], 0, 1, 'R');
        }

        if ($this->validDate($item['startDate']) && $this->validDate($item['endDate'])) {
            // intl would be nice
            $datestr = $this->getDateString($item['startDate'], $item['endDate']);
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - 33));
            $pdf->SetFont($this->alt_font, '', $this->SMALLEST_FONT);
            $pdf->Cell($effective_width, 20, strtoupper($datestr), 0, 1, 'R');
                        // regular price
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - $this->top - 13));
            $pdf->Cell($effective_width, 6, sprintf('Regular Price: $%.2f', $item['nonSalePrice']), 0, 1, 'R');
        }

        // suppress "regular" price on price change batches
        if (isset($item['discountType']) && $item['discountType'] == 0) {
            $item['nonSalePrice'] = $item['normal_price'];
        }

        if (isset($item['nonSalePrice']) && $item['nonSalePrice'] > $item['normal_price']) {
            // regular price
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - $this->top - 13));
            $pdf->Cell($effective_width, 6, sprintf('Regular Price: $%.2f', $item['nonSalePrice']), 0, 1, 'R');
        } else {
            $pdf->SetFont($this->alt_font, '', $this->SMALLEST_FONT);
            $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - 33));
            $pdf->Cell($effective_width, 20, $item['upc'], 0, 1, 'L');
        } 

        $unitStandard = 'error';
        $str = $item['unitofmeasure'];
        $strArray = explode('/', $str);
        if(sizeof($strArray) > 2) { 
            $unitStandard = $strArray[2];
        } else if ($item['size'] !=''){
            $unitStandard = preg_replace('/[0-9]+/', '', $item['size']);
        }


	    $num_unit = $item['pricePerUnit'];
        $alpha_unit = "per ".$unitStandard;

        //Add Orange Rectangle and Blue Stripe
        //oranges square is 17x10mm
        $rectH = 12;
	    $rectW = 20;
        $pdf->SetFillColor(243,112,22);
        $pdf->SetDrawColor(243,112,22);
        $pdf->Rect($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - $this->top - 13.4), $rectW, $rectH,'DF');
	    //Clean up
	    $pdf->SetFillColor(0,0,0);
	    $pdf->SetDrawColor(0,0,0);
	    $pdf->SetTextColor(0,0,0);

	    //Unit Price
        $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - $this->top - 11));
        $pdf->SetFont('steelfish','',22);
        $pdf->Cell($rectW,4.5,"\$$num_unit",0,0,'C');
	
	    $pdf->SetFont('Arial','',6);
        $pdf->SetXY($this->left + ($this->width*$column), $this->top + ($this->height*$row) + ($this->height - $this->top - 6.5)); //numerical unit // silas: was above
        $pdf->MultiCell(20,2.5,$alpha_unit,0,'C',0); //send alpha into a two liner to the right of UNIT price    

        return $pdf;
    }

    protected function tickMarks($pdf, $width, $height)
    {
        $pdf->Line(2, $height+0.0, 6, $height+0.0);
        $pdf->Line(2, (2*$height)+1.0, 6, (2*$height)+1.0);
        $pdf->Line(4*$width-3, $height+0.0, 4*$width+1, $height+0.0);
        $pdf->Line(4*$width-3, (2*$height)+1.0, 4*$width+1, (2*$height)+1.0);

        $pdf->Line($width+1.5, 2, $width+1.5, 8);
        $pdf->Line(2*$width+1.5, 2, 2*$width+1.5, 8);
        $pdf->Line(3*$width+1.5, 2, 3*$width+1.5, 8);
        $pdf->Line($width+1.5, (3*$height)-6, $width+1.5, 3*$height);
        $pdf->Line(2*$width+1.5, (3*$height)-6, 2*$width+1.5, 3*$height);
        $pdf->Line(3*$width+1.5, (3*$height)-6, 3*$width+1.5, 3*$height);

        return $pdf;
    }

    protected function createPDF()
    {
        $pdf = new \FPDF('L', 'mm', 'Letter');
        $pdf->SetMargins(3.175, 3.175, 3.175);
        $pdf->SetAutoPageBreak(false);
        $pdf = $this->loadPluginFonts($pdf);
        $pdf->SetFont($this->font, '', 16);

        return $pdf;
    }

    public function drawPDF()
    {
        $pdf = $this->createPDF();

        $data = $this->loadItems();
        $data = $this->sortProductsByPhysicalLocation($this->getDB(), $data, $this->store);
        $count = 0;
        $sign = 0;
        $this->top = 17;
        foreach ($data as $item) {
            $item = $this->decodeItem($item);
            if ($count % 12 == 0) {
                $pdf->AddPage();
                $sign = 0;
            }

            $row = floor($sign / 4);
            $column = $sign % 4;
            $pdf = $this->drawItem($pdf, $item, $row, $column);

            $count++;
            $sign++;
        }

        $pdf->Output('Signage12UpL.pdf', 'I');
    }
}

}

