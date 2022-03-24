<?php
DEFINE ('DB_USER', 'root');
DEFINE ('DB_PASSWORD', 'dcr');
DEFINE ('DB_HOST', 'localhost');
DEFINE ('DB_NAME', 'shelftag');
$dbc = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

  /**
   * fpdf is the pdf creation class doc
   * manual and tutorial can be found in fpdf dir
   */
  require('../src/fpdf/fpdf.php');
  
  /**
   * prodFuction contains several product related functions
   */
  // require('prodFunction.php');
  
  /**-------------------------------------------------------- 
   *            begin  barcode creation class from 
   *--------------------------------------------------------*/


  /*******************************************************************************
  * Software: barcode                                                            *
  * Author:   Olivier PLATHEY                                                    *
  * License:  Freeware                                                           *
  * URL: www.fpdf.org                                                            *
  * You may use, modify and redistribute this software as you wish.              *
  *******************************************************************************/
  define('FPDF_FONTPATH','font/');
  
  class PDF extends FPDF
  {
    function EAN13($x,$y,$barcode,$h=16,$w=.35)
    {
          $this->Barcode($x,$y,$barcode,$h,$w,12);
    }
  
    function UPC_A($x,$y,$barcode,$h=16,$w=.35)
    {
          $this->Barcode($x,$y,$barcode,$h,$w,strlen($upc));
    }
  
    function GetCheckDigit($barcode)
    {
          //Compute the check digit
          $sum=0;
          for($i=0;$i<=10;$i+=2)
                  $sum+=3*$barcode{$i};
          for($i=1;$i<=9;$i+=2)
                  $sum+=$barcode{$i};
          $r=$sum%10;
          if($r>0)
                  $r=10-$r;
          return $r;
    }
  
    function TestCheckDigit($barcode)
    {
          //Test validity of check digit
          $sum=0;
          for($i=1;$i<=11;$i+=2)
                  $sum+=3*$barcode{$i};
          for($i=0;$i<=10;$i+=2)
                  $sum+=$barcode{$i};
          return ($sum+$barcode{12})%10==0;
    }
  
    function Barcode($x,$y,$barcode,$h,$w,$len)
    {
      GLOBAL $genLeft;
      GLOBAL $descTop;
          //Padding
          //$barcode=str_pad($barcode,$len-1,'0',STR_PAD_LEFT);
      //$barcode = $barcode . $check;
          /*if($len==12)
                  $barcode='0'.$barcode;
      */
          //Add or control the check digit
          
          //if(strlen($barcode)==12){ //was 12
         //	$barcode.=$this->GetCheckDigit($barcode);
          //	} 
          	//elseif(!$this->TestCheckDigit($barcode))
          	//	{
          	//		$this->Error('This is an Incorrect check digit' . $barcode);
          //		  	  //echo $x.$y.$barcode."\n";
          	//	}
        
          //Convert digits to bars
          $codes=array(
                  'A'=>array(
                          '0'=>'0001101','1'=>'0011001','2'=>'0010011','3'=>'0111101','4'=>'0100011',
                          '5'=>'0110001','6'=>'0101111','7'=>'0111011','8'=>'0110111','9'=>'0001011'),
                  'B'=>array(
                          '0'=>'0100111','1'=>'0110011','2'=>'0011011','3'=>'0100001','4'=>'0011101',
                          '5'=>'0111001','6'=>'0000101','7'=>'0010001','8'=>'0001001','9'=>'0010111'),
                  'C'=>array(
                          '0'=>'1110010','1'=>'1100110','2'=>'1101100','3'=>'1000010','4'=>'1011100',
                          '5'=>'1001110','6'=>'1010000','7'=>'1000100','8'=>'1001000','9'=>'1110100')
                  );
  
          $parities=array(
                  '0'=>array('A','A','A','A','A','A'),
                  '1'=>array('A','A','B','A','B','B'),
                  '2'=>array('A','A','B','B','A','B'),
                  '3'=>array('A','A','B','B','B','A'),
                  '4'=>array('A','B','A','A','B','B'),
                  '5'=>array('A','B','B','A','A','B'),
                  '6'=>array('A','B','B','B','A','A'),
                  '7'=>array('A','B','A','B','A','B'),
                  '8'=>array('A','B','A','B','B','A'),
                  '9'=>array('A','B','B','A','B','A')
                  );
          $code='101';
          if (strlen($barcode)== 12) {
          	$padded_barcode = '0'.$barcode;
          } else {
          	$padded_barcode = $barcode;	
          }
          $p=$parities[$padded_barcode{0}];
          for($i=1;$i<=6;$i++)
                  $code.=$codes[$p[$i-1]][$padded_barcode{$i}];
          $code.='01010';
          for($i=7;$i<=12;$i++)
                  $code.=$codes['C'][$padded_barcode{$i}];
          $code.='101';
          //Draw bars
          for($i=0;$i<strlen($code);$i++)
          {
                  if($code{$i}=='1')
                          $this->Rect($x+$i*$w,$y,$w,$h,'F');
          }
          
         //Print text uder barcode
         if (strlen($barcode) == 12){
         	$barcode = substr($barcode,0,6)."-".substr($barcode,6,5)."-".substr($barcode,11);
         }
         $this->SetFont('Arial','',6);
// Silas: was         $this->SetXY($genLeft+9.5,$descTop+7);
         $this->SetXY($genLeft+11,$descTop+7+1);
// Silas: was         $this->Cell(49.609375,4,$barcode,1,0,'L');
         $this->Cell(49.609375,2,$barcode,0,0,'L');

    }
  
  }
  
  /**------------------------------------------------------------
   *       End barcode creation class 
   *-------------------------------------------------------------*/
  
  
  /**------------------------------------------------------------
   *        Start creation of PDF Document here
   *------------------------------------------------------------*/
  
    
  /**
   * connect to mysql server and then 
   * set to database with UNFI table ($data) in it
   * other vendors could be added here, as well. 
   * NOTE: upc in UNFI is without check digit to match standard in 
   * products.
   */
  
  $data = 'shelftag';
  
  $db = mysql_connect('localhost','root', 'dcr');
  mysql_select_db($data,$db);
  

  
  /**
   * begin to create PDF file using fpdf functions
   */

  $hspace = 1.5; //was 0.79375 
  $h = 37.36875; //what is this?
  $top = 5.99 + 2.5; //was 12.7 + 2.5
  $left = 5; //left margin 
  // above..this was two by shifing it to 4 we get two columns until I set $LeftShift at 66 or so
  // and it seems to shift them all right
  $space = 1.190625 * 2; //tried 3 to see if shift columns over
  
  $pdf=new PDF('P', 'mm', 'Letter');
  $pdf->AddFont('arialnarrow');
   $pdf->AddFont('steelfish');
  $pdf->SetMargins($left ,$top + $hspace);
  $pdf->SetAutoPageBreak('off',0);
  $pdf->AddPage('P');
  $pdf->SetFont('Arial','',10);
