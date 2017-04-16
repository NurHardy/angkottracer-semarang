<?php


/**
 * Fix rute trayek yang terpecah karena ada busur yang dipecah.
 * 
 * @param EdgeModel $edgeModel
 * @param RouteModel $routeModel
 * @param int $idRoute
 * @param array $oldEdgeData
 * @param int $newIdNode
 * @param int $newIdEdge
 * @return boolean TRUE jika sukses, FALSE jika gagal
 */
function fix_route($edgeModel, $routeModel, $idRoute, $oldEdgeData, $newIdNode, $newIdEdge) {
	$routeEdges = $routeModel->get_route_edges($idRoute, true);
	
	if (empty($routeEdges)) {
		return true;
	}
	
	$orderNumber = 0;
	$assignData = [];
	
	$lastNodeId = null;
	if (!empty($routeEdges)) {
		$lastNodeId = ($routeEdges[0]['direction'] > 0 ? $routeEdges[0]['id_node_from'] : $routeEdges[0]['id_node_dest']);
	}
	foreach ($routeEdges as $itemEdge) {
		// Increment counter
		$orderNumber++;
		
		// Edge data modified here, so fix it
		if ($itemEdge['id_edge'] == $oldEdgeData['id_edge']) {
			// Check edge and route direction...
			$routeEdgeDir = ($itemEdge['direction'] > 0 ? "'1'" : "'-1'");
			
			// Shift the rest of order
			if (($itemEdge['direction'] > 0) && ($lastNodeId == $oldEdgeData['id_node_from'])) {
				// Route is same direction as edge
				$procResult = $routeModel->shift_route_edges($idRoute, 1, '> '.$orderNumber);
				if (!$procResult) break;
				
				$assignData = [$idRoute, $newIdEdge, $routeEdgeDir, ($orderNumber+1)];
			} else if (($itemEdge['direction'] < 0) && ($lastNodeId == $oldEdgeData['id_node_dest'])) {
				$procResult = $routeModel->shift_route_edges($idRoute, 1, '>= '.$orderNumber);
				if (!$procResult) break;
				
				$assignData = [$idRoute, $newIdEdge, $routeEdgeDir, $orderNumber];
			} else {
				//-- Something not right here...
				$procResult = false;
				break;
			}
			
			$procResult = $routeModel->assign_route_edge([$assignData]);
			break;
		}
		
		$lastNodeId = ($itemEdge['direction'] > 0 ? $itemEdge['id_node_dest'] : $itemEdge['id_node_from']);
	} // End foreach
	
	return $procResult;
}
