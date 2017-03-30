<?php

/**
 * Kelas entitas Node
 * @author Nur Hardyanto
 *
 */
class NodeModel {
	
	/**
	 * Koneksi database MySQLi
	 * @var object
	 */
	private $_db;
	
	public function __construct($dbConnection) {
		$this->_db = $dbConnection;
	}
	
	/**
	 * Ambil list jenis node
	 * @return string[] Array (id =&gt; name)
	 */
	function get_node_types() {
		return array(
				0 => 'Default',
				1 => 'Shelter BRT'
			);
	}
	/**
	 * Ambil list node dengan kriteria tertentu
	 * @param integer $idArea Filter ID Area, -1 untuk nonaktifkan filter ini
	 * @return array|FALSE Kembali array multidimensi asosiatif dari record, atau FALSE jika gagal.
	 */
	function get_nodes($idArea = -1) {
		$condition = array();
		
		if ($idArea > 0) $condition['id_area'] = _db_to_query($idArea, $this->_db);
		$selectQuery = db_select('nodes', $condition, '*, X(location) AS location_lng, Y(location) AS location_lat');
		$queryResult = mysqli_query($this->_db, $selectQuery);
		
		if (!$queryResult) return false;
		$index = 0;
		$listRecord = array();
		
		while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
			$index = $row['id_node'];
			$listRecord[$index] = $row;
			$listRecord[$index]['neighbors'] = array();
		}
		return $listRecord;
	}
	
	/**
	 * Ambil data record node dengan id tertentu
	 * @param integer $nodeId Node ID
	 * @return array|FALSE Kembali array asosiatif dari record, atau FALSE jika gagal.
	 */
	function get_node_by_id($nodeId) {
		$selectQuery = db_select('nodes', array('id_node' => $nodeId), '*, X(location) AS location_lng, Y(location) AS location_lat');
		
		$result = mysqli_query($this->_db, $selectQuery);
		
		$row = mysqli_fetch_array($result, MYSQLI_ASSOC);
		return $row;
	}
	/**
	 * Simpan record node
	 * @param array $nodeData Data node, field sesuai database
	 * @param integer $nodeId ID node. -1 untuk insert
	 * @return int|NULL id node baru jika berhasil, sebaliknya NULL
	 */
	function save_node($nodeData, $nodeId = -1) {
		$nodeFields = array();
		foreach ($nodeData as $propKey => $propValue) {
			$nodeFields[$propKey] = $propValue;
		}
		$saveQuery = "";
		if ($nodeId > 0) {
			$saveQuery = db_update('nodes', $nodeData, array('id_node' => $nodeId));
		} else {
			if (!isset($nodeFields['date_created']))
				$nodeFields['date_created'] = _db_to_query(date('Y-m-d H:i:s'), $this->_db);
			$saveQuery = db_insert_into('nodes', $nodeFields);
		}
		
		$queryResult = mysqli_query($this->_db, $saveQuery);
		if ($queryResult) {
			if ($nodeId > 0) {
				return $nodeId;
			} else {
				$newId = mysqli_insert_id($this->_db);
				return $newId;
			}
		} else return null;
	}
	
	/**
	 * Hapus node berdasar ID node
	 *
	 * @param ID busur $idEdge
	 * @param bool $softDelete [Optional, default = FALSE] Soft delete?
	 * @return bool TRUE jika berhasil, NULL jika gagal
	 */
	function delete_node($idNode, $softDelete = false) {
		$idNode = intval($idNode);
		$deleteQuery = db_delete_where('nodes', array('id_node' => $idNode));
	
		$queryResult = mysqli_query($this->_db, $deleteQuery);
		if ($queryResult) {
			return true;
		} else return null;
	}
}