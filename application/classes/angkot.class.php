<?php

/**
 * Kelas Edge
 * @author Nur Hardyanto
 *
 */
class Edge {
	var $idEdge;
	var $edgeName;
	var $latLng;
	
	function __construct($resultRow) {
		$this->idEdge = $resultRow['id_edge'];
		$this->edgeName = $resultRow['edge_name'];
	}
	
	function toJSON() {
		return json_encode(array(
			
		));
	}
}
	
/**
 * Kelas LatLng
 * @author Nur Hardyanto
 *
 */
class LatLng {
	var $lat;
	var $lng;
	
	function __construct($posArray) {
		if (is_array($posArray)) {
			$this->lat = $posArray['lat'];
			$this->lng = $posArray['lng'];
		}
	}
	function __construct($lat_, $lng_) {
		$this->lat = $lat_;
		$this->lng = $lng_;
	}
}