//  $pdf->Image('large_lables_back.jpg', 3, 10, 200); //x y
  
  /**
   * set up location variable starts
   */
   
  $barLeft = $left ; // this was a 4 now 14 it did create 3 columns
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
 // $w = 70.609375; //this does width of label started at 49 @ 70 it started to line on column one but two and three stuck over so leftshift is the next test
$priceLeft = (8) + ($space); 
 //$priceLeft = ($w / 2) + ($space);
  // $priceLeft = 24.85
  /**
   * increment through items in query
   */
 /** 
   * $testQ query creates select for barcode labels for items
   */ 


$testQ = "
	SELECT right(EAN20,13) as upc, `VN_PRNO` as sku, `vn_init` as vn_init, `DESC30` as description, FORMAT((CUST/100),2) as normal_price, 1 as quantity, `VN_KEY` as vendor_id, `sizeValue`,`sizeUnit`,`CS_SIZE`, FORMAT((price_per_unit/100),2) as price_per_unit, `costUnit`, 0 as scale, `brand_show` as brand, `vn_name` as vendorName, `dep_nm` as department,subdep_nm as subname
	FROM shelftag.gfm
	ORDER BY vn_init, brand, description";

  
  $result = mysql_query($testQ);
  if (!$result) {
     $message  = 'Invalid query: ' . mysql_error() . "\n";
     $message .= 'Whole query: ' . $query;
     die($message);
  }
  $offset = 0;
  while($row = mysql_fetch_array($result)){
     /**
      * check to see if we have made XX labels.
      * if we have start a new page....
      */
      
     if($labelCount == 40){ //this was 32
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
 // $w = 70.609375; //this does width of label started at 49 @ 70 it started to line on column one but two and three stuck over so leftshift is the next test
$priceLeft = (8) + ($space); 
 //$priceLeft = ($w / 2) + ($space);
  // $priceLeft = 24.85
    
     }
  
     /** 
      * check to see if we have reached the right most label
      * if we have reset all left hands back to initial values
      */
      if($barLeft > 165){
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
   *Had to shift items over
   *ie column 2 over 2 
   *column 3 over three
   *count 1 2 3 if 2 shift if 3 shift then back to 1
   */
//if($shift_count == '2') {
   //$genLeft = $genLeft + 2;
  // $vendLeft = $vendLeft + 2;
   //$unitLeft += 3;
   //$barLeft = $barLeft + 2; //these shifted it to two columns?
   //$shift_count++;
  // } elseif($shift_count == '3') {
  // $genLeft = $genLeft + 3;
   //$vendLeft = $vendLeft + 3;
   //$unitLeft += 6;
   //$barLeft = $barLeft + 3;
   //$shift_count = 1;  
   //} else { 
   $genLeft = $genLeft + 2.5;
   $vendLeft = $vendLeft + 2.5;
   //$descTop = $descTop + .5;
   $unitLeft += 2.5;
   //$barLeft = $barLeft -2;
   //$shift_count++; 
   //}

   
  /**
   * instantiate variables for printing on barcode from 
   * $testQ query result set
   */
     /** 
   * determine check digit using barcode.php function
   */
     
  /**
   * get tag creation date (today)
   */
     $tagdate = date('m/d/y');
     
     $department = $row['subname'];
     $department = strtoupper(substr($department,0,5));
     //$department = $row['department'];
     $vendor = $department."-".$row['brand'];
     $quantity = $row['quantity'];
     $quantity = 1;
     $num_unit = $row['price_per_unit'];
     $alpha_unit = "per ".$row['costUnit'];
     $upc = $row['upc'];
     if (!(substr($upc,0,2) == "00")){
     	     $check = "";
     	} else {
     	     $upc=(substr($upc,2));
     	     $check = $pdf->GetCheckDigit($upc);
     	}
     $price = $row['normal_price'];
$size_value =$row['sizeValue'];
$size_unit = $row['sizeUnit'];
$cs_size = $row['CS_SIZE'];
$desc=$row['description'];
$sku = $row['sku'];
$vn_init = $row['vn_init'];
$brand = $row['brand'];

  
  /**
   * begin creating tag
   */

  $pdf->SetXY($genLeft - 1, $unitTop+8); 
  $pdf->SetFont('steelfish','',29);
  $pdf->Cell(8,4,"\$$num_unit",0,0,'L');
  $pdf->SetFont('Arial','',7);
  $pdf->SetXY($genLeft+1, $unitTop+13.2); //numerical unit // silas: was above
//  $pdf->SetXY($genLeft+4.7, $unitTop+10);

  $pdf->MultiCell(20,3,$alpha_unit,0,'L',0); //send alpha into a two liner to the right of UNIT price
  //$pdf->SetFont('Arial','B',8);
  //$pdf->SetXY($genLeft+9,$unitTop+8.35); //price on the right side top Made this +3 cause it goes up toward last row of labels
  //$pdf->Cell(10,8,"$",0,0,'R');
    
  $pdf->SetFont('steelfish','',29);
  $pdf->SetXY($genLeft+27.55,$unitTop+9.9); //price on the right side top Made this +3 cause it goes up toward last row of labels
  $pdf->Cell(10,8,"\$$price",0,0,'R'); //\$$price $barLeft
  
  $pdf->SetFont('arialnarrow','',6);
  $pdf->SetXY($genLeft, $unitTop+18.5); //desc of tiem
  $pdf->Cell($w,4,"$brand $desc",0,0,'L');
  $pdf->SetFont('Arial','',6);
  $pdf->SetXY($genLeft+25, $unitTop+16.2);
  $pdf->Cell($w,4,$cs_size,0,0,'L');
  //$pdf->Cell($w,4,"1/".$size_value." ".$size_unit,0,0,'L');
  $pdf->SetFont('Arial','',7);
  //$pdf->SetXY($priceLeft-22,$skuTop+10);
  

  $pdf->SetXY($genLeft, $unitTop+27.5);
  $pdf->Cell($w,4,"$vn_init $sku",0,0,'L');
  $pdf->SetXY($genLeft+25-.5, $unitTop+27.5);
  $pdf->Cell(12,4,$tagdate,0,0,'R'); 
  /** 
   * add check digit to pid from testQ
   */
    $pdf->SetFont('Arial','',4);
    $newUPC = $upc . $check;
// silas: was $pdf->UPC_A($genLeft+1.25, $unitTop+21.5,$upc,3);
    $pdf->UPC_A($genLeft+1, $unitTop+21.5,$newUPC,3); //changes size //changed to 6 from 3 to move it down

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
  
  include('../src/footer.html');


?>
