/*
 * js/main/vertex.js
 * ------------------
 * Subroutine untuk vertex/node
 */

function new_node() {
	//currentState = STATE_PLACENODE;
	var oldState = currentState;
	
	change_state(STATE_PLACENODE, function(oldState, newState) {
		if (oldState == STATE_PLACENODE) {
			//-- Sembunyikan nodeCursor jika tidak diperlukan
			if (nodeCursor)	nodeCursor.setVisible(false);
		}
	});
	
	update_gui();
}

/*
 * On map click event callback for new vertex
 */
function _new_vertex(e) {
	var selectedPoint = e.latLng;
	var clickLat = selectedPoint.lat();
	var clickLng = selectedPoint.lng();
	
	var submitData = {'lat': clickLat, 'lng': clickLng};
	
	//-- Editing edge?
	if (activeEditingPolyLine) {
		var isEditPolylineExist = activeEditingPolyLine.getVisible();
		if (isEditPolylineExist) {
			submitData['connect_to'] = {
				'type': 'edge',
				'id': activeEditingPolyLine.id_edge
			}
		}
	}

	//-- Editing node?
	if (nodeCursor) {
		var isNodeCursorExist = nodeCursor.getVisible();
		if (isNodeCursorExist) {
			submitData['connect_to'] = {
				'type': 'node',
				'id':nodeCursor.id_node
			}
		}
	}
	
	
	//alert(selectedPoint.lat()+','+selectedPoint.lng());
	show_modal(URL_MODAL, {
		'name': 'node.add',
		'data': submitData
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
	}, "Memuat...", AJAX_REQ_URL + '/node/' + nodeId, 'GET');
	return false;
}

function node_clear_workspace(oldState, newState) {
	// Sembunyikan cursor jika status baru bukan untuk 'mengolah' node.
	if ((newState != STATE_NODESELECTED) && (newState != STATE_MOVENODE)) {
		if ((oldState == STATE_NODESELECTED) && (newState == STATE_PLACENODE)) {
			//-- Hide node cursor
			if (nodeCursor)	nodeCursor.setVisible(false);
		}
		//-- Clear clickable neighbor edges
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

function node_do_delete_(idEdge, afterDeleteCallback) {
	_ajax_send({
		
	}, function(jsonData){
		if (typeof(afterDeleteCallback) === 'function') {
			afterDeleteCallback(jsonData);
		}
	}, "Memproses...", AJAX_REQ_URL+'/node/'+idEdge, 'DELETE');
	
	return false;
}
function node_delete() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	var uConf = confirm('Hapus node?');
	if (!uConf) return false;
	
	node_do_delete_(nodeCursor.id_node, function(){
		//-- Remove adjacent polylines from the map
		var edgeCount = neighborNodeCache_[nodeCursor.id_node].length;
		var ctr;
		
		for (ctr = edgeCount-1; ctr >= 0; ctr--) {
			var idEdge = neighborNodeCache_[nodeCursor.id_node][ctr].id_edge;
			_gui_modify_edge(idEdge, null);
		}
		
		//-- Remove marker
		_gui_modify_node(nodeCursor.id_node, null);
		
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
		position: {lat: newLat, lng: newLng}
	}, function(jsonData){
		var currentIdNode = nodeCursor.id_node;
		_gui_modify_node(currentIdNode, newPosition, {});
		
		toastr.success('Node successfully moved.');
		reset_gui();
	}, "Memproses...", AJAX_REQ_URL+'/node/'+nodeCursor.id_node, 'POST');
}

function node_showprops() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	//-- Init panel before showing them to user...
	var selectedIdMarker = _get_idmarker_by_idnode(nodeCursor.id_node);
	var nodeName = activeMarkers[selectedIdMarker].nodeData.node_name;
	var nodeType = activeMarkers[selectedIdMarker].nodeData.node_type;
	
	$("#fpanel_nodeopts input[name=node_name]").val(nodeName);
	$("#fpanel_nodeopts select[name=node_type]").val(nodeType);
	
	$("#fpanel_nodeopts").show();
	$("#site_floatpanel_extension").fadeIn(200, function(){
		$("#fpanel_nodeopts input[name=node_name]").focus();
	});
	return false;
}

function node_edit_recenter() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	var selectedIdMarker = _get_idmarker_by_idnode(nodeCursor.id_node);
	var focusPos = activeMarkers[selectedIdMarker].getPosition();
	nodeCursor.setPosition(focusPos);
	map.panTo(focusPos);
}
function node_submit_nodeprops() {
	if (currentState != STATE_NODESELECTED) return false;
	if (!nodeCursor) return false;
	
	var formData = $('#form_floatpanel_node').serialize();
	var newNodeName = $('#fpanel_nodeopts input[name=node_name]').val();
	var newNodeType = $('#fpanel_nodeopts select[name=node_type]').val();
	
	_ajax_send(formData, function(jsonData){
		var currentIdNode = nodeCursor.id_node;
		_gui_modify_node(currentIdNode, 0, {
			node_name: newNodeName,
			node_type: newNodeType
		});
		
		toastr.success('Node properties successfully updated.');
		hide_fpanel_ext();
	}, "Memproses...", AJAX_REQ_URL+'/node/'+nodeCursor.id_node, 'POST');
	
	return false;
}