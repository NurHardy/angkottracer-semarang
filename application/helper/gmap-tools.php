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

/**
 * Encode kompresi polyline milik GoogleMap
 * 
 * @param array $points Array of point (lat: , lng:)
 * @return string Encoded string
 */
function encode_polyline ($points) {
	$encodedString = '';
	$index = 0;
	$previous = array(0, 0);
	foreach ( $points as $itemPoint ) {
		$number = (float)($itemPoint['lat']);
		$number = (int)round($number * pow(10, 5));
		$diff = $number - $previous[$index % 2];
		$previous[$index % 2] = $number;
		$number = $diff;
		$index++;
		$number = ($number < 0) ? ~($number << 1) : ($number << 1);
		$chunk = '';
		while ( $number >= 0x20 ) {
			$chunk .= chr((0x20 | ($number & 0x1f)) + 63);
			$number >>= 5;
		}
		$chunk .= chr($number + 63);
		$encodedString .= $chunk;
		
		$number = (float)($itemPoint['lng']);
		$number = (int)round($number * pow(10, 5));
		$diff = $number - $previous[$index % 2];
		$previous[$index % 2] = $number;
		$number = $diff;
		$index++;
		$number = ($number < 0) ? ~($number << 1) : ($number << 1);
		$chunk = '';
		while ( $number >= 0x20 ) {
			$chunk .= chr((0x20 | ($number & 0x1f)) + 63);
			$number >>= 5;
		}
		$chunk .= chr($number + 63);
		$encodedString .= $chunk;
	}
	return $encodedString;
}