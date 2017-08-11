<?php
/*******************************************************************************

    Copyright 2009 Whole Foods Co-op

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
if (!class_exists('FpdfWithBarcode')) {
    include(dirname(__FILE__) . '/../FpdfWithBarcode.php');
}

  class MA_Standard_PDF extends FpdfWithBarcode
  {
    function barcodeText($x, $y, $h, $barcode, $len)
    {
      if ($len == 12){
         $barcode = substr($barcode,0,1)."-".substr($barcode,2,5)."-".substr($barcode,7,5)."-".substr($barcode,12);
         $len +=3;
      }

      $this->SetFont('Arial','',9);
      if (filter_input(INPUT_GET, 'narrow') !== null)
          $this->Text($x,$y+$h+11/$this->k,substr($barcode,-$len));
      else
          $this->Text($x+6,$y+$h+11/$this->k,substr($barcode,-$len));
    }
  }
  
  /**------------------------------------------------------------
   *       End barcode creation class 
   *-------------------------------------------------------------*/
  
  
  /**
   * begin to create PDF file using fpdf functions
   */

function MA_Standard($data,$offset=0){
    global $FANNIE_OP_DB;
    global $FANNIE_ROOT;
    //global $FANNIE_COOP_ID;
    $dbc = FannieDB::get($FANNIE_OP_DB);
    $shift_count = 1; //help move columns over

    $hspace = 1.5; //was 0.79375 
    $h = 37.36875; //what is this?
    $top = 8.99 + 2.5; //was 12.7 + 2.5
    $left = 6.55; //left margin 
    // above..this was two by shifing it to 4 we get two columns until I set $LeftShift at 66 or so
    // and it seems to shift them all right
    $space = 1.190625 * 2; //tried 3 to see if shift columns over
  
    $pdf=new MA_Standard_PDF('P', 'mm', 'Letter');
    $pdf->AddFont('arialnarrow');
    $pdf->AddFont('steelfish');
    $pdf->SetMargins($left ,$top + $hspace);
    $pdf->SetAutoPageBreak('off',0);
    $pdf->AddPage('P');
    $pdf->SetFont('Arial','',10);
  
    $barLeft = $left + 32; // this was a 4 now 14 it did create 3 columns
    $unitTop = $top + $hspace;
    $alpha_unitTop = $unitTop + $hspace;
    $descTop = $unitTop + 17;
    $barTop = $unitTop + 16;
    $priceTop = $unitTop + 4;
    $labelCount = 0;
    $brandTop = $unitTop + 4;
    $sizeTop = $unitTop + 8;
    $genLeft = $left;
    $skuTop = $unitTop + 12;
    $vendLeft = $left + 13;
    $down = 31.006; //30.55 kept it the right hieght
    //there is a relation ship below Left and w
    $LeftShift = 65.990625; 
    //was 51 shifts the width between columns 67.990625 seems okay on the PRICE RETAIL
    //the above does alot to create the columns for the top
    $w = 70.609375; //this does width of label started at 49 @ 70 it started to line on column one but two and three stuck over so leftshift is the next test
    $priceLeft = ($w / 2) + ($space);
    // $priceLeft = 24.85
    /**
       * increment through items in query
       */
       
    foreach($data as $row){
    /**
    * check to see if we have made 32 labels.
    * if we have start a new page....
    */

        if($labelCount == 24){ //this was 32
            $pdf->AddPage('P');
            $unitTop = $top + $hspace;
            $alpha_unitTop = $unitTop + $hspace;
            $descTop = $unitTop + 17;
            $barLeft = $left + 32;  // this was a 4 now 14 it did create 3 columns
            $barTop = $unitTop + 16;
            $priceTop = $unitTop + 4;
            $priceLeft = ($w / 2) + ($space);
            $labelCount = 0;
            $brandTop = $unitTop + 4;
            $sizeTop = $unitTop + 8;
            $genLeft = $left;
            $skuTop = $unitTop + 12;
            $vendLeft = $left + 13;
        }
      
        /** 
        * check to see if we have reached the right most label
        * if we have reset all left hands back to initial values
        */
        if($barLeft > 175){
            $barLeft = $left + 32;  // this was a 4 now 14 it did create 3 columns
            $barTop = $barTop + $down;
            $priceLeft = ($w / 2) + ($space);
            $priceTop = $priceTop + $down;
            $descTop = $descTop + $down;
            $unitTop = $unitTop + $down;
            $alpha_unitTop = $alpha_unitTop + $down;
            $brandTop = $brandTop + $down;
            $sizeTop = $sizeTop + $down;
            $genLeft = $left;
            $vendLeft = $left + 13;
            $skuTop = $skuTop + $down;
        }

          /**
   *Had to shift items over
   *ie column 2 over 2 
   *column 3 over three
   *count 1 2 3 if 2 shift if 3 shift then back to 1
   */
        if($shift_count == '2') {
            $genLeft = $genLeft + 2;
            $vendLeft = $vendLeft + 2;
            //$barLeft = $barLeft + 2; //these shifted it to two columns?
            $shift_count++;
        } elseif($shift_count == '3') {
            $genLeft = $genLeft + 3;
            $vendLeft = $vendLeft + 3;
            //$barLeft = $barLeft + 3;
            $count = 1;  
        } else { 
            $genLeft = $genLeft + 3;
            $vendLeft = $vendLeft + 3;
            $descTop = $descTop + .5;
            //$barLeft = $barLeft -2;
            $shift_count++; 
        }
   
        
        /**
        * instantiate variables for printing on barcode from 
        * $testQ query result set
        */


        if ($row['scale'] == 0) {$price = $row['normal_price'];}
        elseif ($row['scale'] == 1) {$price = $row['normal_price'] . "/lb";}
        $desc = strtoupper(substr($row['description'],0,27));
        $brand = ucwords(strtolower(substr($row['brand'],0,13)));
        $pak = $row['units'];
        $size = $row['units'] . "-" . $row['size'];
        $sku = $row['sku'];
        $num_unit = $row['pricePerUnit'];
        $alpha_unit = "per ".$iStdUnit['unitStandard'];

       $upc = $row['upc'];
       if (!(substr($upc,0,2) == "00")){
             $check = "";
        } else {
             $upc=(substr($upc,2));
             $check = $pdf->GetCheckDigit($upc);
        }
        /**
        * get tag creation date (today)
        */
        $tagdate = date('m/d/y');
        $vendor = substr($row['vendor'],0,7);

           /* begin creating tag
   */
  $pdf->SetFont('Arial','',24);
  $pdf->SetXY($genLeft-2, $unitTop+5.9); //per unit cost numerical total
  $pdf->Cell($w,4,"\$$num_unit",0,0,'L');
  
  $pdf->SetFont('Arial','',12);
  $pdf->SetXY($genLeft+1, $unitTop+11.2); //numerical unit
  $pdf->MultiCell(20,4,$alpha_unit,0,'C',0); //send alpha into a two liner to the right of UNIT price
  
   
  //$pdf->SetFont('Arial','B',8);
  //$pdf->SetXY($genLeft+7,$unitTop+8.7); //price on the right side top Made this +3 cause it goes up toward last row of labels
  //$pdf->Cell($w/2,8,"\$",0,0,'R');
  
  $pdf->SetFont('Arial','B',30);
  $pdf->SetXY($genLeft+28.6,$unitTop+8.7); //price on the right side top Made this +3 cause it goes up toward last row of labels
  $pdf->Cell($w/2,8,"\$$price",0,0,'R');
  

  
  $pdf->SetFont('Arial','',9);
  $pdf->SetXY($genLeft, $descTop+3.4); //desc of tiem
  $pdf->Cell($w,4,"$brand $desc",0,0,'L');

  //$pdf->SetXY($genLeft,$brandTop);
  //$pdf->Cell($w/2,4,Test1,0,0,'L'); //this is not showing was $brand
  //$pdf->SetXY($genLeft,$sizeTop); 
  //$pdf->Cell($w/2,4,$size,0,0,'L'); //was creating - mark under unit cost
  $pdf->SetXY($priceLeft+9.5,$unitTop+24);
  $pdf->Cell($w/3,4,$size,0,0,'R');
  //$pdf->Cell($w/3,4,"1/".$size_value." ".$size_unit,0,0,'R'); //this was date now going to be unit under normal price
  // $pdf->SetFont('Arial','',10);
  //$pdf->SetXY($genLeft,$skuTop);
  //$pdf->Cell($w/3,4,Test2,0,0,'L'); //this was not showing was $sku
  $pdf->SetFont('Arial','',7);
  //$pdf->SetXY($priceLeft-22,$skuTop+10);
  $pdf->SetXY($vendLeft+26,$skuTop+16.5);
  $pdf->Cell($w/3,4,$tagdate,0,0,'R'); //date moved Down lower left corder
  $pdf->SetXY($vendLeft-20,$skuTop+16.5);
  $pdf->Cell($w/3,4,"$vendor $sku",0,0,'C'); 

  /** 
   * add check digit to pid from testQ
   */
    $newUPC = $upc . $check;
    //$upc = "0738018001633";
    if (strlen($upc) <= 11)
        $pdf->UPC_A($barLeft-18,$barTop+8.3,$newUPC,3);
    else
        $pdf->EAN13($barLeft-18,$barTop+8.3,$newUPC,3);

    //$pdf->UPC_A($barLeft-18,$barTop+8.3,$newUPC,3); //changes size //changed to 6 from 3 to move it down
  /**
   * increment label parameters for next label
   */
    $barLeft =$barLeft + $LeftShift;
    $priceLeft = $priceLeft + $LeftShift;
    $genLeft = $genLeft + $LeftShift;
    $vendLeft = $vendLeft + $LeftShift;
    $labelCount++;
    }
      
    /**
    * write to PDF
    */
    $pdf->Output();
  }

