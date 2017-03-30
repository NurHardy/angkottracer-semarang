/**
 * Edge management subroutine
 */

function new_edge() {
	if (focusedMarker == null) return;
	
	currentState = STATE_SELECTNODE;
	node_selected_callback = function (selectedMarker) {
		show_modal(URL_MODAL, {
			'name': 'edge.add',
			'data': {'id_node_1': focusedMarker.id_node, 'id_node_2': selectedMarker.id_node}
		}, function(response){
			//-- Insert and render the new edge
			activeLines.push(new google.maps.Polyline({
				path: [focusedMarker.position, selectedMarker.position],
				geodesic: false,
				strokeColor: '#FF0000',
				strokeOpacity: 1.0,
				strokeWeight: 1,
				clickable: false,
				map: map
			}));
			/*
			reversibleLabel = (response.data.reversible?"Yes":"No");
			$("#table_edge tbody").append(
					'<tr><td>'+response.data.id+
					'</td><td>'+response.data.distance+
					'</td><td>'+reversibleLabel+'</td><td>edit | <a href="#">hapus</a></td></tr>');
			*/
			
			//-- Saved and add into edge network
			_gui_push_edge(response.data.id, [focusedMarker.position, selectedMarker.position], response.data.edgedata);
			hide_modal();
			
			edit_edge_do(response.data.id);
		}, function(){
			
		});
	};
	update_gui();
}

/*
 * Update GUI current edge editor.
 */
function edit_update_() {
	if (activeEditingPolyLine) {
		activeEditingPolyLine.setOptions({
			icons: (activeEditingPolyLine.edgeData.reversible ? [] : SYS_SINGLEDIR_POLYLINE_ICONS)
        });
	}
}
function edit_edge_setat_(i) {
	var newLength = google.maps.geometry.spherical.computeLength(activeEditingPolyLine.getPath());
	$("#edgeedit_distance").html((newLength/1000).toFixed(3));
	
	if (i == 0) {
		if (labelMarkers[0]) labelMarkers[0].setPosition(activeEditingPolyLine.getPath().getAt(i));
	} else if (i >= activeEditingPolyLine.getPath().getLength() -1) {
		if (labelMarkers[1]) labelMarkers[1].setPosition(activeEditingPolyLine.getPath().getAt(i));
	}
}

