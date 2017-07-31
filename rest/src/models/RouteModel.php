<?php

/**
 * Kelas entitas trayek
 * @author Nur Hardyanto
 *
 */
class RouteModel {
	
	/**
	 * Koneksi database MySQLi
	 * @var object
	 */
	private $_db;
	
	public static $path_routeicon = "/assets/images/angkot/";
	public static $default_routeicon = "angkot-gray.png";
	
	public function __construct($dbConnection) {
		$this->_db = $dbConnection;
	}
	
	/**
	 * Ambil list jenis trayek
	 * @return string[] Array (id =&gt; name)
	 */
	function get_route_types() {
		return array(
				0 => 'Angkutan Kota',
				1 => 'Shuttle Bus (BRT)'
		);
	}
	
	/**
	 * Fetch routes
	 * @param int $vehicleType Jenis armada. 1 = mobil, 2 = bus kecil, 3 = bus besar, 4 = BRT
	 * @return null|array NULL jika error, jika sukses kembali array objek
	 */
	function get_routes($vehicleType = -1, $routeStatus = -1) {
		$condition = array();
		if ($vehicleType > 0) $condition['vehicle_type'] = _db_to_query($vehicleType, $this->_db);
		if ($routeStatus > 0) $condition['status'] = _db_to_query($routeStatus, $this->_db);
		
		$selectQuery = db_select('public_routes', $condition);
		$selectQuery .= ' ORDER BY route_code ASC';
		$queryResult = mysqli_query($this->_db, $selectQuery);
	
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
		$selectQuery = db_select('public_routes', array('id_route' => $idRoute));
	
		$result = mysqli_query($this->_db, $selectQuery);
	
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		return $row;
	}
	
	/**
	 * Hapus cache edge assign untuk route tertentu.
	 * @param unknown $idRoute
	 * @return unknown
	 */
	function clear_route_edges($idRoute) {
		$delQuery = db_delete_where('route_edges', array('id_route' => $idRoute));
		$result = mysqli_query($this->_db, $delQuery);
		return $result;
	}
	
	/**
	 * Simpan record trayek/route
	 * @param array $routeData Field route yang ingin disimpan/diupdate
	 * @param int $routeId ID trayek/route
	 * @return int|NULL Kembali ID route jika berhasil, atau NULL jika gagal.
	 */
	function save_route($routeData, $routeId = -1) {
		$routeFields = array();
		foreach ($routeData as $propKey => $propValue) {
			$routeFields[$propKey] = $propValue;
		}
		$saveQuery = "";
		if ($routeId > 0) {
			$saveQuery = db_update('public_routes', $routeFields, array('id_route' => $routeId));
		} else {
			if (!isset($routeFields['date_created']))
				$routeFields['date_created'] = _db_to_query(date('Y-m-d H:i:s'), $this->_db);
				$saveQuery = db_insert_into('public_routes', $routeFields);
		}
	
		$queryResult = mysqli_query($this->_db, $saveQuery);
		if ($queryResult) {
			if ($routeId > 0) {
				return $routeId;
			} else {
				$newId = mysqli_insert_id($this->_db);
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
		$condition = array();
		$condition['id_route'] = _db_to_query($idRoute, $this->_db);
	
		//-- Default values
		$joinQuery = null;
		$fieldToSelect = "*";
	
		if ($joinEdge) {
			$joinQuery = ' INNER JOIN edges ON edges.id_edge=route_edges.id_edge ';
			$fieldToSelect = _gen_fields(array(
					0 => 'route_edges.*',
					'id_node_from' => 'edges.id_node_from',
					'id_node_dest' => 'edges.id_node_dest',
					//'id_road' => 'edges.id_road',
					'distance' => 'edges.distance',
					'polyline_data' => 'AsText(edges.polyline)'
			));
		}
	
		$selectQuery = db_select('route_edges', $condition, $fieldToSelect, $joinQuery);
		$selectQuery .= " ORDER BY `order`";
		$queryResult = mysqli_query($this->_db, $selectQuery);
	
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
		$saveQuery = "";
		if (is_array($assignData)) {
			$saveQuery = db_insert_into_batch('route_edges', array(
					'`id_route`', '`id_edge`', '`direction`', '`order`'
			), $assignData);
		}
	
		$queryResult = mysqli_query($this->_db, $saveQuery);
		return $queryResult;
	}
	
	/**
	 * Update informasi assign busur dari trayek.
	 * 
	 * @param int $idRoute ID trayek
	 * @param int $orderSeq Nomor urutan
	 * @param array $updateData Field yang akan diupdate
	 * @return bool TRUE jika berhasil, NULL jika gagal
	 */
	function update_edge_assign($idRoute, $orderSeq, $updateData) {
		$updateQuery = db_update('public_routes', $updateData, array('id_route' => $routeId, '`order`' => $orderSeq));
		$queryResult = mysqli_query($this->_db, $updateQuery);
		return $queryResult;
	}
	/**
	 * Shift urutan data busur
	 * @param int $idRoute ID trayek
	 * @param int $shiftCount Jumlah shift, boleh negatif. Ex: 1 atau -1
	 * @param string $orderCondition Kondisi urutan. Ex: '&gt; 10' atau '&lt; 15'
	 * @return bool TRUE jika berhasil, NULL jika gagal
	 */
	function shift_route_edges($idRoute, $shiftCount, $orderCondition) {
		$strWhere = sprintf("(id_route=%s) AND (`order` %s)",
				_db_to_query($idRoute, $this->_db), $orderCondition);
		
		$intShiftCount = intval($shiftCount);
		
		$updateField = array('`order`' => '`order` + ('.$intShiftCount.')');
		$updateQuery = db_update('route_edges', $updateField, $strWhere);
		
		$queryResult = mysqli_query($this->_db, $updateQuery);
		return $queryResult;
	}
	/**
	 * Ambil list trayek berdasar id edge
	 * @param int $idEdge ID Edge
	 * @return NULL|array[]
	 */
	function get_edge_route($idEdge) {
		$condition = array();
		$condition['id_edge'] = _db_to_query($idEdge, $this->_db);
	
		//-- Default values
		$joinQuery = null;
		$fieldToSelect = "*";
	
		$selectQuery = db_select('route_edges', $condition, $fieldToSelect, $joinQuery);
		$queryResult = mysqli_query($this->_db, $selectQuery);
	
		if (!$queryResult) return null;
	
		$index = 0;
		$listRecord = array();
	
		while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
			$listRecord[] = $row;
		}
		return $listRecord;
	}
	
	/**
	 * Hapus trayek berdasar ID trayek
	 *
	 * @param ID busur $idRoute
	 * @return bool TRUE jika berhasil, NULL jika gagal
	 */
	function delete_route($idRoute) {
		$idRoute = intval($idRoute);
		$deleteQuery = db_delete_where('public_routes', array('id_route' => $idRoute));
	
		$queryResult = mysqli_query($this->_db, $deleteQuery);
		if ($queryResult) {
			return true;
		} else return null;
	}
}