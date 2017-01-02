<?php

	function kml_coords_to_mysql($paramCoordStr) {
		$coordStr = trim($paramCoordStr);
		$points = explode(' ', $coordStr);
		$resultStr = "";
		
		if (empty($points)) return "NULL";
		
		$resultStr = 'LINESTRING(';
		foreach ($points as $itemPoint) {
			$geoData = explode(',', $itemPoint);
			if (count($geoData) == 3) {
				$resultStr .= $geoData[0].' '.$geoData[1].',';
			}
		}
		$resultStr = trim($resultStr, ',');
		$resultStr .= '),0';
		return $resultStr;
	}
	
	/**
	 * Ubah posisi lat, lng ke MySQL geometri (POINT)
	 * @param float $nodePosLng Longitude
	 * @param float $nodePosLat Latitude
	 * @return string String MySQL
	 */
	function latlng_point_to_mysql($nodePosLng, $nodePosLat) {
		return sprintf("POINT(%f %f)", $nodePosLng, $nodePosLat);
	}
	
	function latlng_coords_to_mysql($paramCoords) {
		$resultStr = "";
		
		if (empty($paramCoords)) return "NULL";
		
		$resultStr = 'LINESTRING(';
		foreach ($paramCoords as $itemPoint) {
			$nodeStr = sprintf("%f %f", $itemPoint['lng'], $itemPoint['lat']);
			$resultStr .= $nodeStr.',';
		}
		$resultStr = trim($resultStr, ',');
		$resultStr .= '),0';
		return $resultStr;
	}
	
	function mysql_to_latlng_coords($mysqlLineString) {
		if (empty($mysqlLineString)) return array();
		// 12345678901
		// LINESTRING(...)
		// 0123456789
		
		$coordResult = array();
		$cleanStr = trim($mysqlLineString);
		
		// Hilangkan LINESTRING
		$cleanStr = substr($cleanStr, 10, strlen($mysqlLineString)-11);
		
		// Hilangkan kurung
		$cleanStr = trim($cleanStr, '()');
		
		$listVertex = explode(',', $cleanStr);
		foreach ($listVertex as $itemVertex) {
			$vertexComp = explode(' ', $itemVertex);
			$coordResult[] = array(
				'lng' => floatval($vertexComp[0]),
				'lat' => floatval($vertexComp[1])
			);
		}
		
		return $coordResult;
		
	}
	//Source: https://www.geodatasource.com/developers/php
	/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
	/*::                                                                         :*/
	/*::  This routine calculates the distance between two points (given the     :*/
	/*::  latitude/longitude of those points). It is being used to calculate     :*/
	/*::  the distance between two locations using GeoDataSource(TM) Products    :*/
	/*::                                                                         :*/
	/*::  Definitions:                                                           :*/
	/*::    South latitudes are negative, east longitudes are positive           :*/
	/*::                                                                         :*/
	/*::  Passed to function:                                                    :*/
	/*::    lat1, lon1 = Latitude and Longitude of point 1 (in decimal degrees)  :*/
	/*::    lat2, lon2 = Latitude and Longitude of point 2 (in decimal degrees)  :*/
	/*::    unit = the unit you desire for results                               :*/
	/*::           where: 'M' is statute miles (default)                         :*/
	/*::                  'K' is kilometers                                      :*/
	/*::                  'N' is nautical miles                                  :*/
	/*::  Worldwide cities and other features databases with latitude longitude  :*/
	/*::  are available at http://www.geodatasource.com                          :*/
	/*::                                                                         :*/
	/*::  For enquiries, please contact sales@geodatasource.com                  :*/
	/*::                                                                         :*/
	/*::  Official Web site: http://www.geodatasource.com                        :*/
	/*::                                                                         :*/
	/*::         GeoDataSource.com (C) All Rights Reserved 2015		   		     :*/
	/*::                                                                         :*/
	/*::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/
	function distance($lat1, $lon1, $lat2, $lon2, $unit) {
	
		$theta = $lon1 - $lon2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		$miles = $dist * 60 * 1.1515;
		$unit = strtoupper($unit);
	
		if ($unit == "K") {
			return ($miles * 1.609344);
		} else if ($unit == "N") {
			return ($miles * 0.8684);
		} else {
			return $miles;
		}
	}
	
	/**
	 * Hitung panjang polyline
	 * 
	 * @param array $coordsData Array of point (lat:, lng:)
	 * @param char $unit M = mile, K = kilometer
	 * @return NULL|float Jarak dalam satuan yang dimaksud, NULL jika error.
	 */
	function polyline_length($coordsData, $unit) {
		if (!is_array($coordsData)) return null;
		
		$distance = 0.0; $idxCounter = 0;
		
		$pointsCount = count($coordsData);
		for ($idxCounter = 1; $idxCounter < $pointsCount; $idxCounter++) {
			$distance += distance(
					$coordsData[$idxCounter-1]['lat'], $coordsData[$idxCounter-1]['lng'],
					$coordsData[$idxCounter]['lat'], $coordsData[$idxCounter]['lng'],
					$unit);
		}
		
		return $distance;
	}
	//Example usage:
	//echo distance(32.9697, -96.80322, 29.46786, -98.53506, "M") . " Miles<br>";
	//echo distance(32.9697, -96.80322, 29.46786, -98.53506, "K") . " Kilometers<br>";
	//echo distance(32.9697, -96.80322, 29.46786, -98.53506, "N") . " Nautical Miles<br>";
	