function edit_edge_do(idEdge) {
	change_state(STATE_EDGESELECTED, edge_clear_workspace);
	clear_lines();
	
	//-- Hide editing edge
	var idActivePolyline = _get_idpolyline_by_idedge(idEdge);
	if (idActivePolyline !== null) {
		edgeNetworkPreview[idActivePolyline].setVisible(false);
	} else {
		return false;
	}
	
	var polyLineData = edgeNetworkPreview[idActivePolyline].getPath();
	
	//-- Draw editable polylines in the map
	if (activeEditingPolyLine) {
		activeEditingPolyLine.setMap(map);
		activeEditingPolyLine.setPath(polyLineData);
		activeEditingPolyLine.id_edge = idEdge;
		
		activeEditingPolyLine.edgeData.id_node_from = edgeNetworkPreview[idActivePolyline].edgeData.id_node_from;
		activeEditingPolyLine.edgeData.id_node_dest = edgeNetworkPreview[idActivePolyline].edgeData.id_node_dest;
		activeEditingPolyLine.edgeData.reversible = edgeNetworkPreview[idActivePolyline].edgeData.reversible;
		
		google.maps.event.addListener(activeEditingPolyLine.getPath(), 'set_at', edit_edge_setat_);
		activeEditingPolyLine.setVisible(true);
	} else {
		activeEditingPolyLine = new google.maps.Polyline({
			path: polyLineData,
			geodesic: false,
			strokeColor: '#162953',
			strokeOpacity: 1.0,
			strokeWeight: 2,
			clickable: false,
			editable: true,
			map: map,
			id_edge: idEdge,
			edgeData: {
				id_node_from: edgeNetworkPreview[idActivePolyline].edgeData.id_node_from,
				id_node_dest: edgeNetworkPreview[idActivePolyline].edgeData.id_node_dest,
				reversible: edgeNetworkPreview[idActivePolyline].edgeData.reversible
			}
		});
		
		//-- Setup editing context menu
		ctxMenu = new VertexContextMenu();
		google.maps.event.addListener(activeEditingPolyLine.getPath(), 'set_at', edit_edge_setat_);
		google.maps.event.addListener(activeEditingPolyLine, 'rightclick', function(e) {
			// Check if click was on a vertex control point
			if (e.vertex == undefined) {
				return;
			}
			
			// Vertex pertama dan terakhir tidak bisa dihapus...
			if ((e.vertex == 0) || (e.vertex >= activeEditingPolyLine.getPath().getLength() -1)) {
				return;
			}
			
			ctxMenu.open(map, activeEditingPolyLine.getPath(), e.vertex);
		});
	}
	
	edit_update_();
	
	$('#edge_name').val(edgeNetworkPreview[idActivePolyline].edgeData.edge_name);
	if (edgeNetworkPreview[idActivePolyline].edgeData.reversible) {
		$('#edge_isreversible').prop('checked', true);
	} else {
		$('#edge_isreversible').prop('checked', false);
	}
	
	//-- Hide node markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(false);
	});
	markerCluster.clearMarkers();
	
	//-- Start and end marker
	if (labelMarkers[0]) {
		labelMarkers[0].setPosition(polyLineData.getAt(0));
		labelMarkers[0].setMap(map);
		labelMarkers[0].setVisible(true);
	} else {
		labelMarkers[0] = new google.maps.Marker({
			position: polyLineData.getAt(0),
			map: map,
			label: 'A',
			title: 'Start',
			zIndex: -1,
			clickable: false
		});
	}
	
	var lastId = polyLineData.getLength()-1;
	if (labelMarkers[1]) {
		labelMarkers[1].setPosition(polyLineData.getAt(lastId));
		labelMarkers[1].setMap(map);
		labelMarkers[1].setVisible(true);
	} else {
		labelMarkers[1] = new google.maps.Marker({
			position: polyLineData.getAt(lastId),
			map: map,
			label: 'B',
			title: 'End',
			zIndex: -1,
			clickable: false
		});
	}
	
	edit_edge_setat_();
	
	//-- Pan viewport
	var bounds = new google.maps.LatLngBounds();
	for (var i = 0; i < polyLineData.length; i++) {
	    bounds.extend(polyLineData.getAt(i));
	}
	map.fitBounds(bounds);
	
	update_gui(false);
}
function edit_edge(idEdge) {
	//-- Fetch data
	_ajax_send({
		
	}, function(jsonData){
		//-- Modify existing node
		_gui_modify_node(jsonData.data.from.id_node, new google.maps.LatLng(jsonData.data.from.position),
				jsonData.data.from.node_data);
		_gui_modify_node(jsonData.data.dest.id_node, new google.maps.LatLng(jsonData.data.dest.position),
				jsonData.data.dest.node_data);
		
		//-- Add to editing line
		var polyLineData = google.maps.geometry.encoding.decodePath(jsonData.data.polyline_data);
		
		polyLineData.unshift(jsonData.data.from.position);
		polyLineData.push(jsonData.data.dest.position);
		
		_gui_modify_edge(jsonData.data.id, polyLineData, jsonData.data.edgedata);
		
		edit_edge_do(idEdge);
	}, "Memuat...", AJAX_REQ_URL+'/edge/'+idEdge, 'GET');
}

/*
 * Jika edit_edge akan menampilkan cursor editing, maka fungsi done_edit_edge akan
 * menyembunyikannya kembali...
 */
function edge_clear_workspace(oldState, newState) {
	if (labelMarkers[0]) labelMarkers[0].setVisible(false);
	if (labelMarkers[1]) labelMarkers[1].setVisible(false);
	
	//-- Show editing edge, if exists
	if (activeEditingPolyLine.id_edge) {
		var idActivePolyline = _get_idpolyline_by_idedge(activeEditingPolyLine.id_edge);
		if (idActivePolyline) {
			edgeNetworkPreview[idActivePolyline].setVisible(true);
		}
	}
	
	//-- Reshow all markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(true);
		markerCluster.addMarker(curMarker, false);
	});
}

function edge_do_delete_(idEdge, afterDeleteCallback) {
	_ajax_send({
		
	}, function(jsonData){
		if (typeof(afterDeleteCallback) === 'function') {
			afterDeleteCallback(jsonData);
		}
	}, "Memproses...", AJAX_REQ_URL+'/edge/'+idEdge, 'DELETE');
	
	return false;
}

function edge_showprops() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	//-- Init panel before showing them to user...
	var selectedIdMarker = _get_idpolyline_by_idedge(activeEditingPolyLine.id_edge);
	var edgeName = edgeNetworkPreview[selectedIdMarker].edgeData.edge_name;
	var isReversible = edgeNetworkPreview[selectedIdMarker].edgeData.reversible;
	$("#fpanel_edgeopts #edge_name").val(edgeName);
	$("#fpanel_edgeopts #edge_isreversible").val((isReversible ? "yes" : "no"));
	
	$("#fpanel_edgeopts").show();
	$("#site_floatpanel_extension").fadeIn(200, function(){
		$("#edge_name").focus();
	});
	return false;
}

