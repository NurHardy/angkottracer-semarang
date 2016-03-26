<?php

function save_node($nodeData, $nodeId = -1) {
	global $mysqli;
	
	$nodeFields = array();
	foreach ($nodeData as $propKey => $propValue) {
		$nodeFields[$propKey] = $propValue;
	}
	$saveQuery = "";
	if ($nodeId > 0) {
		$saveQuery = db_update('nodes', $nodeData, array('id_node' => $nodeId));
	} else {
		if (!isset($nodeFields['date_created']))
			$nodeFields['date_created'] = date('Y-m-d H:i:s');
		$saveQuery = db_insert_into('nodes', $nodeFields);
	}
	
	$queryResult = mysqli_query($mysqli, $saveQuery);
	return $queryResult;
}