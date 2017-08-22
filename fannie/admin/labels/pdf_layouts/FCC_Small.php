<?php
/*******************************************************************************

    Copyright 2017 Franklin Community Coop

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

  class FCC_Small_PDF extends FpdfWithBarcode
  {
    function barcodeText($x, $y, $h, $barcode, $len)
    {
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

  function FCC_Small($data,$offset=0){
    global $FANNIE_OP_DB;
    global $FANNIE_ROOT;
    //global $FANNIE_COOP_ID;
    $dbc = FannieDB::get($FANNIE_OP_DB);

    $hspace = 1.5;
    $h = 37.36875; //what is this?
    $top = 5.99 + 2.5; //was 12.7 + 2.5
    $left = 5; //left margin 
    // above..this was two by shifing it to 4 we get two columns until I set $LeftShift at 66 or so
    // and it seems to shift them all right
    $space = 1.190625 * 2; //tried 3 to see if shift columns over
  
    $pdf=new FCC_Small_PDF('P', 'mm', 'Letter');
    $pdf->AddFont('arialnarrow');
    $pdf->AddFont('steelfish');
    $pdf->SetMargins($left ,$top + $hspace);
    $pdf->SetAutoPageBreak('off',0);
    $pdf->AddPage('P');
    $pdf->SetFont('Arial','',10);
  
    /**
    * set up location variable starts
    */

    $barLeft = $left; // this was a 4 now 14 it did create 3 columns
    $unitTop = $top + $hspace;
    $alpha_unitTop = $unitTop + $hspace;
    $descTop = $unitTop + 17;
    $barTop = $unitTop + 16;
    $priceTop = $unitTop - 4;
    $labelCount = 0;
    $brandTop = $unitTop + 4;
    $sizeTop = $unitTop + 8;
    $genLeft = $left;
    $unitLeft = $left;
    $skuTop = $unitTop + 12;
    $vendLeft = $left + 13;
    $down = 31.006; //30.55 kept it the right hieght
    //there is a relation ship below Left and w
    $LeftShift = 41.25; 
    //was 51 shifts the width between columns 67.990625 seems okay on the PRICE RETAIL
    //the above does alot to create the columns for the top
    //$w = 70.609375; //this does width of label started at 49 @ 70 it started to line on column one but two and three stuck over so leftshift is the next test
    $priceLeft = (8) + ($space); 
    // $priceLeft = 24.85
    /**
       * increment through items in query
       */
       
    foreach($data as $row){
    /**
    * check to see if we have made 32 labels.
    * if we have start a new page....
    */

        if($labelCount == 40){
            $pdf->AddPage('P');
            $barLeft = $left ; // this was a 4 now 14 it did create 3 columns
            $w=.35;
            $unitTop = $top + $hspace;
            $alpha_unitTop = $unitTop + $hspace;
            $descTop = $unitTop + 17;
            $barTop = $unitTop + 16;
            $priceTop = $unitTop - 4;
            $labelCount = 0;
            $brandTop = $unitTop + 4;
            $sizeTop = $unitTop + 8;
            $genLeft = $left;
            $unitLeft = $left;
            $skuTop = $unitTop + 12;
            $vendLeft = $left + 13;
            $down = 31.006; //30.55 kept it the right hieght
            //there is a relation ship below Left and w
            $LeftShift = 39; 
            //was 51 shifts the width between columns 67.990625 seems okay on the PRICE RETAIL
            //the above does alot to create the columns for the top
            $w = 39;//70.609375; //this does width of label started at 49 @ 70 it started to line on column one but two and three stuck over so leftshift is the next test
            $priceLeft = (8) + ($space); 
            //$priceLeft = ($w / 2) + ($space);
            // $priceLeft = 24.85
        }
      
        /** 
        * check to see if we have reached the right most label
        * if we have reset all left hands back to initial values
        */
        if($barLeft > 175){
            $barLeft = $leftshift;
            $barTop = $barTop + $down;
            $priceLeft = $priceLeft + $LeftShift;
            $priceTop = $priceTop + $down;
            $descTop = $descTop + $down;
            $unitTop = $unitTop + $down;
            $alpha_unitTop = $alpha_unitTop + $down;
            $brandTop = $brandTop + $down;
            $sizeTop = $sizeTop + $down;
            $genLeft = $left;
            $unitLeft = $left;
            $vendLeft = $left + 13;
            $skuTop = $skuTop + $down;
        }
        
        /**
        * instantiate variables for printing on barcode from 
        * $testQ query result set
        */

        // get the unit price unit.
        $qStdUnit = "SELECT u.unitStandard FROM prodStandardUnit u WHERE u.upc =?";
        $rStdUnit = $dbc->execute($dbc->prepare($qStdUnit),array($row['upc']));
        $iStdUnit = $dbc->fetchRow($rStdUnit);

        //get unit and flagging data;        
        $query = "
            SELECT f.description,
                f.bit_number,
                (1<<(f.bit_number-1)) & p.numflag AS flagIsSet
            FROM products AS p, 
                prodFlags AS f
            WHERE p.upc=?
                " . (FannieConfig::config('STORE_MODE') == 'HQ' ? ' AND p.store_id=? ' : '') . "
                AND f.active=1";
        $args = array($row['upc']);
        if (FannieConfig::config('STORE_MODE') == 'HQ') {
            $args[] = FannieConfig::config('STORE_ID');
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
        }

        //please use the order  "Local, Organic, NONGMO, Gluten Free
        $flags = array('Local'=> false, 'Organic' => false, 'Non_GMO' => false, 'Gluten Free'=>false);
        
        while($info = $dbc->fetchRow($res)){
                $flags[$info['description']] = $info['flagIsSet'];
       }
       $showLocal = $flags['Local'];
       $showOrganic = $flags['Organic'];
       $showNONGMO = $flags['Non_GMO'];
       $showGlutenFree = $flags['Gluten Free'];
           /* if ($i==0) $ret .= '<tr>';
            if ($i != 0 && $i % 2 == 0) $ret .= '</tr><tr>';
            $ret .= sprintf('<td><input type="checkbox" id="item-flag-%d" name="flags[]" value="%d" %s /></td>
                <td><label for="item-flag-%d">%s</label></td>',$i, $row['bit_number'],
                ($row['flagIsSet']==0 ? '' : 'checked'),
                $i,
                $row['description']
            );
            // embed flag info to avoid re-querying it on save
            $ret .= sprintf('<input type="hidden" name="pf_attrs[]" value="%s" />
                            <input type="hidden" name="pf_bits[]" value="%d" />',
                            $row['description'], $row['bit_number']);
            $i++;*/

        $price = $row['normal_price'];
        $desc = strtoupper(substr($row['description'],0,27));
        $brand = ucwords(strtolower(substr($row['brand'],0,13)));
        $pak = $row['units'];
        $size = $row['units'] . "-" . $row['size'];
        $sku = $row['sku'];
        $num_unit = $row['pricePerUnit'];
        $alpha_unit = "per ".$iStdUnit['unitStandard'];

       $upc = $row['upc'];
        /** 
        * determine check digit using barcode.php function
        */
        //$check = $pdf->GetCheckDigit($upc);
        /**
        * get tag creation date (today)
        */
        $tagdate = date('m/d/y');
        $vendor = substr($row['vendor'],0,7);

        /**
        * begin creating tag
        */
        $pdf->SetXY($genLeft +1, $unitTop+8); 
        $pdf->SetFont('steelfish','',29);
        $pdf->Cell(8,4,"\$$num_unit",0,0,'L');
        $pdf->SetFont('Arial','',7);
        $pdf->SetXY($genLeft+2, $unitTop+13.2); //numerical unit // silas: was above
        //  $pdf->SetXY($genLeft+4.7, $unitTop+10);

        $pdf->MultiCell(20,3,$alpha_unit,0,'L',0); //send alpha into a two liner to the right of UNIT price
        //$pdf->SetFont('Arial','B',8);
        //$pdf->SetXY($genLeft+9,$unitTop+8.35); //price on the right side top Made this +3 cause it goes up toward last row of labels
        //$pdf->Cell(10,8,"$",0,0,'R');
    
        $pdf->SetFont('steelfish','',29);
        $pdf->SetXY($genLeft+30.55,$unitTop+8.5); //price on the right side top Made this +3 cause it goes up toward last row of labels
        $pdf->Cell(10,8,"\$$price",0,0,'R'); //\$$price $barLeft
  
        $pdf->SetFont('arialnarrow','',6);
        $pdf->SetXY($genLeft+1, $unitTop+18.5); //desc of tiem
        $pdf->Cell($w,4,"$brand $desc",0,0,'L');
        $pdf->SetFont('Arial','',6);
        $pdf->SetXY($genLeft+25, $unitTop+16.2);
        //please use the order  "Local, Organic, NONGMO, Gluten Free
        if ($showLocal) {$pdf->Image($FANNIE_ROOT.'src/images/Local.jpg',$genLeft+26,$unitTop+16,3);}
        if ($showOrganic) {$pdf->Image($FANNIE_ROOT.'src/images/Organic.jpg',$genLeft+29.5,$unitTop+16,3);}
        if ($showNONGMO) {$pdf->Image($FANNIE_ROOT.'src/images/non-gmo.jpg',$genLeft+33,$unitTop+16,3);}
        if ($showGlutenFree) {$pdf->Image($FANNIE_ROOT.'src/images/Gluten-Free.jpg',$genLeft+36.5,$unitTop+16,3);}        
        
        $pdf->Cell($w,4,$cs_size,0,0,'L');
        //$pdf->Cell($w,4,"1/".$size_value." ".$size_unit,0,0,'L');
        $pdf->SetFont('Arial','',7);
        //$pdf->SetXY($priceLeft-22,$skuTop+10);
  

        $pdf->SetXY($genLeft+3, $unitTop+28.5);
        $pdf->Cell($w,4,"$vendor $sku",0,0,'L');
        $pdf->SetXY($genLeft+28-.5, $unitTop+28.5);
        $pdf->Cell(12,4,$tagdate,0,0,'R'); 
        /** 
        * add check digit to pid from testQ
        */
        $pdf->SetFont('Arial','',4);
        // silas: was $pdf->UPC_A($genLeft+1.25, $unitTop+21.5,$upc,3);
        $pdf->UPC_A($genLeft+4.5, $unitTop+21.5,$upc,3); //changes size //changed to 6 from 3 to move it down

        //  $pdf->SetFont('Arial','',7);
        $pdf->SetXY($genLeft+1.3, $unitTop+23.6);
        $pdf->Cell(5,5,$caseqty,0,0,'L'); 
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

