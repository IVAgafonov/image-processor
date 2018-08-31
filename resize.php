<?php 
	ini_set('memory_limit', '-1');

	$maxSize = intval($_POST["maxSize"],10);
	if(!$maxSize) $maxSize = 1600;	
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
	
	$initialReduce = $maxSize;
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
	imagecopyresampled($testimg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $imWidth, $imHeight);
	
	$quality = 100;
	if(intval($_POST["optimize"],10)) $quality = 90;
		
	header("Content-Type: image/jpeg");
	imagejpeg($testimg, null, $quality);
	imagedestroy($testimg);
	
	function convertFromPNG($input, $width, $height){
		$output = imagecreatetruecolor($width, $height);
		$white = imagecolorallocate($output,  255, 255, 255);
		imagefilledrectangle($output, 0, 0, $width, $height, $white);
		imagecopy($output, $input, 0, 0, 0, 0, $width, $height);
		
		return $output;
	}
?>