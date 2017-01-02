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
		tmpMarker = new google.maps.Marker({
			position: {'lat': response.data.lat, 'lng': response.data.lng},
			map: map,
			title: response.data.name,
			id_node: response.data.id,
			icon: MARKERBASE + 'dot-red.png'
		});
		
		activeMarkers.push(tmpMarker);
		google.maps.event.addListener(tmpMarker, 'click', marker_click);
		$('#site_nodeselector select[name=nodeid]').append(
			'<option value="'+(response.data.id)+'">'+response.data.name+'</option>');
		
		hide_modal();
		reset_gui();
	}, function(){
		
	});
}

function focus_node_do() {
	change_state(STATE_NODESELECTED, node_clear_workspace);
	
	map.panTo(jsonData.nodedata.position);
	$("#table_edge tbody").empty();

	var edgeCount = jsonData.edges.length;
	var ctr; var reversibleLabel;
	
	clear_lines();
	for (ctr = 0; ctr < edgeCount; ctr++) {
		var polyLineData = jsonData.edges[ctr].polyline_data;
		
		// Tambahkan lokasi node di ujung awal dan akhir polyline
		if (jsonData.edges[ctr].polyline_dir > 0) {
			polyLineData.unshift(jsonData.nodedata.position);
			polyLineData.push(jsonData.edges[ctr].position);
		} else {
			polyLineData.unshift(jsonData.edges[ctr].position);
			polyLineData.push(jsonData.nodedata.position);
		}
		
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
				'</td></tr>');
	}
	
	update_gui();
	
	//-- Cursor: Tunjuk pada marker yang dipilih
	if (nodeCursor == null) {
		nodeCursor = new google.maps.Marker({
			position: jsonData.nodedata.position,
			map: map,
			icon: MARKERBASE + 'arrow.png',
			clickable: false
		});
	} else {
		nodeCursor.setVisible(true);
		nodeCursor.setPosition(jsonData.nodedata.position);
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
		
		for (ctr = 0; ctr < edgeCount; ctr++) {
			//var decodedPath = google.maps.geometry.encoding.decodePath(jsonData.edges[ctr].polyline_data);
			var polyLineData = jsonData.edges[ctr].polyline_data;
			
			// Tambahkan lokasi node di ujung awal dan akhir polyline
			if (jsonData.edges[ctr].polyline_dir > 0) {
				polyLineData.unshift(jsonData.nodedata.position);
				polyLineData.push(jsonData.edges[ctr].position);
			} else {
				polyLineData.unshift(jsonData.edges[ctr].position);
				polyLineData.push(jsonData.nodedata.position);
			}
			
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
					'</td></tr>');
		}
		
	}, "Memuat...", URL_DATA_AJAX);
	return false;
}

function node_clear_workspace(oldState, newState) {
	// Sembunyikan cursor jika status baru bukan untuk 'mengolah' node.
	if ((newState != STATE_NODESELECTED) && (newState != STATE_MOVESELECTED)) {
		if (nodeCursor)	nodeCursor.setVisible(false);
	}
	if (oldState == STATE_MOVESELECTED) {
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
		
	}, "Memproses...", URL_DATA_AJAX);
}