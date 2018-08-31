<?php 
	ini_set('memory_limit', '-1');
	
	$brightnessLimit = floatval($_POST["brightness"]);
	if(!$brightnessLimit) $brightnessLimit = (float)0.97;
		
	$url = $_POST['link'];
	
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
	$img = curl_exec($ch); 
	curl_close($ch); 
	
	$img = imagecreatefromstring($img);
		
	$imHeight = imagesy($img);
	$imWidth = imagesx($img);
	
	if($_POST['png']=='1') $img = convertFromPNG($img, $imWidth, $imHeight);
		
	$result = addWatermark($img, $imWidth, $imHeight, $brightnessLimit, $_POST['ebay']=='1');
	
	$quality = 100;
	if(intval($_POST["optimize"],10)) $quality = 90;
		
	header("Content-Type: image/jpeg");
	imagejpeg($result, null, $quality);
	imagedestroy($result);
	
	function hexToLightness( $hex ) {
		$r = ($hex >> 16) & 0xFF;
		$g = ($hex >> 8) & 0xFF;
		$b = $hex & 0xFF;
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		
		$l = ( $max + $min ) / 2;
		return $l/255;
	}
	
	function convertFromPNG($input, $width, $height){
		$output = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($output,  255, 255, 255);
		imagefilledrectangle($output, 0, 0, $width, $height, $white);
		imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
		
		return $output;
	}
	
	function addWatermark($img, $width, $height, $brightnessLimit, $ebay){
		$MAX_HEIGHT = round($height*0.1);
		$MIN_HEIGHT = round($height*0.05);
		$MAX_WIDTH = round($width*0.7);
				
		$watermark = ($ebay)?imagecreatefrompng('watermark.png'):imagecreatefrompng('watermark2.png');
				
		$originalWidth = imagesx($watermark);
		$originalHeight = imagesy($watermark);
		
		$watermarkWidth = round($width*0.333);
		$watermarkHeight = round($originalHeight*$watermarkWidth/$originalWidth);
		
		if($watermarkHeight<$MIN_HEIGHT) {
			$watermarkWidth = round($watermarkWidth*$MIN_HEIGHT/$watermarkHeight);
			$watermarkHeight = $MIN_HEIGHT;
		}
		else if($watermarkHeight>$MAX_HEIGHT){
			$watermarkWidth = round($watermarkWidth*$MAX_HEIGHT/$watermarkHeight);
			$watermarkHeight = $MAX_HEIGHT;
		}
		
		if($watermarkWidth>$MAX_WIDTH){
			$watermarkHeight = $watermarkHeight*$MAX_WIDTH/$watermarkWidth;
			$watermarkWidth = $MAX_WIDTH;
		}
		
		$water_dif_x = round(($width - $watermarkWidth)/2);
		$start_x = $water_dif_x + $watermarkWidth*0.1;
		$end_x = $width - $water_dif_x - $watermarkWidth*0.1;
				
		$start_y = ($ebay)?round($height-$watermarkHeight-$height*0.05):findWatermarkY($img, $height, $start_x, $end_x, $brightnessLimit) - $watermarkHeight*0.5;		
		if($start_y<0) $start_y = round($height-$watermarkHeight-$height*0.05);		
		if(($start_y+$watermarkHeight)>$height) $start_y = $height - $watermarkHeight;
				
		imagecopyresampled($img, $watermark, $water_dif_x, $start_y, 0, 0, $watermarkWidth, $watermarkHeight, $originalWidth, $originalHeight);
		return $img;
	}
	
	function findWatermarkY($img, $imHeight, $start_x, $end_x, $brightnessLimit){
		for($b_btm = 0; $b_btm < $imHeight; ++$b_btm) {
			for($x = $start_x; $x < $end_x; ++$x) {
				if(hexToLightness(imagecolorat($img, $x, $imHeight - $b_btm - 1))<$brightnessLimit) {
					return $imHeight - $b_btm - 1;
				}
			}
		}
	}
?>