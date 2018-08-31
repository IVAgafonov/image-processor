<?php 
	ini_set('memory_limit', '-1');
	
	$brightnessLimit = floatval($_POST["brightness"]);
	if(!$brightnessLimit) $brightnessLimit = (float)0.97;
	$minSize = intval($_POST["minSize"],10);
	if(!$minSize) $minSize = 500;
			
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
	
	//resize to maximum to increase search operation's speed
	$newHeight = $imHeight;
	$newWidth = $imWidth;
	
	$initialReduce = 2000;
	if($newWidth>$newHeight){
		if($newWidth>$initialReduce){
			$testResizeFactor = $initialReduce/$newWidth;
			$newHeight = round($newHeight*$testResizeFactor);
			$newWidth = $initialReduce;
		}
		else $testResizeFactor = 1;
	}
	else{
		if($newHeight>$initialReduce){
			$testResizeFactor = $initialReduce/$newHeight;
			$newWidth = round($newWidth*$testResizeFactor);
			$newHeight = $initialReduce;
		}
		else $testResizeFactor = 1;
	}
	
	$testimg = imagecreatetruecolor($newWidth, $newHeight);
	imagecopyresized($testimg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $imWidth, $imHeight);

	$borderRemove = 2;
	
	$b_top = $borderRemove;
	$b_btm = $borderRemove;
	$b_lft = $borderRemove;
	$b_rt = $borderRemove;
	
	//top
	for(; $b_top < $newHeight; ++$b_top) {
		for($x = $borderRemove; $x < $newWidth - $borderRemove; ++$x) {
			if(hexToLightness(imagecolorat($testimg, $x, $b_top))<$brightnessLimit){
				break 2; //out of the 'top' loop
			}
		}
	}
	
	//bottom
	for(; $b_btm < $newHeight; ++$b_btm) {
		for($x = $borderRemove; $x < $newWidth - $borderRemove; ++$x) {
			if(hexToLightness(imagecolorat($testimg, $x, $newHeight - $b_btm-1))<$brightnessLimit) {
				break 2; //out of the 'bottom' loop
			}
		}
	}
	
	//left
	for(; $b_lft < $newWidth; ++$b_lft) {
		for($y = $borderRemove; $y < $newHeight - $borderRemove; ++$y) {
			if(hexToLightness(imagecolorat($testimg, $b_lft, $y))<$brightnessLimit) {
				break 2; //out of the 'left' loop
			}
		}
	}
	
	//right
	for(; $b_rt < $newWidth; ++$b_rt) {
		for($y = $borderRemove; $y < $newHeight - $borderRemove; ++$y) {
			if(hexToLightness(imagecolorat($testimg, $newWidth - $b_rt-1, $y))<$brightnessLimit) {
				break 2; //out of the 'right' loop
			}
		}
	}
	
	$b_top/=$testResizeFactor;
	$b_btm/=$testResizeFactor;
	$b_lft/=$testResizeFactor;
	$b_rt/=$testResizeFactor;
	
	$touchingEdge = ($b_top == $borderRemove) || ($b_btm == $borderRemove) || ($b_lft == $borderRemove) || ($b_rt == $borderRemove);
		
	$actualHeight = $imHeight - $b_top - $b_btm;
	$actualWidth = $imWidth - $b_lft - $b_rt;
				
	//new image = actual image + 8% whitespace
	$addToTop = 0.08;
	$addToBottom = 0.08;
	if($b_top != $borderRemove || $b_btm != $borderRemove){
		if($b_top == $borderRemove) $addToTop=0;
		if($b_btm == $borderRemove) $addToBottom=0;
	}
	$newHeight = round($actualHeight/(1-$addToTop-$addToBottom));
		
	$addToLeft = 0.08;
	$addToRight = 0.08;
	if($b_lft != $borderRemove || $b_rt != $borderRemove){
		if($b_lft == $borderRemove) $addToLeft=0;
		if($b_rt == $borderRemove) $addToRight=0;
	}
	$newWidth = round($actualWidth/(1-$addToLeft-$addToRight));
						
	if($newHeight>$newWidth) {
		if($addToTop!=0) $addToTop = round($newHeight*$addToTop+$borderRemove);
		else $addToTop = 0;
				
		if($addToLeft!=0) $addToLeft = round($addToLeft*$newWidth + $borderRemove + ($addToLeft/($addToLeft + $addToRight))*($newHeight - $newWidth));
		else if($addToRight==0) $addToLeft = $borderRemove + ($newHeight - $newWidth);
		else $addToLeft = 0;
		$newWidth = $newHeight;
	}
	else {
		if($addToLeft!=0) $addToLeft = round($newWidth*$addToLeft+$borderRemove);
		else $addToLeft = 0;
		if($addToTop!=0) $addToTop = round($addToTop*$newHeight + $borderRemove + ($addToTop/($addToTop + $addToBottom))*($newWidth - $newHeight));
		else $addToTop = 0;
		$newHeight = $newWidth;
	}
					
	$diff_w = $newWidth - $actualWidth;
	$diff_h = $newHeight - $actualHeight;
				
	//not less than min size
	if($newWidth<$minSize){
		$diff_w = $diff_w + $minSize - $newWidth;
		$diff_h = $diff_h + $minSize - $newHeight;
		
		if($addToLeft!=0) $addToLeft = $addToLeft + ($addToLeft/($addToLeft + $addToRight))*($minSize - $newWidth);
		else if($addToRight==0) $addToLeft = $minSize - $newWidth;
		if($addToTop!=0) $addToTop = $addToTop + ($addToTop/($addToTop + $addToBottom))*($minSize - $newHeight);
		
		$newWidth = $minSize;		
		$newHeight = $minSize;
	}
	
	$dst_x = $addToLeft;
	$dst_y = $addToTop;
	$src_x = $b_lft;
	$src_y = $b_top;
	$dst_w = $newWidth - $diff_w;
	$dst_h = $newHeight - $diff_h;
	$src_w = $actualWidth;
	$src_h = $actualHeight;
						
	$result = imagecreatetruecolor($newWidth, $newHeight);
	$bg = imagecolorallocate ( $result, 255, 255, 255 );
	imagefilledrectangle($result,0,0, $newWidth, $newHeight, $bg);
	imagecopyresampled($result, $img, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
	imagedestroy($img);	
	
	if($_POST["noWatermark"]!=1) {
		$result = addWatermark($result, $newWidth, $newHeight, $brightnessLimit);
	}
	
	$numPack = intval($_POST["numPack"],10);
	if($numPack) applyCorner($result, $newWidth, $newHeight, $numPack);
	
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
	
	function addWatermark($img, $width, $height, $brightnessLimit){
		$MAX_WIDTH = round($width*0.7);
				
		$watermark = imagecreatefrompng('watermark.png');
				
		$originalWidth = imagesx($watermark);
		$originalHeight = imagesy($watermark);
		
		$watermarkHeight = round($height*0.08);
		$watermarkWidth = round($originalWidth*$watermarkHeight/$originalHeight);
				
		if($watermarkWidth>$MAX_WIDTH){
			$watermarkHeight = $watermarkHeight*$MAX_WIDTH/$watermarkWidth;
			$watermarkWidth = $MAX_WIDTH;
		}
		
		$water_dif_x = round(($width - $watermarkWidth)/2);				
		$start_y = round($height*0.91);
		
		if(($start_y+$watermarkHeight)>$height) $start_y = $height - $watermarkHeight;
				
		imagecopyresampled($img, $watermark, $water_dif_x, $start_y, 0, 0, $watermarkWidth, $watermarkHeight, $originalWidth, $originalHeight);
		return $img;
	}
	
	function applyCorner($result, $newWidth, $newHeight, $numPack){
		$redCorner = imagecreatefrompng('red.png');
		$redCornerHeight = imagesy($redCorner);
		$redCornerWidth = imagesx($redCorner);
		
		if($newWidth>$newHeight) $standardSize = $newHeight;
		else $standardSize = $newWidth;
		
		$dst_x = $newWidth - $standardSize;
		$dst_y = 0;
			
		$diff_y = $newHeight - $standardSize;
			
		$src_x = 0;
		$src_y = 0;
			
		imagecopyresampled($result, $redCorner, $dst_x, $dst_y, $src_x, $src_y, $standardSize, $newHeight - $diff_y, $redCornerWidth, $redCornerHeight);
			
		$font_path = 'ProximaNova-Extrabld.ttf';
		$font_size = $standardSize*0.06;
		$x = $dst_x + $standardSize*0.695;
		$y = $standardSize*0.064; 

		$strPack = (($numPack<10)?' ':'').$numPack.' PACK';
		$bg = imagecolorallocate ( $result, 255, 255, 255 );
		imagettftext($result, $font_size, -45, $x, $y, $bg, $font_path, $strPack);
		
		return $result;
	}
?>