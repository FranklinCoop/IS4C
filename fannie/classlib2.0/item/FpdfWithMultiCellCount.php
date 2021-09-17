<?php
/****Credit for the majority of what is below for barcode generation
 has to go to Valentin Schmidt for posting the script on the FPDF.org scripts
 webpage.****/

if (!class_exists('FPDF')) {
    include(dirname(__FILE__) . '/../../../src/fpdf/fpdf.php');
}
class FpdfWithMultiCellCount extends FPDF
{   

        // Sets line style
    // Parameters:
    // - style: Line style. Array with keys among the following:
    //   . width: Width of the line in user units
    //   . cap: Type of cap to put on the line (butt, round, square). The difference between 'square' and 'butt' is that 'square' projects a flat end past the end of the line.
    //   . join: miter, round or bevel
    //   . dash: Dash pattern. Is 0 (without dash) or array with series of length values, which are the lengths of the on and off dashes.
    //           For example: (2) represents 2 on, 2 off, 2 on , 2 off ...
    //                        (2,1) is 2 on, 1 off, 2 on, 1 off.. etc
    //   . phase: Modifier of the dash pattern which is used to shift the point at which the pattern starts
    //   . color: Draw color. Array with components (red, green, blue)
    function SetLineStyle($style) {
        extract($style);
        if (isset($width)) {
            $width_prev = $this->LineWidth;
            $this->SetLineWidth($width);
            $this->LineWidth = $width_prev;
        }
        if (isset($cap)) {
            $ca = array('butt' => 0, 'round'=> 1, 'square' => 2);
            if (isset($ca[$cap]))
                $this->_out($ca[$cap] . ' J');
        }
        if (isset($join)) {
            $ja = array('miter' => 0, 'round' => 1, 'bevel' => 2);
            if (isset($ja[$join]))
                $this->_out($ja[$join] . ' j');
        }
        if (isset($dash)) {
            $dash_string = '';
            if ($dash) {
                $tab = explode(',', $dash);
                $dash_string = '';
                foreach ($tab as $i => $v) {
                    if ($i > 0)
                        $dash_string .= ' ';
                    $dash_string .= sprintf('%.2F', $v);
                }
            }
            if (!isset($phase) || !$dash)
                $phase = 0;
            $this->_out(sprintf('[%s] %.2F d', $dash_string, $phase));
        }
        if (isset($color)) {
            list($r, $g, $b) = $color;
            $this->SetDrawColor($r, $g, $b);
        }
    }

        // Draws a rounded rectangle
    // Parameters:
    // - x, y: Top left corner
    // - w, h: Width and height
    // - r: Radius of the rounded corners
    // - round_corner: Draws rounded corner or not. String with a 0 (not rounded i-corner) or 1 (rounded i-corner) in i-position. Positions are, in order and begin to 0: top left, top right, bottom right and bottom left
    // - style: Style of rectangle (draw and/or fill) (D, F, DF, FD)
    // - border_style: Border style of rectangle. Array like for SetLineStyle
    // - fill_color: Fill color. Array with components (red, green, blue)
    function RoundedRect($x, $y, $w, $h, $r, $round_corner = '1111', $style = '', $border_style = null, $fill_color = null) {
        if ('0000' == $round_corner) // Not rounded
            $this->Rect($x, $y, $w, $h, $style, $border_style, $fill_color);
        else { // Rounded
            if (!(false === strpos($style, 'F')) && $fill_color) {
                list($red, $g, $b) = $fill_color;
                $this->SetFillColor($red, $g, $b);
            }
            switch ($style) {
                case 'F':
                    $border_style = null;
                    $op = 'f';
                    break;
                case 'FD': case 'DF':
                    $op = 'B';
                    break;
                default:
                    $op = 'S';
                    break;
            }
            if ($border_style)
                $this->SetLineStyle($border_style);

            $MyArc = 4 / 3 * (sqrt(2) - 1);

            $this->_Point($x + $r, $y);
            $xc = $x + $w - $r;
            $yc = $y + $r;
            $this->_Line($xc, $y);
            if ($round_corner[0])
                $this->_Curve($xc + ($r * $MyArc), $yc - $r, $xc + $r, $yc - ($r * $MyArc), $xc + $r, $yc);
            else
                $this->_Line($x + $w, $y);

            $xc = $x + $w - $r ;
            $yc = $y + $h - $r;
            $this->_Line($x + $w, $yc);

            if ($round_corner[1])
                $this->_Curve($xc + $r, $yc + ($r * $MyArc), $xc + ($r * $MyArc), $yc + $r, $xc, $yc + $r);
            else
                $this->_Line($x + $w, $y + $h);

            $xc = $x + $r;
            $yc = $y + $h - $r;
            $this->_Line($xc, $y + $h);
            if ($round_corner[2])
                $this->_Curve($xc - ($r * $MyArc), $yc + $r, $xc - $r, $yc + ($r * $MyArc), $xc - $r, $yc);
            else
                $this->_Line($x, $y + $h);

            $xc = $x + $r;
            $yc = $y + $r;
            $this->_Line($x, $yc);
            if ($round_corner[3])
                $this->_Curve($xc - $r, $yc - ($r * $MyArc), $xc - ($r * $MyArc), $yc - $r, $xc, $yc - $r);
            else {
                $this->_Line($x, $y);
                $this->_Line($x + $r, $y);
            }
            $this->_out($op);
        }
    }


