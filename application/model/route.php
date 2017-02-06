<?php

/**
 * Fetch routes
 * @param int $vehicleType Jenis armada. 1 = mobil, 2 = bus kecil, 3 = bus besar, 4 = BRT
 * @return null|array NULL jika error, jika sukses kembali array objek
 */
function get_routes($vehicleType = -1) {
	global $mysqli;

	$condition = array();
	if ($vehicleType > 0) $condition['vehicle_type'] = _db_to_query($vehicleType);
	$selectQuery = db_select('public_routes', $condition);
	$queryResult = mysqli_query($mysqli, $selectQuery);

	if (!$queryResult) return null;
	$index = 0;
	$listRecord = array();

	while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
		$index = $row['id_route'];
		$listRecord[$index] = $row;
	}
	return $listRecord;
}

/**
 * Ambil data record trayek dengan id tertentu
 * @param integer $nodeId Node ID
 * @return array|FALSE Kembali array asosiatif dari record, atau FALSE jika gagal.
 */
function get_route_by_id($idRoute) {
	global $mysqli;

	$selectQuery = db_select('public_routes', array('id_route' => $idRoute));

	$result = mysqli_query($mysqli, $selectQuery);

	$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
	return $row;
}