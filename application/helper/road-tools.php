<?php

function get_road_edge($originCoord, $destCoord) {
	
}
function snap_road_api($pathCoords) {
	$coordData = '';
	
	foreach ($pathCoords as $coordItem) {
		$coordData .= sprintf("%f,%f|", $coordItem['lat'], $coordItem['lng']);
	}
	
	$coordData = trim($coordData, '|');
	
	
	//$ch = curl_init("https://roads.googleapis.com/v1/snapToRoads");
	
	/*$postFields = array(
			'interpolate' => true,
			'key' => 'AIzaSyB2LvXICy-Je6QQFgeIi32FnbA8r-dnqU4',
			'path' => $coordData
	);*/
	
	$postFields = array(
			'origin' => sprintf("%f,%f", $pathCoords[0]['lat'],$pathCoords[0]['lng']),
			'destination' => sprintf("%f,%f", $pathCoords[1]['lat'],$pathCoords[1]['lng']),
			'key' => 'AIzaSyB2LvXICy-Je6QQFgeIi32FnbA8r-dnqU4'
	);
	
	$postData = '';
	foreach ($postFields as $keyField => $itemField) {
		$postData .= $keyField.'='.urlencode($itemField).'&';
	}
	$postData = trim($postData, '&');
	
	//== Ambil data
	$requestPath = "https://maps.googleapis.com/maps/api/directions/json?".$postData;
	$ch = curl_init($requestPath);
	
	//curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	//curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_CAPATH, FCPATH . "assets");
	
	$jsonString = curl_exec($ch);
	
	if ($jsonString === false) {
		return curl_error($ch);		
	}
	curl_close($ch);
	
	return $jsonString;
}