    // Draws a rectangle where the top edge is a Bézier curve
    // Parameters:
    // - x0, y0: Start point
    // - x1, y1: Control point 1
    // - x2, y2: Control point 2
    // - x3, y3: End point of curve
    // - w, h: Height and Width 
    // - style: Style of rectangule (draw and/or fill: D, F, DF, FD)
    // - line_style: Line style for curve. Array like for SetLineStyle
    // - fill_color: Fill color. Array with components (red, green, blue)
    function topCurveRect($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3,$w,$h, $style = '', $line_style = null, $fill_color = null) {
        if (!(false === strpos($style, 'F')) && $fill_color) {
            list($r, $g, $b) = $fill_color;
            $this->SetFillColor($r, $g, $b);
        }
        switch ($style) {
            case 'F':
                $op = 'f';
                $line_style = null;
                break;
            case 'FD': case 'DF':
                $op = 'B';
                break;
            default:
                $op = 'S';
                break;
        }
        if ($line_style)
            $this->SetLineStyle($line_style);


        $this->_Point($x0,$y0);  //start point
        $this->_Curve($x1,$y1,$x2,$y2,$x3,$y3); // draw curve on top
        //if ($h-$y3 == 0) {
            $this->_Line($x0+$w,$y0+$h); // draw line
        //}
        $this->_Line($x0,$y0+$h);
        $this->_Line($x0,$y0);
       
        $this->_out($op);
    }

    // Draws a Bézier curve (the Bézier curve is tangent to the line between the control points at either end of the curve)
    // Parameters:
    // - x0, y0: Start point
    // - x1, y1: Control point 1
    // - x2, y2: Control point 2
    // - x3, y3: End point
    // - style: Style of rectangule (draw and/or fill: D, F, DF, FD)
    // - line_style: Line style for curve. Array like for SetLineStyle
    // - fill_color: Fill color. Array with components (red, green, blue)
    function Curve($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3, $style = '', $line_style = null, $fill_color = null) {
        if (!(false === strpos($style, 'F')) && $fill_color) {
            list($r, $g, $b) = $fill_color;
            $this->SetFillColor($r, $g, $b);
        }
        switch ($style) {
            case 'F':
                $op = 'f';
                $line_style = null;
                break;
            case 'FD': case 'DF':
                $op = 'B';
                break;
            default:
                $op = 'S';
                break;
        }
        if ($line_style)
            $this->SetLineStyle($line_style);

        $this->_Point($x0, $y0);
        $this->_Curve($x1, $y1, $x2, $y2, $x3, $y3);
        $this->_out($op);
    }

    // Draws a rectangle where the bottom edge is a Bézier curve
    // Parameters:
    // - x0, y0: Start point
    // - x1, y1: Control point 1
    // - x2, y2: Control point 2
    // - x3, y3: End point of curve
    // - w, h: Height and Width 
    // - style: Style of rectangule (draw and/or fill: D, F, DF, FD)
    // - line_style: Line style for curve. Array like for SetLineStyle
    // - fill_color: Fill color. Array with components (red, green, blue)
    function bottomCurveRect($x0, $y0, $x1, $y1, $x2, $y2, $x3, $y3,$w,$h, $style = '', $line_style = null, $fill_color = null) {
        if (!(false === strpos($style, 'F')) && $fill_color) {
            list($r, $g, $b) = $fill_color;
            $this->SetFillColor($r, $g, $b);
        }
        switch ($style) {
            case 'F':
                $op = 'f';
                $line_style = null;
                break;
            case 'FD': case 'DF':
                $op = 'B';
                break;
            default:
                $op = 'S';
                break;
        }
        if ($line_style)
            $this->SetLineStyle($line_style);


        $this->_Point($x0,$y0);
        $this->_Curve($x1,$y1,$x2,$y2,$x3,$y3);
        $this->_Line($x0, $y3);
        //$this->_Line($x0, $y0);
        //$this->_Line($x0+$w,$y0+$h);
        //$this->_Curve($x0+$w,$y0,$x2,$y2,$x0,$y0+$h);
        
        //$this->_Line($x0,$y0);

        //if ($h-$y3 == 0) {
            //$this->_Line($x0+$w,$y0+$h);
        //}
        //$this->_Line($x0,$y0+$h);
        
       
        $this->_out($op);
    }