function edge_isreversible_onupdate(elmt) {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var isCheck = $(elmt).is(":checked");
	activeEditingPolyLine.edgeData.reversible = isCheck;
	
	edit_update_();
}

function edge_reverse_current() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var edgePath = activeEditingPolyLine.getPath();
	
	var newPath = new google.maps.MVCArray();
	
	var i; var vCount = edgePath.getLength();
	for (i = 0; i < vCount; i++) {
		newPath.push(edgePath.pop());
	}
	
	activeEditingPolyLine.setPath(newPath);
	
	edit_edge_setat_(0);
	edit_edge_setat_(vCount-1);
	
	//-- Tukar id node
	var idNodeFrom = activeEditingPolyLine.edgeData.id_node_from;
	activeEditingPolyLine.edgeData.id_node_from = activeEditingPolyLine.edgeData.id_node_dest;
	activeEditingPolyLine.edgeData.id_node_dest = idNodeFrom;
	
	edit_update_();
}

function edge_save() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var edgeName = $('#edge_name').val();
	var edgePath = activeEditingPolyLine.getPath();
	var encStr = google.maps.geometry.encoding.encodePath(edgePath);
	
	_ajax_send({
		new_path: encStr,
		edge_name: edgeName,
		id_node_from: activeEditingPolyLine.edgeData.id_node_from,
		id_node_dest: activeEditingPolyLine.edgeData.id_node_dest,
		reversible: (activeEditingPolyLine.edgeData.reversible ? 1 : 0)
	}, function(jsonData){
		//-- Clone path
		var decPath = google.maps.geometry.encoding.decodePath(encStr);
		_gui_modify_edge(activeEditingPolyLine.id_edge, decPath, {
			reversible: activeEditingPolyLine.edgeData.reversible
		});
		
		//-- Geser node ujung, dan update edge yang adjacent
		var lastIdx = decPath.length - 1;
		_gui_modify_node(activeEditingPolyLine.edgeData.id_node_from, decPath[0], {});
		_gui_modify_node(activeEditingPolyLine.edgeData.id_node_dest, decPath[lastIdx], {});
		
		toastr.success('Edge successfully saved.');
	}, "Menyimpan...", AJAX_REQ_URL+'/edge/'+activeEditingPolyLine.id_edge, 'POST');
	
	return false;
}

function edge_reset() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var uConf = confirm('Polyline akan hilang dan mungkin menghilangkan hasil pekerjaan Anda apabila edge direset. Reset edge? ');
	if (!uConf) return false;
	
	var activePolyLinePath = activeEditingPolyLine.getPath();
	var ctr; var upperBound = activePolyLinePath.getArray().length - 2;
	for (ctr = upperBound; ctr > 0; ctr--) {
		activePolyLinePath.removeAt(ctr);
	}
	return false;
}

function edge_getdir(opt_direction) {
	var direction_ = opt_direction || 1;
	
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var uConf = confirm('Polyline akan hilang dan mungkin menghilangkan hasil pekerjaan Anda. Lanjutkan proses get direction? ');
	if (!uConf) return false;
	
	var activeEditingPolyLinePath = activeEditingPolyLine.getPath();
	
	var originPoint = activeEditingPolyLinePath.getAt(0).toJSON();
	var destPoint = activeEditingPolyLinePath.getAt(activeEditingPolyLinePath.getLength()-1).toJSON();
	
	_ajax_send({
		origin: (direction_ > 0 ? originPoint : destPoint),
		dest: (direction_ > 0 ? destPoint : originPoint)
	}, function(jsonData){
		var coords = jsonData.path;
		
		var activePolyLinePath = activeEditingPolyLine.getPath();
		activePolyLinePath.clear();
		
		var ctr; var pointTotal = coords.length;
		
		if (direction_ > 0) {
			for (ctr = 0; ctr < pointTotal; ctr++) {
				activePolyLinePath.push(new google.maps.LatLng(coords[ctr].lat, coords[ctr].lng));
			}
		} else {
			for (ctr = pointTotal-1; ctr >= 0; ctr--) {
				activePolyLinePath.push(new google.maps.LatLng(coords[ctr].lat, coords[ctr].lng));
			}
		}
		
	}, "Memproses...", AJAX_REQ_URL+'/edge/dir','POST');
	
	return false;
}
function edge_interpolate() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	//-- Interpolasi dibatasi sampai 100 titik saja...
	var edgePath = activeEditingPolyLine.getPath();
	if (edgePath.getLength() > 100) {
		alert("Maksimum titik interpolasi adalah 100 titik. Silakan hapus beberapa dan coba lagi.");
		return false;
	}
	var uConf = confirm('Polyline akan hilang dan mungkin menghilangkan hasil pekerjaan Anda. Lanjutkan proses interpolasi? ');
	if (!uConf) return false;
	
	var encStr = google.maps.geometry.encoding.encodePath(edgePath);
	
	_ajax_send({
		path: encStr
	}, function(jsonData){
		var coords = jsonData.snapdata;
		
		var activePolyLinePath = activeEditingPolyLine.getPath();
		activePolyLinePath.clear();
		
		var ctr; var pointTotal = jsonData.snapdata.length;
		for (ctr = 0; ctr < pointTotal; ctr++) {
			activePolyLinePath.push(new google.maps.LatLng(jsonData.snapdata[ctr].lat, jsonData.snapdata[ctr].lng));
		}
		
	}, "Memproses...", AJAX_REQ_URL+'/edge/interpolate', 'POST');
	
	return false;
}

