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