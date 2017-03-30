/**
 * Route editor component
 */

var activeRouteId = null;

var activeRouteNodeSequence = [];
var activeRouteEdgeSequence = [];
var activeRouteLines = [];

//-- Polyline neighbor dari cursor
var suggestionPolylines = [];

//-------- INTERNAL FUNCTIONS ------------------------
function clear_routelines_() {
	var dLength = activeRouteLines.length;
	var ctr;
	for (ctr=dLength-1; ctr >= 0; ctr--) {
		activeRouteLines[ctr].setMap(null);
		activeRouteLines.splice(ctr, 1);
	}
}

/*
 * Push routeline.
 * idEdge: ID edge
 * idNodeDest: ID node tujuan
 * 
 * Kembali NULL jika proses gagal/tidak valid.
 */
function routeeditor_push_routeline(idEdge, idNodeDest) {
	var idPoly = _get_idpolyline_by_idedge(idEdge);
	
	if (idPoly == null) return null;
	
	//-- Cek apakah edge reversible?
	if (!edgeNetworkPreview[idPoly].edgeData.reversible) {
		if (edgeNetworkPreview[idPoly].edgeData.id_node_dest != idNodeDest) {
			return null;
		}
	}
	
	var edgePath = edgeNetworkPreview[idPoly].getPath();
	var newPath = new google.maps.MVCArray();
	
	//-- Tentukan arah
	var i; var vCount = edgePath.getLength();
	if (edgeNetworkPreview[idPoly].edgeData.id_node_dest == idNodeDest) {
		for (i = 0; i < vCount; i++) {
			newPath.push(edgePath.getAt(i));
		}
	} else {
		for (i = vCount-1; i >= 0; i--) {
			newPath.push(edgePath.getAt(i));
		}
	}
	
	var idMarker = _get_idmarker_by_idnode(idNodeDest);
	if (idMarker == null) return null;
	
	activeRouteLines.push(new google.maps.Polyline({
		id_edge: edgeNetworkPreview[idPoly].id_edge,
		edgeData: edgeNetworkPreview[idPoly].edgeData,
		path: newPath,
		geodesic: false,
		strokeColor: '#f00',
		strokeOpacity: 0.9,
		strokeWeight: 2,
		clickable: false,
		map: map,
		icons: (SYS_SINGLEDIR_POLYLINE_ICONS),
		zIndex: 100
	}));
	
	var lastId = activeRouteNodeSequence.length;
	var edgeInfo = "<br /><small>via edge <a href='#' onclick='return panto_edge("+idEdge+");'>#"+idEdge+"</a> ";
	if (edgeNetworkPreview[idPoly].edgeData.edge_name != null) {
		edgeInfo += '('+edgeNetworkPreview[idPoly].edgeData.edge_name+')'
	}
	edgeInfo += "</small>";
	$("#table_routeedge tbody").append(
			'<tr><td><a href="javascript:void(0);" onclick="return routeeditor_draw_movecursor('+(lastId)+');">'+
			idNodeDest+'</a></td><td>'+activeMarkers[idMarker].nodeData.node_name+edgeInfo+'</td></tr>');
	$("#container_tablerouteedge").scrollTop($('#container_tablerouteedge').height());
	
	activeRouteNodeSequence.push(idNodeDest);
	activeRouteEdgeSequence.push(idEdge);
	
	return true;
}
//-------- ROUTE EDITOR ------------------------------
function routeeditor_clear_workspace(oldState, newState) {
	activeRouteNodeSequence = [];
	activeRouteEdgeSequence = [];
	
	clear_polyline_array(activeRouteLines);
	clear_polyline_array(suggestionPolylines);
	$("#table_routeedge tbody").empty();
	
	if ((newState != STATE_DRAWROUTE) && (newState != STATE_ROUTEEDITOR)) {
		activeRouteId = null;
		
		//-- Reshow all markers
		activeMarkers.map(function(curMarker, i){
			curMarker.setVisible(true);
			markerCluster.addMarker(curMarker, false);
		});
		
		//-- Show edges
		edgeNetworkPreview.map(function(curEdge, i){
			curEdge.setOptions({
				strokeOpacity: SYS_EDGEEDITOR_DEF_OPACITY,
			});
		});
	}
	
}

function init_routeeditor() {
	change_state(STATE_ROUTEEDITOR, routeeditor_clear_workspace);
	
	//-- Hide node markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(false);
	});
	markerCluster.clearMarkers();
	
	//-- Fade edges
	edgeNetworkPreview.map(function(curEdge, i){
		curEdge.setOptions({
			strokeOpacity: (SYS_EDGEEDITOR_DEF_OPACITY / 2),
		});
	});
	
	update_gui();
	return false;
}

