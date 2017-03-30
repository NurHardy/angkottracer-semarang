/*
 * js/main/vertex.js
 * ------------------
 * Subroutine untuk vertex/node
 */

function new_node() {
	currentState = STATE_PLACENODE;
	update_gui();
}

/*
 * On map click event callback for new vertex
 */
function _new_vertex(e) {
	var selectedPoint = e.latLng;
	var clickLat = selectedPoint.lat();
	var clickLng = selectedPoint.lng();
	//alert(selectedPoint.lat()+','+selectedPoint.lng());
	show_modal(URL_MODAL, {
		'name': 'node.add',
		'data': {'lat': clickLat, 'lng': clickLng}
	}, function(response){
		_gui_push_node(response.data.id, response.data.position, response.data.node_data);
		hide_modal();
		
		focus_node_do(response.data.id);
	}, function(){
		
	});
}

function focus_node_do(idNode) {
	//-- Get node info from library...
	var selectedIdMarker;
	if ((idNode in nodeMarkerMap_) && (idNode in neighborNodeCache_)) {
		selectedIdMarker = nodeMarkerMap_[idNode];
	} else {
		_gui_need_refresh('focus_node_do: ' + idNode + " not in nodeMarkerMap_ or " + idNode + " not in neighborNodeCache_");
		return;
	}
	
	change_state(STATE_NODESELECTED, node_clear_workspace);
	
	var focusPos = activeMarkers[selectedIdMarker].getPosition();
	map.panTo(focusPos);
	$("#table_edge tbody").empty();

	var edgeCount = neighborNodeCache_[idNode].length;
	var ctr; var reversibleLabel;
	
	clear_lines();
	for (ctr = 0; ctr < edgeCount; ctr++) {
		var idNodeAdj = neighborNodeCache_[idNode][ctr].id_node_adj;
		var idEdge = neighborNodeCache_[idNode][ctr].id_edge;
		var idPolyline = edgePolylineMap_[idEdge];
		
		var polyLineData = edgeNetworkPreview[idPolyline].getPath();
		
		activeLines.push(new google.maps.Polyline({
			path: polyLineData,
			geodesic: false,
			strokeColor: '#FF0000',
			strokeOpacity: 1.0,
			strokeWeight: 2,
			clickable: true,
			map: map,
			id_edge: idEdge,
			zIndex: 10
		}));
		
		google.maps.event.addListener(activeLines[ctr], 'click', function(){
			edit_edge(this.id_edge);
		});
		
		reversibleLabel = (edgeNetworkPreview[idPolyline].edgeData.reversible?"Y":"N");
		/*
		$("#table_edge tbody").append(
				'<tr><td><a href="#" onclick="return focus_node('+edgeNetworkPreview[idPolyline].id_edge+');" title="'+jsonData.edges[ctr].name+'">'+
				jsonData.edges[ctr].id+'</a>'+
				'</td><td>'+jsonData.edges[ctr].distance+' km'+
				'</td><td>'+reversibleLabel+'</td><td> '+
				'<a href="#" onclick="return edit_edge('+jsonData.edges[ctr].id_edge+');">edit</a> | ' + 
				'<a href="#" onclick="return delete_edge('+nodeId+','+jsonData.edges[ctr].id_edge+');">hapus</a>' + 
				'</td></tr>');*/
	}
	
	update_gui();
	
	//-- Cursor: Tunjuk pada marker yang dipilih
	if (nodeCursor == null) {
		nodeCursor = new google.maps.Marker({
			id_node: idNode,
			position: focusPos,
			map: map,
			icon: MARKERBASE + 'arrow.png',
			clickable: false
		});
	} else {
		nodeCursor.id_node = idNode;
		nodeCursor.setVisible(true);
		nodeCursor.setPosition(focusPos);
	}
}
function focus_node(nodeId) { // nodeId di database
	_ajax_send({
		verb: 'node.getbyid',
		id: nodeId
	}, function(jsonData){
		//-- Update nodes data
		var edgeCount = jsonData.edges.length;
		var ctr;
		
		var idNodeFrom; var idNodeDest; var adjNodePosition;
		
		//-- Karena pada proses ini server mengambil semua node adjacent, maka perbari cache...
		//if (!(jsonData.nodedata.id in neighborNodeCache_))
		//	neighborNodeCache_[jsonData.nodedata.id] = [];
		neighborNodeCache_[jsonData.nodedata.id] = [];
		
		for (ctr = 0; ctr < edgeCount; ctr++) {
			// Cek dulu apakah ada dalam map?
			if (jsonData.edges[ctr].id_node_adj in nodeMarkerMap_) {
				if (jsonData.edges[ctr].polyline_dir > 1) {
					idNodeFrom = nodeId;
					idNodeDest = jsonData.edges[ctr].id_node_adj;
				} else {
					idNodeFrom = jsonData.edges[ctr].id_node_adj;
					idNodeDest = nodeId;
				}
				
				neighborNodeCache_[nodeId].push({
					id_edge: jsonData.edges[ctr].id_edge,
					id_node_adj: jsonData.edges[ctr].id_node_adj
				});
			} else {
				_gui_need_refresh('focus_node: Node '+jsonData.edges[ctr].id_node_adj+' not in nodeMarkerMap_');
				break;
			}
			
			var polyLineData = google.maps.geometry.encoding.decodePath(jsonData.edges[ctr].polyline);
			//var polyLineData = jsonData.edges[ctr].polyline_data;
			
			_gui_modify_edge(jsonData.edges[ctr].id_edge, polyLineData, jsonData.edges[ctr].edge_data);
			/*
			activeLines.push(new google.maps.Polyline({
				path: polyLineData,
				geodesic: false,
				strokeColor: '#FF0000',
				strokeOpacity: 1.0,
				strokeWeight: 2,
				clickable: true,
				map: map,
				id_edge: jsonData.edges[ctr].id_edge
			}));
			
			google.maps.event.addListener(activeLines[ctr], 'click', function(){
				edit_edge(this.id_edge);
			});
			
			reversibleLabel = (jsonData.edges[ctr].reversible?"Y":"N");
			$("#table_edge tbody").append(
					'<tr><td><a href="#" onclick="return focus_node('+jsonData.edges[ctr].id+');" title="'+jsonData.edges[ctr].name+'">'+
					jsonData.edges[ctr].id+'</a>'+
					'</td><td>'+jsonData.edges[ctr].distance+' km'+
					'</td><td>'+reversibleLabel+'</td><td> '+
					'<a href="#" onclick="return edit_edge('+jsonData.edges[ctr].id_edge+');">edit</a> | ' + 
					'<a href="#" onclick="return delete_edge('+nodeId+','+jsonData.edges[ctr].id_edge+');">hapus</a>' + 
					'</td></tr>'); */
		}
		
		focus_node_do(jsonData.nodedata.id);
	}, "Memuat...", URL_DATA_AJAX);
	return false;
}

