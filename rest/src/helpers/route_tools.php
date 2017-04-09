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
	
	foreach ($routeEdges as $itemEdge) {
		// Increment counter
		$orderNumber++;
		
		// Edge data modified here, so fix it
		if ($itemEdge['id_edge'] == $oldEdgeData['id_edge']) {
			// Shift the rest of order
			if ($itemEdge['direction'] > 0) {
				// Route is same direction as edge
				$procResult = $routeModel->shift_route_edges($idRoute, 1, '> '.$orderNumber);
				if (!$procResult) break;
				
				$assignData = [$idRoute, $newIdEdge, "'1'", ($orderNumber+1)];
			} else {
				$procResult = $routeModel->shift_route_edges($idRoute, 1, '>= '.$orderNumber);
				if (!$procResult) break;
				
				$assignData = [$idRoute, $newIdEdge, "'-1'", $orderNumber];
			}
			
			$procResult = $routeModel->assign_route_edge([$assignData]);
			break;
		}
		
	} // End foreach
	
	return $procResult;
}
