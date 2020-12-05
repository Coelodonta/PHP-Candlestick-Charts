<?php
/**** BEGIN DRIVER CODE FOR STANDALONE IMAGE GENERATION *****/
$use_driver=TRUE; // Set to FALSE to use the Candles class without this dricer code

if($use_driver){
	// The chart symbol
	$symbol=$_GET['symbol'];
	// The number of candles we should display
	$n=$_GET['n'];

	// Read data
	$path="./";
	$file="$path/$symbol.csv";
	if(FALSE===file_exists($file)){
		echo "No data available for $symbol";
		exit(0);
	}

	$content = file_get_contents($file);
	$lines=explode("\n",$content);

	// Try to show at least 10 candles
	//$n=max(11,$n);
	// But we can't show more candles than we have data
	$n=min($n,count($lines));

	$cdls=new Candles();

	$cdls->generateOHLCV($n,$lines);

	// This is how to use the parameter $show
	$show=TRUE;
	if($show){
		// Generate image and show immediately
		$cdls->generateImage($n,$symbol,$lines);
	}
	else{
		// Generate image, and use explicit code to show it
		// For example if the class is used without this driver code to generate images
		$im=$cdls->generateImage($n,$symbol,$lines,FALSE);
		header("Content-type: image/png");
		imagepng($im);
		imagedestroy($im);
	}
}
/**** END DRIVER CODE *****/


class Candles{

	public $max_price=0;
	public $min_price=100000;
	public $max_vol=0;
	public $open_price=array();
	public $close_price=array();
	public $high_price=array();
	public $low_price=array();
	public $vol=array();


	public function generateOHLCV($n,$lines){
		// Slice off first line if it's a header
		if(ctype_alpha($lines[0][0])){
			array_shift($lines);
		}

		for($i=0;$i<$n;$i++){
			$line=explode(",",$lines[$i]);
			$this->max_price=max($line[2],$this->max_price);
			$this->min_price=min($line[3],$this->min_price);
			$this->max_vol=max($line[5],$this->max_vol);
			$this->open_price[$i]=$line[1];
			$this->close_price[$i]=$line[4];
			$this->high_price[$i]=$line[2];
			$this->low_price[$i]=$line[3];
			$this->vol[$i]=$line[5];
		}

	}

	# Generate the image
	public function generateImage($n,$symbol,$lines,$show=TRUE){
		if($show){
			header("Content-type: image/png");
		}

		$width = 600;
		$height = 300;
		$height2 = 100;
		$height3 =0;
		$margin = 50;

		$diff=$this->max_price-$this->min_price;
		$units=array(0.05,0.1,0.5,1,5,10,50,100);
		for($i=0;$i<7;$i++){
			if($diff/$units[$i]<=20){
				$unit=$units[$i];
				break;
			}
		}
		$l_bound=floor($this->min_price/$unit)*$unit;
		$u_bound=ceil($this->max_price/$unit)*$unit;

		$im = imagecreatetruecolor($width+50, $height+$height2+$height3);
		$red = imagecolorallocate($im, 255, 0, 0);
		$gray = imagecolorallocate($im, 220, 220, 220);
		$green = imagecolorallocate($im, 0, 160, 0);
		$blue = imagecolorallocate($im, 0, 0, 255);
		$black = imagecolorallocate($im, 0, 0, 0);
		$white = imagecolorallocate($im, 255, 255, 255);

		imagefill($im,0,0,$white);

		imagestring($im,5,$width/4-100,$margin/2,$symbol,$blue);
		$t1=substr($lines[0],0,16);
		$t2=substr($lines[$n-1],0,16);
		imagestring($im,3,$width/4-25,$margin/2,"($t1 - $t2)",$black);

		$n_grid=($u_bound-$l_bound)/$unit;
		for($i=1;$i<$n_grid;$i++){
			imageline($im,$margin/2,$margin+$i*($height-2*$margin)/$n_grid,$width-1.5*$margin,$margin+$i*($height-2*$margin)/$n_grid,$gray);
			imagestring($im,3,$width-1.5*$margin+5,$margin+$i*($height-2*$margin)/$n_grid-10,$u_bound-$i*$unit,$black);
		}


		$v_step=$this->max_vol/10;
		for($i=1;$i<10;$i++){
			$val=round($this->max_vol-($i*$v_step));
			imageline($im,$margin/2,$height-$margin+$i*$height2/10,$width-1.5*$margin,$height-$margin+$i*$height2/10,$gray);
			imagestring($im,3,$width-1.5*$margin+5,$height-$margin+$i*$height2/10-10,$val,$black);
		}

		imageline($im,$margin/2,$margin,$margin/2,$height-$margin+$height2+$height3,$black);
		imageline($im,$margin/2,$margin,$width-1.5*$margin,$margin,$black);
		imageline($im,$width-1.5*$margin,$margin,$width-1.5*$margin,$height-$margin+$height2+$height3,$black);
		imageline($im,$margin/2,$height-$margin,$width-1.5*$margin,$height-$margin,$black);
		imageline($im,$margin/2,$height-$margin+$height2,$width-1.5*$margin,$height-$margin+$height2,$black);


		// Draw the candles
		$c_width=($width-2*$margin)/($n+1);
		$j=0;
		for($i=0;$i<$n;$i++){
			$color=$this->close_price[$i]<=$this->open_price[$i]?$red:$green;
			$j+=1;

			// Wicks
			imagefilledrectangle($im,$margin/2+$j*$c_width,$margin+($u_bound-$this->high_price[$i])*($height-2*$margin)/($u_bound-$l_bound),$margin/2+$j*$c_width,$margin+($u_bound-$this->low_price[$i])*($height-2*$margin)/($u_bound-$l_bound),$color);

			// Body
			imagefilledrectangle($im,$margin/2+$j*$c_width-1.5,$margin+($u_bound-$this->open_price[$i])*($height-2*$margin)/($u_bound-$l_bound),$margin/2+$j*$c_width+2,$margin+($u_bound-$this->close_price[$i])*($height-2*$margin)/($u_bound-$l_bound),$color);

			// Volume
			$v=100*($this->vol[$i]/$this->max_vol);
			imagefilledrectangle($im,
				$margin/2+$j*$c_width-1.5,
				$height-$margin+(100-$v)*$height2/100,$margin/2+$j*$c_width+2,$height-$margin+100*$height2/100,$color);

		}
		if($show){
			imagepng($im);
			imagedestroy($im);
		}
		else{
			return $im;
		}
	}
}
?>