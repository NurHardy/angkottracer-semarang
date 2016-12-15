<?php

/**
 * Decode kompresi polyline milik GoogleMap
 * @param string $encoded Encoded Polyline
 */
function decode_polyline($encoded) {
	$poly = array();
	$index = 0; $len = strlen($encoded);
	$lat = 0; $lng = 0;

	while ($index < $len) {
		$b = 0; $shift = 0; $result = 0;
		do {
			$b = ord($encoded[$index++]) - 63;
			$result = $result | ($b & 0x1f) << $shift;
			$shift += 5;
		} while ($b >= 0x20);
		$dlat = (($result & 1) != 0 ? ~($result >> 1) : ($result >> 1));
		$lat += $dlat;

		$shift = 0;
		$result = 0;
		do {
			$b = ord($encoded[$index++]) - 63;
			$result = $result | ($b & 0x1f) << $shift;
			$shift += 5;
		} while ($b >= 0x20);
		$dlng = (($result & 1) != 0 ? ~($result >> 1) : ($result >> 1));
		$lng += $dlng;

		$poly[] = array('lat' => ($lat / 1e5), 'lng' => ($lng / 1e5));
	}
	return $poly;
}