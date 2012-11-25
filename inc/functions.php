<?php
include("config.inc.php");
include("Uploaded.php");

// getSize
function getSize($size) {
	$round = 2;
	if ($size<=1024) $size = $size." Byte";
	else if ($size<=1024000) $size = round($size/1024,$round)." KB";
	else if ($size<=1048576000) $size = round($size/1048576,$round)." MB";
	else if ($size<=1073741824000) $size = round($size/1073741824,$round)." GB";
	$size = explode(" ", $size);
	$size = number_format($size[0], $round, '.', '')." ".$size[1];
	return $size;
}

// time2str()
function time2str($time, $abs=false, $sek=false, $anz=9) {
	$str = "";
	if($time > (60 * 60 * 24 * 30 * 12) && $anz > 0) {
		$anz--;
		$days = floor($time / (60 * 60 * 24 * 30 * 12));
		if($days == "1") {
			$str .= $days."y ";
		 }else{
			$str .= $days."y ";
		}
		$time = $time - ((60 * 60 * 24 * 30 * 12) * $days);
	}

	if($time > (60 * 60 * 24 * 30) && $anz > 0) {
		$anz--;
		$days = floor($time / (60 * 60 * 24 * 30));
		if($days == "1") {
			$str .= $days."m ";
		 }else{
			$str .= $days."m ";
		}
		$time = $time - ((60 * 60 * 24 * 30) * $days);
	}

	if($time > (60 * 60 * 24) && $anz > 0) {
		$anz--;
		$days = floor($time / (60 * 60 * 24));
		if($days == "1") {
			$str .= $days."d ";
		 }else{
			$str .= $days."d ";
		}
		$time = $time - ((60 * 60 * 24) * $days);
	}

	if($time > (60 * 60) && $anz > 0) {
		$anz--;
		$stunden = floor($time / (60 * 60));
		if($stunden == "1") {
			$str .= $stunden."h ";
		 }else{
			$str .= $stunden."h ";
		}
		$time = $time - ((60*60) * $stunden);
	}

	if(empty($str)) { $sek = true; }

	if($time > (60) && $anz > 0) {
		$anz--;
		$min = floor($time / (60));
		if($min == "1") {
			$str .= $min."m ";
		 }else{
			$str .= $min."m ";
		}
		$time = $time - ((60) * $min);
	}

	if($time > 1 && $anz > 0 && $sek){
		$anz--;
		if($time == "1") {
			$str .= $time."s";
		 }else{
			$str .= $time."s";
		}
	}

	if(substr($str, -1) == " ") {
		$str = substr($str, 0, strlen($str)-1);
	}
	return $str;
}
?>