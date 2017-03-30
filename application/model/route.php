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

/**
 * Hapus cache edge assign untuk route tertentu.
 * @param unknown $idRoute
 * @return unknown
 */
function clear_route_edges($idRoute) {
	global $mysqli;
	
	$delQuery = db_delete_where('route_edges', array('id_route' => $idRoute));
	$result = mysqli_query($mysqli, $delQuery);
	return $result;
}

/**
 * Simpan record trayek/route
 * @param array $routeData Field route yang ingin disimpan/diupdate
 * @param int $routeId ID trayek/route
 * @return int|NULL Kembali ID route jika berhasil, atau NULL jika gagal.
 */
function save_route($routeData, $routeId = -1) {
	global $mysqli;

	$routeFields = array();
	foreach ($routeData as $propKey => $propValue) {
		$routeFields[$propKey] = $propValue;
	}
	$saveQuery = "";
	if ($routeId > 0) {
		$saveQuery = db_update('public_routes', $routeFields, array('id_route' => $routeId));
	} else {
		if (!isset($routeFields['date_created']))
			$routeFields['date_created'] = _db_to_query(date('Y-m-d H:i:s'));
		$saveQuery = db_insert_into('public_routes', $routeFields);
	}

	$queryResult = mysqli_query($mysqli, $saveQuery);
	if ($queryResult) {
		if ($routeId > 0) {
			return $routeId;
		} else {
			$newId = mysqli_insert_id($mysqli);
			return $newId;
		}
	} else return null;
}

/**
 * Ambil list edge berdasar ID route
 * @param int $idRoute ID route
 * @return NULL|array[]
 */
function get_route_edges($idRoute, $joinEdge = false) {
	global $mysqli;

	$condition = array();
	$condition['id_route'] = _db_to_query($idRoute);
	
	//-- Default values
	$joinQuery = null;
	$fieldToSelect = "*";
	
	if ($joinEdge) {
		$joinQuery = ' INNER JOIN edges ON edges.id_edge=route_edges.id_edge ';
		$fieldToSelect = _gen_fields(array(
				0 => 'route_edges.*',
				'id_node_from' => 'edges.id_node_from',
				'id_node_dest' => 'edges.id_node_dest',
				'id_road' => 'edges.id_road',
				'polyline_data' => 'AsText(edges.polyline)'
		));
	}
	
	$selectQuery = db_select('route_edges', $condition, $fieldToSelect, $joinQuery);
	$selectQuery .= " ORDER BY `order`";
	$queryResult = mysqli_query($mysqli, $selectQuery);

	if (!$queryResult) return null;
	
	$index = 0;
	$listRecord = array();

	while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
		$listRecord[] = $row;
	}
	return $listRecord;
}

/**
 * Simpan route-edge assign
 * @param array $assignData array of array(id_route, id_edge, direction, order)
 * @return unknown
 */
function assign_route_edge($assignData) {
	global $mysqli;
	
	$saveQuery = "";
	if (is_array($assignData)) {
		$saveQuery = db_insert_into_batch('route_edges', array(
			'`id_route`', '`id_edge`', '`direction`', '`order`'
		), $assignData);
	}
	
	$queryResult = mysqli_query($mysqli, $saveQuery);
	return $queryResult;
}

/**
 * Ambil list trayek berdasar id edge
 * @param int $idEdge ID Edge
 * @return NULL|array[] 
 */
function get_edge_route($idEdge) {
	global $mysqli;
	
	$condition = array();
	$condition['id_edge'] = _db_to_query($idEdge);
	
	//-- Default values
	$joinQuery = null;
	$fieldToSelect = "*";
	
	$selectQuery = db_select('route_edges', $condition, $fieldToSelect, $joinQuery);
	$queryResult = mysqli_query($mysqli, $selectQuery);
	
	if (!$queryResult) return null;
	
	$index = 0;
	$listRecord = array();
	
	while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
		$listRecord[] = $row;
	}
	return $listRecord;
}