function routeeditor_vertexclick(clickedMarker) {
	if (currentState == STATE_DRAWROUTE) {
		routeeditor_draw_nextcursor(clickedMarker.id_node);
	}
}
function new_route() {
	change_state(STATE_DRAWROUTE, routeeditor_clear_workspace);
	
	activeRouteId = null;
	routeeditor_update_([], []);
	update_gui();
	return false;
}

function select_route() {
	show_modal(URL_MODAL, {
		'name': 'route.load'
	}, function(response){
		hide_modal();
		reset_gui();
	}, function(){
		
	});
	
	return false;
}

function routeeditor_update_(newNodeSeq, newEdgeSeq) {
	if (currentState != STATE_DRAWROUTE) return;

	//-- Reset editor workspace
	routeeditor_clear_workspace(currentState, STATE_DRAWROUTE);
	
	var i = 0; var iLen = newNodeSeq.length;
	
	//-- Starting node
	if (iLen > 0) {
		var idMarker = _get_idmarker_by_idnode(newNodeSeq[0]);
		if (idMarker == null) {
			return;
		}
		
		activeRouteNodeSequence.push(newNodeSeq[0]);
		$("#table_routeedge tbody").append(
				'<tr><td><a href="javascript:void(0);" onclick="return routeeditor_draw_movecursor(0);">' + newNodeSeq[0] +
				'</a></td><td>'+activeMarkers[idMarker].title+'</td></tr>');
	}
	
	
	//-- Iterasi mulai elemen node kedua
	for (i = 1; i < iLen; i++) {
		if (!routeeditor_push_routeline(newEdgeSeq[i-1], newNodeSeq[i])) {
			alert('Route is broken: @'+i);
			break;
		}
	}
	
	//-- Show all marker for select
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(true);
		markerCluster.addMarker(curMarker, false);
	});
	
	update_gui();
}
function load_route(idRoute) {
	postData = {
		verb: 'route.get',
		id_route: idRoute,
	}
	_ajax_send(postData, function(response){
		change_state(STATE_DRAWROUTE, routeeditor_clear_workspace);
		
		$('#txt_route_code').val(response.data.route_code);
		$('#txt_route_name').val(response.data.route_name);
		
		activeRouteId = response.data.id_route;
		routeeditor_update_(response.data.node_seq, response.data.edge_seq);
		hide_modal();
	}, "Memproses", URL_DATA_AJAX);
	
	return false;
}

function route_save(formElmt) {
	if (currentState != STATE_DRAWROUTE) return false;
	
	var formData = $(formElmt).serializeArray();
	var postData = {};
	
	var i; var iLen = formData.length;
	for (i = 0; i < iLen; i++) {
		postData[formData[i].name] = formData[i].value;
	}
	
	postData['id_route'] = activeRouteId;
	postData['seq_edge'] = activeRouteEdgeSequence;
	postData['seq_node'] = activeRouteNodeSequence;
	
	_ajax_send(postData, function(jsonData){
		activeRouteId = jsonData.data.new_id_route;
		
		toastr.success('Route successfully saved.');
	}, "Menyimpan...", URL_DATA_AJAX);
	return false;
}

function routeeditor_draw_showneighbors() {
	if (currentState != STATE_DRAWROUTE) return;
	
	clear_polyline_array(suggestionPolylines);
	
	var lastId = activeRouteNodeSequence.length-1;
	var lastNodeId = activeRouteNodeSequence[lastId];
	
	var newId;
	//-- Search for neighbor edges.
	if (lastNodeId in neighborNodeCache_) {
		var isFound = false;
		var i = 0; var iLen = neighborNodeCache_[lastNodeId].length;
		for (i = 0; i < iLen; i++) {
			var polyId = _get_idpolyline_by_idedge(neighborNodeCache_[lastNodeId][i].id_edge);
			if (polyId == null) break;
			
			//-- Pastikan ada edge keluar
			if (edgeNetworkPreview[polyId].edgeData.reversible || (edgeNetworkPreview[polyId].edgeData.id_node_from == lastNodeId)) {
				var newPath = edgeNetworkPreview[polyId].getPath();
				newId = suggestionPolylines.length;
				suggestionPolylines.push(new google.maps.Polyline({
					path: newPath,
					geodesic: false,
					strokeColor: '#444',
					strokeOpacity: 1.0,
					strokeWeight: 3,
					clickable: true,
					map: map,
					id_edge: edgeNetworkPreview[polyId].id_edge,
					next_node_id: neighborNodeCache_[lastNodeId][i].id_node_adj,
					zIndex: 101
				}));
				
				google.maps.event.addListener(suggestionPolylines[newId], 'click', function(){
					routeeditor_draw_nextcursor(this.next_node_id, this.id_edge);
				});
			}
			
			
		} // End for
		
	} // End if neighbor exist
	
}