    //Cell with horizontal scaling if text is too wide
    function CellFit($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='', $scale=false, $force=true)
    {
        //Get string width
        $str_width=$this->GetStringWidth($txt);

        //Calculate ratio to fit cell
        if($w==0)
            $w = $this->w-$this->rMargin-$this->x;
        $ratio = ($w-$this->cMargin*2)/$str_width;

        $fit = ($ratio < 1 || ($ratio > 1 && $force));
        if ($fit)
        {
            if ($scale)
            {
                //Calculate horizontal scaling
                $horiz_scale=$ratio*100.0;
                //Set horizontal scaling
                $this->_out(sprintf('BT %.2F Tz ET', $horiz_scale));
            }
            else
            {
                //Calculate character spacing in points
                $char_space=($w-$this->cMargin*2-$str_width)/max($this->MBGetStringLength($txt)-1, 1)*$this->k;
                //Set character spacing
                $this->_out(sprintf('BT %.2F Tc ET', $char_space));
            }
            //Override user alignment (since text will fill up cell)
            $align='';
        }

        //Pass on to Cell method
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

        //Reset character spacing/horizontal scaling
        if ($fit)
            $this->_out('BT '.($scale ? '100 Tz' : '0 Tc').' ET');
    }

        //Patch to also work with CJK double-byte text
    function MBGetStringLength($s)
    {
        if($this->CurrentFont['type']=='Type0')
        {
            $len = 0;
            $nbbytes = strlen($s);
            for ($i = 0; $i < $nbbytes; $i++)
            {
                if (ord($s[$i])<128)
                    $len++;
                else
                {
                    $len++;
                    $i++;
                }
            }
            return $len;
        }
        else
            return strlen($s);
    }
    function MultiCellRet($w,$h,$txt,$border=0,$align='J',$fill=0)
    {
        //Output text with automatic or explicit line breaks
        $cw=&$this->CurrentFont['cw'];
        if($w==0)
            $w=$this->w-$this->rMargin-$this->x;
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',$txt);
        $nb=strlen($s);
        if($nb>0 && $s[$nb-1]=="\n")
            $nb--;
        $b=0;
        if($border)
        {
            if($border==1)
            {
                $border='LTRB';
                $b='LRT';
                $b2='LR';
            }
            else
            {
                $b2='';
                if(strpos($border,'L')!==false)
                    $b2.='L';
                if(strpos($border,'R')!==false)
                    $b2.='R';
                $b=(strpos($border,'T')!==false) ? $b2.'T' : $b2;
            }
        }
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $ns=0;
        $nl=1;
        while($i<$nb)
        {
            //Get next character
            $c=$s[$i];
            if($c=="\n")
            {
                //Explicit line break
                if($this->ws>0)
                {
                    $this->ws=0;
                    $this->_out('0 Tw');
                }
                $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $ns=0;
                $nl++;
                if($border && $nl==2)
                    $b=$b2;
                continue;
            }
                if($c==' ')
            {
                $sep=$i;
                $ls=$l;
                $ns++;
            }
                $l+=$cw[$c];
            if($l>$wmax)
            {
                //Automatic line break
                if($sep==-1)
                {
                    if($i==$j)
                        $i++;
                    if($this->ws>0)
                    {
                        $this->ws=0;
                        $this->_out('0 Tw');
                    }
                    $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
                }
                else
                {
                    if($align=='J')
                    {
                        $this->ws=($ns>1) ? ($wmax-$ls)/1000*$this->FontSize/($ns-1) : 0;
                        $this->_out(sprintf('%.3f Tw',$this->ws*$this->k));
                    }
                    $this->Cell($w,$h,substr($s,$j,$sep-$j),$b,2,$align,$fill);
                    $i=$sep+1;
                }
                $sep=-1;
                $j=$i;
                $l=0;
                $ns=0;
                $nl++;
                if($border && $nl==2)
                    $b=$b2;
            }
            else
                $i++;
        }
        //Last chunk
        if($this->ws>0)
        {
            $this->ws=0;
            $this->_out('0 Tw');
        }
        if($border && strpos($border,'B')!==false)
            $b.='B';
        $this->Cell($w,$h,substr($s,$j,$i-$j),$b,2,$align,$fill);
        $this->x=$this->lMargin;
        return $nl;
    }