function create_shelter(vertexIdx) {
	alert(vertexIdx);
}
function edge_break(vertexIdx) {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var uConf = confirm('Save selected edge and break?');
	if (!uConf) return false;
	
	var edgePath = activeEditingPolyLine.getPath();
	var encStr = google.maps.geometry.encoding.encodePath(edgePath);
	
	_ajax_send({
		id: activeEditingPolyLine.id_edge,
		new_path: encStr,
		vertex_idx: vertexIdx
	}, function(jsonData){
		_gui_push_node(jsonData.new_node_id, jsonData.new_node_pos, jsonData.new_node_data);
		
		var poly1 = google.maps.geometry.encoding.decodePath(jsonData.new_polyline[0].polyline);
		var poly2 = google.maps.geometry.encoding.decodePath(jsonData.new_polyline[1].polyline);
		
		//-- Jika edge lama direplace
		if (activeEditingPolyLine.id_edge == jsonData.new_polyline[0].id_edge) {
			//-- Update neighbor cache
			var idPolyline = _get_idpolyline_by_idedge(activeEditingPolyLine.id_edge);
			var idNodeFrom = edgeNetworkPreview[idPolyline].edgeData.id_node_from;
			var idNodeDest = edgeNetworkPreview[idPolyline].edgeData.id_node_dest;

			// New vertex neighbor
			neighborNodeCache_[jsonData.new_node_id].push({
				id_node_adj: idNodeFrom,
				id_edge: activeEditingPolyLine.id_edge
			});
			
			// Old edge neighbor
			neighborNodeCache_[idNodeFrom].find(function(elmt, idx){
				if (elmt.id_edge == activeEditingPolyLine.id_edge) {
					neighborNodeCache_[idNodeFrom][idx].id_node_adj = jsonData.new_node_id;
					return true;
				} else return false;
			});
			
			neighborNodeCache_[idNodeDest].find(function(elmt, idx){
				if (elmt.id_edge == activeEditingPolyLine.id_edge) {
					neighborNodeCache_[idNodeDest][idx].id_edge = jsonData.new_polyline[1].id_edge;
					neighborNodeCache_[idNodeDest][idx].id_node_adj = jsonData.new_node_id;
					return true;
				} else return false;
			});
			
			//-- Update existing edge
			_gui_modify_edge(activeEditingPolyLine.id_edge, poly1, {
				edge_name: jsonData.new_polyline[0].edge_name,
				id_node_dest: jsonData.new_node_id,
				reversible: jsonData.new_polyline[0].reversible
			});
		} else {
			_gui_push_edge(jsonData.new_polyline[0].id_edge, poly1, {
				edge_name: edgeNetworkPreview[idPolyline].edgeData.edge_name,
				id_node_from: idNodeFrom,
				id_node_dest: jsonData.new_node_id,
				reversible: jsonData.new_polyline[0].reversible
			});
		}
		
		_gui_push_edge(jsonData.new_polyline[1].id_edge, poly2, {
			edge_name: jsonData.new_node_name,
			id_node_from: jsonData.new_node_id,
			id_node_dest: idNodeDest,
			reversible: jsonData.new_polyline[1].reversible
		});
		
		toastr.success('Edge successfully breaked.');
		focus_node_do(jsonData.new_node_id);
		
	}, "Menyimpan...", AJAX_REQ_URL+'/edge/break', 'POST');
	
	return false;
}
function edge_delete() {
	if (currentState != STATE_EDGESELECTED) return false;
	if (!activeEditingPolyLine) return false;
	
	var uConf = confirm('Hapus busur?');
	if (!uConf) return false;
	
	edge_do_delete_(activeEditingPolyLine.id_edge, function(){
		_gui_modify_edge(activeEditingPolyLine.id_edge, null);
		activeEditingPolyLine.id_edge = null;
		toastr.success('Edge successfully deleted.');
		reset_gui();
	});
	return false;
}