function node_clear_workspace(oldState, newState) {
	// Sembunyikan cursor jika status baru bukan untuk 'mengolah' node.
	if ((newState != STATE_NODESELECTED) && (newState != STATE_MOVENODE)) {
		if (nodeCursor)	nodeCursor.setVisible(false);
		clear_lines();
	}
	if (oldState == STATE_MOVENODE) {
		nodeCursor.setDraggable(false);
		
		//-- Reshow all markers
		activeMarkers.map(function(curMarker, i){
			curMarker.setVisible(true);
			markerCluster.addMarker(curMarker, false);
		});
	}
}

function node_do_delete_(afterDeleteCallback) {
	_ajax_send({
		verb: 'node.delete',
		id: idEdge
	}, function(jsonData){
		if (typeof(afterDeleteCallback) === 'function') {
			afterDeleteCallback(jsonData);
		}
	}, "Memproses...", URL_DATA_AJAX);
	
	return false;
}
function node_delete() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	var uConf = confirm('Hapus node?');
	if (!uConf) return false;
	
	node_do_delete_(nodeCursor.id_node, function(){
		rebuild_markermap_();
		toastr.success('Node successfully deleted.');
		reset_gui();
	});
	return false;
}

function node_move() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	//-- Setup workspace
	nodeCursor.setDraggable(true);
	
	//-- Hide node markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(false);
	});
	markerCluster.clearMarkers();
	
	change_state(STATE_MOVENODE, node_clear_workspace);
	update_gui();
}

function node_move_commit() {
	var newPosition = nodeCursor.getPosition();
	var newLat = newPosition.lat();
	var newLng = newPosition.lng();
	
	_ajax_send({
		verb: 'node.edit',
		id: nodeCursor.id_node,
		position: {lat: newLat, lng: newLng}
	}, function(jsonData){
		var currentIdNode = nodeCursor.id_node;
		_gui_modify_node(currentIdNode, newPosition, {});
		
		toastr.success('Node successfully moved.');
		reset_gui();
	}, "Memproses...", URL_DATA_AJAX);
}