       /* PRIVATE METHODS */

    // Sets a draw point
    // Parameters:
    // - x, y: Point
    function _Point($x, $y) {
        $this->_out(sprintf('%.2F %.2F m', $x * $this->k, ($this->h - $y) * $this->k));
    }

    // Draws a line from last draw point
    // Parameters:
    // - x, y: End point
    function _Line($x, $y) {
        $this->_out(sprintf('%.2F %.2F l', $x * $this->k, ($this->h - $y) * $this->k));
    }

    // Draws a Bézier curve from last draw point
    // Parameters:
    // - x1, y1: Control point 1
    // - x2, y2: Control point 2
    // - x3, y3: End point
    function _Curve($x1, $y1, $x2, $y2, $x3, $y3) {
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k, $x3 * $this->k, ($this->h - $y3) * $this->k));
    }

	protected $gradients = array();

	function LinearGradient($x, $y, $w, $h, $col1=array(), $col2=array(), $coords=array(0,0,1,0)){
		$this->Clip($x,$y,$w,$h);
		$this->Gradient(2,$col1,$col2,$coords);
	}

	function RadialGradient($x, $y, $w, $h, $col1=array(), $col2=array(), $coords=array(0.5,0.5,0.5,0.5,1)){
		$this->Clip($x,$y,$w,$h);
		$this->Gradient(3,$col1,$col2,$coords);
	}

	function CoonsPatchMesh($x, $y, $w, $h, $col1=array(), $col2=array(), $col3=array(), $col4=array(), $coords=array(0.00,0.0,0.33,0.00,0.67,0.00,1.00,0.00,1.00,0.33,1.00,0.67,1.00,1.00,0.67,1.00,0.33,1.00,0.00,1.00,0.00,0.67,0.00,0.33), $coords_min=0, $coords_max=1){
		$this->Clip($x,$y,$w,$h);		
		$n = count($this->gradients)+1;
		$this->gradients[$n]['type']=6; //coons patch mesh
		//check the coords array if it is the simple array or the multi patch array
		if(!isset($coords[0]['f'])){
			//simple array -> convert to multi patch array
			if(!isset($col1[1]))
				$col1[1]=$col1[2]=$col1[0];
			if(!isset($col2[1]))
				$col2[1]=$col2[2]=$col2[0];
			if(!isset($col3[1]))
				$col3[1]=$col3[2]=$col3[0];
			if(!isset($col4[1]))
				$col4[1]=$col4[2]=$col4[0];
			$patch_array[0]['f']=0;
			$patch_array[0]['points']=$coords;
			$patch_array[0]['colors'][0]['r']=$col1[0];
			$patch_array[0]['colors'][0]['g']=$col1[1];
			$patch_array[0]['colors'][0]['b']=$col1[2];
			$patch_array[0]['colors'][1]['r']=$col2[0];
			$patch_array[0]['colors'][1]['g']=$col2[1];
			$patch_array[0]['colors'][1]['b']=$col2[2];
			$patch_array[0]['colors'][2]['r']=$col3[0];
			$patch_array[0]['colors'][2]['g']=$col3[1];
			$patch_array[0]['colors'][2]['b']=$col3[2];
			$patch_array[0]['colors'][3]['r']=$col4[0];
			$patch_array[0]['colors'][3]['g']=$col4[1];
			$patch_array[0]['colors'][3]['b']=$col4[2];
		}
		else{
			//multi patch array
			$patch_array=$coords;
		}
		$bpcd=65535; //16 BitsPerCoordinate
		//build the data stream
		$this->gradients[$n]['stream']='';
		for($i=0;$i<count($patch_array);$i++){
			$this->gradients[$n]['stream'].=chr($patch_array[$i]['f']); //start with the edge flag as 8 bit
			for($j=0;$j<count($patch_array[$i]['points']);$j++){
				//each point as 16 bit
				$patch_array[$i]['points'][$j]=(($patch_array[$i]['points'][$j]-$coords_min)/($coords_max-$coords_min))*$bpcd;
				if($patch_array[$i]['points'][$j]<0) $patch_array[$i]['points'][$j]=0;
				if($patch_array[$i]['points'][$j]>$bpcd) $patch_array[$i]['points'][$j]=$bpcd;
				$this->gradients[$n]['stream'].=chr(floor($patch_array[$i]['points'][$j]/256));
				$this->gradients[$n]['stream'].=chr(floor($patch_array[$i]['points'][$j]%256));
			}
			for($j=0;$j<count($patch_array[$i]['colors']);$j++){
				//each color component as 8 bit
				$this->gradients[$n]['stream'].=chr($patch_array[$i]['colors'][$j]['r']);
				$this->gradients[$n]['stream'].=chr($patch_array[$i]['colors'][$j]['g']);
				$this->gradients[$n]['stream'].=chr($patch_array[$i]['colors'][$j]['b']);
			}
		}
		//paint the gradient
		$this->_out('/Sh'.$n.' sh');
		//restore previous Graphic State
		$this->_out('Q');
	}

	function Clip($x,$y,$w,$h){
		//save current Graphic State
		$s='q';
		//set clipping area
		$s.=sprintf(' %.2F %.2F %.2F %.2F re W n', $x*$this->k, ($this->h-$y)*$this->k, $w*$this->k, -$h*$this->k);
		//set up transformation matrix for gradient
		$s.=sprintf(' %.3F 0 0 %.3F %.3F %.3F cm', $w*$this->k, $h*$this->k, $x*$this->k, ($this->h-($y+$h))*$this->k);
		$this->_out($s);
	}

	function Gradient($type, $col1, $col2, $coords){
		$n = count($this->gradients)+1;
		$this->gradients[$n]['type']=$type;
		if(!isset($col1[1]))
			$col1[1]=$col1[2]=$col1[0];
		$this->gradients[$n]['col1']=sprintf('%.3F %.3F %.3F',($col1[0]/255),($col1[1]/255),($col1[2]/255));
		if(!isset($col2[1]))
			$col2[1]=$col2[2]=$col2[0];
		$this->gradients[$n]['col2']=sprintf('%.3F %.3F %.3F',($col2[0]/255),($col2[1]/255),($col2[2]/255));
		$this->gradients[$n]['coords']=$coords;
		//paint the gradient
		$this->_out('/Sh'.$n.' sh');
		//restore previous Graphic State
		$this->_out('Q');
	}

	function _putshaders(){
    	foreach($this->gradients as $id=>$grad){  
    		if($grad['type']==2 || $grad['type']==3){
                $this->_newobj();
                $this->_put('<<');
                $this->_put('/FunctionType 2');
                $this->_put('/Domain [0.0 1.0]');
                $this->_put('/C0 ['.$grad['col1'].']');
                $this->_put('/C1 ['.$grad['col2'].']');
                $this->_put('/N 1');
                $this->_put('>>');
                $this->_put('endobj');
                $f1=$this->n;
            }
            
			$this->_newobj();
            $this->_put('<<');
            $this->_put('/ShadingType '.$grad['type']);
            $this->_put('/ColorSpace /DeviceRGB');
            if($grad['type']=='2'){
            	$this->_put(sprintf('/Coords [%.3F %.3F %.3F %.3F]',$grad['coords'][0],$grad['coords'][1],$grad['coords'][2],$grad['coords'][3]));
            	$this->_put('/Function '.$f1.' 0 R');
            	$this->_put('/Extend [true true] ');
            	$this->_put('>>');
            }
            elseif($grad['type']==3){
            	//x0, y0, r0, x1, y1, r1
            	//at this time radius of inner circle is 0
            	$this->_put(sprintf('/Coords [%.3F %.3F 0 %.3F %.3F %.3F]',$grad['coords'][0],$grad['coords'][1],$grad['coords'][2],$grad['coords'][3],$grad['coords'][4]));
            	$this->_put('/Function '.$f1.' 0 R');
            	$this->_put('/Extend [true true] ');
            	$this->_put('>>');
            }
            elseif($grad['type']==6){
				$this->_put('/BitsPerCoordinate 16');
				$this->_put('/BitsPerComponent 8');
				$this->_put('/Decode[0 1 0 1 0 1 0 1 0 1]');
				$this->_put('/BitsPerFlag 8');
				$this->_put('/Length '.strlen($grad['stream']));
				$this->_put('>>');
				$this->_putstream($grad['stream']);
            }
            $this->_put('endobj');
            $this->gradients[$id]['id']=$this->n;
    	}
	}

	function _putresourcedict(){
		parent::_putresourcedict();
		$this->_put('/Shading <<');
		foreach($this->gradients as $id=>$grad)
 			$this->_put('/Sh'.$id.' '.$grad['id'].' 0 R');
		$this->_put('>>');
	}

	function _putresources(){
		$this->_putshaders();
		parent::_putresources();
	}
    
}



?>