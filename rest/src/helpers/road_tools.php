<?php

/**
 * Request map direction API
 * 
 * @param array $originCoord LatLng awal
 * @param array $destCoord LatLng tujuan
 * @return string|null Kembali JSON feedback jika request berhasil, sebaliknya NULL.
 */
function map_direction_api($originCoord, $destCoord) {
	$postData = '';
	
	$postFields = array(
			'origin'		=> sprintf("%f,%f", $originCoord['lat'], $originCoord['lng']),
			'destination'	=> sprintf("%f,%f", $destCoord['lat'], $destCoord['lng']),
			'key'			=> GOOGLE_APIKEY
	);
	
	foreach ($postFields as $keyField => $itemField) {
		$postData .= $keyField.'='.urlencode($itemField).'&';
	}
	$postData = trim($postData, '&');
	
	//== Ambil data
	$requestPath = "https://maps.googleapis.com/maps/api/directions/json?".$postData;
	$ch = curl_init($requestPath);
	
	//curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_CAPATH, FCPATH . "assets");
	
	$jsonString = curl_exec($ch);
	if ($jsonString === false) {
		return null; //curl_error($ch);
	}
	curl_close($ch);
	
	return $jsonString;
}
/**
 * Call snap to road Google API
 * @param array $pathCoords Array of LatLng
 * @return NULL|string Kembali JSON feedback jika request berhasil, atau NULL jika gagal.
 */
function snap_road_api($pathCoords, $interpolate = true) {
	$coordData = '';
	
	foreach ($pathCoords as $coordItem) {
		$coordData .= sprintf("%f,%f|", $coordItem['lat'], $coordItem['lng']);
	}
	
	$coordData = trim($coordData, '|');
	
	$postFields = array(
			'interpolate' => $interpolate,
			'key' => GOOGLE_APIKEY,
			'path' => $coordData
	);
	
	$postData = "";
	foreach ($postFields as $keyField => $itemField) {
		$postData .= $keyField.'='.urlencode($itemField).'&';
	}
	$postData = trim($postData, '&');
	
	//== Ambil data
	$requestPath = "https://roads.googleapis.com/v1/snapToRoads";
	$ch = curl_init($requestPath);
	
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	//curl_setopt($ch, CURLOPT_CAPATH, FCPATH . "assets");
	
	$jsonString = curl_exec($ch);
	
	if ($jsonString === false) {
		return null; //curl_error($ch);		
	}
	curl_close($ch);
	
	return $jsonString;
}