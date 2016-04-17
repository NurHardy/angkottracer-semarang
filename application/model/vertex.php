<?php

function get_vertexes() {
	global $mysqli;
	
	$condition = array();
	$selectQuery = db_select('vertexes', $condition);
	$queryResult = mysqli_query($mysqli, $selectQuery);
	
	if (!$queryResult) return false;
	$index = 0;
	$listRecord = array();
	
	while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
		$listRecord[$index] = $row;
		$index++;
	}
	return $listRecord;
}

function get_adjacent_vertex($idNode, $joinNode = false) {
	global $mysqli;
	
	$condition = sprintf('(id_node_from=%d) OR (id_node_dest=%d)', $idNode, $idNode);
	
	$selectQuery = db_select('vertexes', $condition);
	$queryResult = mysqli_query($mysqli, $selectQuery);
	
	if (!$queryResult) return false;
	$index = 0;
	$listRecord = array();
	
	while ($row = mysqli_fetch_array($queryResult, MYSQLI_ASSOC)) {
		$listRecord[$index] = $row;
		$index++;
	}
	return $listRecord;
}