function routeeditor_draw_nextcursor(idNode, idEdge) {
	if (currentState != STATE_DRAWROUTE) return;
	
	var idMarker = _get_idmarker_by_idnode(idNode);
	if (!idMarker) return;
	
	var clickedMarker = activeMarkers[idMarker];
	
	if (activeRouteNodeSequence.length == 0) {
		activeRouteNodeSequence.push(idNode);
		
		$("#table_routeedge tbody").append(
				'<tr><td><a href="javascript:void(0);" onclick="return routeeditor_draw_movecursor(0);">' + clickedMarker.id_node+
					'</a></td><td>'+clickedMarker.title+'</td></tr>');
		routeeditor_draw_showneighbors();
	} else {
		var lastId = activeRouteNodeSequence.length-1;
		var lastNodeId = activeRouteNodeSequence[lastId];
		
		//-- Search for neighbor edges. Check if the node is adjacent with last node.
		if (lastNodeId in neighborNodeCache_) {
			var isFound = false;
			var i = 0; var iLen = neighborNodeCache_[lastNodeId].length;
			for (i = 0; i < iLen; i++) {
				if (neighborNodeCache_[lastNodeId][i].id_node_adj == clickedMarker.id_node) {
					//-- Cek id_edge jika parameter disediakan...
					if (idEdge && (idEdge != neighborNodeCache_[lastNodeId][i].id_edge)) {
						continue;
					}
					
					//-- Push routeline
					var selIdEdge = neighborNodeCache_[lastNodeId][i].id_edge;
					if (!routeeditor_push_routeline(selIdEdge, clickedMarker.id_node)) {
						continue; // Lanjutkan neighbor lain jika proses gagal.
					}
					
					isFound = true;
					
					map.panTo(clickedMarker.getPosition());
					routeeditor_draw_showneighbors();
					break;
				}
			}
			
			if (!isFound) {
				alert("Selected node is not adjacent with previous node. Create edge or select another node.");
			}
		} else {
			alert("Selected node doesn't have neighbor. Create edge or select another node.");
		}
	}
}

function routeeditor_draw_movecursor(idxDest) {
	if (idxDest < 0) return;
	if (currentState != STATE_DRAWROUTE) return;
	
	var seqLen = activeRouteNodeSequence.length;
	if (seqLen == 0) return;
	
	if (idxDest < (seqLen-1)) {
		var i = 0;
		for (i = seqLen-1; i > idxDest; i--) {
			activeRouteNodeSequence.pop();
			activeRouteEdgeSequence.pop();
			activeRouteLines.pop().setMap(null);
			
			$( "#table_routeedge tbody tr:last" ).remove();
			$("#container_tablerouteedge").scrollTop($('#container_tablerouteedge').height());
		}
		
		routeeditor_draw_showneighbors();
		
		var lastMarkerId = _get_idmarker_by_idnode(activeRouteNodeSequence[idxDest]);
		map.panTo(activeMarkers[lastMarkerId].getPosition());
	} else if (idxDest == 0) {
		var lastMarkerId = _get_idmarker_by_idnode(activeRouteNodeSequence[idxDest]);
		map.panTo(activeMarkers[lastMarkerId].getPosition());
		
		activeRouteNodeSequence.pop();
		$("#table_routeedge tbody tr:last").remove();
		$("#container_tablerouteedge").scrollTop($('#container_tablerouteedge').height());
	}
	return false;
}
function routeeditor_draw_backward() {
	var seqLen = activeRouteNodeSequence.length-1;
	if (seqLen > 0) {
		routeeditor_draw_movecursor(seqLen-1);
	}
	return false;
}


//-------- ROUTE DEBUGGER ------------------------------
function routedebug_clear_workspace() {
	clear_dirlines();
	
	//-- Reshow all markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(true);
		markerCluster.addMarker(curMarker, false);
	});
	
	//-- Show edges
	edgeNetworkPreview.map(function(curEdge, i){
		curEdge.setOptions({
			strokeOpacity: SYS_EDGEEDITOR_DEF_OPACITY,
		});
	});
}

function init_routedebug() {
	change_state(STATE_ROUTEDEBUG, routedebug_clear_workspace);
	
	//-- Hide node markers
	activeMarkers.map(function(curMarker, i){
		curMarker.setVisible(false);
	});
	markerCluster.clearMarkers();
	
	//-- Fade edges
	edgeNetworkPreview.map(function(curEdge, i){
		curEdge.setOptions({
			strokeOpacity: (SYS_EDGEEDITOR_DEF_OPACITY / 2),
		});
	});
	
	update_gui();
	return false;
}