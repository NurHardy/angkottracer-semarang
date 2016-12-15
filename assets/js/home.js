/**
 * home.js
 * ----------------------------------
 * File JavaScript untuk halaman depan aplikasi angkotTracer
 * 
 */

	var map;
	var activeMarkers = [];
	var activeMarkerId = [];
	var focusedMarker = null;
	
	var activeLines = [];
	var activeDirLines = [];
	var labels = 'ABCDEFGHIJKLMNOPQRSTUVWYZ';
	var iconBase = 'https://maps.google.com/mapfiles/kml/shapes/';

	var nodeCursor = null;
	var currentState;
	 
	//-- Callbacks
	var node_selected_callback;		// Node selected callback
	//-------- GUI Functions ----------------------------
	function update_gui() {
		$('.site_actionpanel, .site_defaultpanel').hide();
		map.setOptions({ draggableCursor: 'url(http://maps.google.com/mapfiles/openhand.cur), move' });
		if (currentState == STATE_PLACENODE) {
			map.setOptions({ draggableCursor: 'crosshair' });
			$('#site_panel_placenode').show();
		} else if (currentState == STATE_NODESELECTED) {
			$('#site_panel_nodeselected').show();
		} else if (currentState == STATE_EDGESELECTED) {
			$('#site_panel_selectedge').show();
		} else if (currentState == STATE_SELECTNODE) {
			$('#site_panel_selectnode').show();
		} else { // Default
			$('.site_defaultpanel').show();
		}
	}
	
	function reset_gui() {
		currentState = STATE_DEFAULT;
		update_gui();
	}
	function map_click(e) {
		if (currentState == STATE_PLACENODE) {
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
		} // End if currentState is PLACENODE
	}
	function marker_click() {
		if ((currentState == STATE_DEFAULT) || (currentState == STATE_NODESELECTED)) {
			focusedMarker = this;
			focus_node(this.id_node);
		} else if (currentState == STATE_SELECTNODE) {
			if (typeof(node_selected_callback) === 'function') {
				node_selected_callback(this);
			}
		}
	}
	
	function delete_edge(idNode, idEdge, afterDeleteCallback) {
		var uConf = confirm('Hapus busur?');
		if (!uConf) return false;
		_ajax_send({
			verb: 'edge.delete',
			id: idEdge
		}, function(jsonData){
			if (typeof(afterDeleteCallback) === 'function') {
				afterDeleteCallback(jsonData);
			}
			focus_node(idNode);
		}, "Memproses...", URL_DATA_AJAX);
		
		return false;
	}
	
	function interpolate_edge(idNode, idEdge) {
		var uConf = confirm('Interpolasi busur?');
		if (!uConf) return false;
		_ajax_send({
			verb: 'edge.refine',
			id: idEdge
		}, function(jsonData){
			var polylineNodes = [];
			var nodeCount = jsonData.data.length;
			var ctr;
			for (ctr = 0; ctr < nodeCount; ctr++) {
				//nodeCount.push({lat: , lng: });
			}
			//-- Draw polilines in the map
			activeLines.push(new google.maps.Polyline({
				path: jsonData.data,
				geodesic: false,
				strokeColor: '#162953',
				strokeOpacity: 1.0,
				strokeWeight: 2,
				clickable: false,
				editable: true,
				map: map
			}));
			//alert(jsonData.data);
		}, "Memproses...", URL_DATA_AJAX);
		
		return false;
	}

	function focus_node(nodeId) { // nodeId di database
		_ajax_send({
			verb: 'node.getbyid',
			id: nodeId
		}, function(jsonData){
			map.panTo(jsonData.nodedata.position);
			$("#table_edge tbody").empty();
	
			var edgeCount = jsonData.edges.length;
			var ctr; var reversibleLabel;
			
			clear_lines();
			for (ctr = 0; ctr < edgeCount; ctr++) {
				activeLines.push(new google.maps.Polyline({
					path: jsonData.edges[ctr].polyline_data, //[jsonData.nodedata.position, jsonData.edges[ctr].position],
					geodesic: false,
					strokeColor: '#FF0000',
					strokeOpacity: 1.0,
					strokeWeight: 1,
					clickable: false,
					map: map
				}));
				
				reversibleLabel = (jsonData.edges[ctr].reversible?"Y":"N");
				$("#table_edge tbody").append(
						'<tr><td><a href="#" onclick="return focus_node('+jsonData.edges[ctr].id+');" title="'+jsonData.edges[ctr].name+'">'+
						jsonData.edges[ctr].id+'</a>'+
						'</td><td>'+jsonData.edges[ctr].distance+' km'+
						'</td><td>'+reversibleLabel+'</td><td> '+
						'<a href="#" onclick="return edit_edge('+jsonData.edges[ctr].id_edge+');">edit</a> | ' + 
						'<a href="#" onclick="return interpolate_edge('+nodeId+','+jsonData.edges[ctr].id_edge+');">interpolate</a> | ' + 
						'<a href="#" onclick="return delete_edge('+nodeId+','+jsonData.edges[ctr].id_edge+');">hapus</a>' + 
						'</td></tr>');
			}
			
			currentState = STATE_NODESELECTED;
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
				nodeCursor.setPosition(jsonData.nodedata.position);
			}
		}, "Memuat...", URL_DATA_AJAX);
		return false;
	}
	function get_direction() {
		if (focusedMarker == null) return;
		
		currentState = STATE_SELECTNODE;
		node_selected_callback = function (selectedMarker) {
			_ajax_send({
				verb: 'algorithm.astar',
				id_node_start: selectedMarker.id_node,
				id_node_end: focusedMarker.id_node
			}, function(jsonData){
				clear_dirlines();
				var edgeCount = jsonData.data.sequence.length;
				var prevPosition = null;
				for (ctr = 0; ctr < edgeCount; ctr++) {
					if (prevPosition != null) {
						activeDirLines.push(new google.maps.Polyline({
							path: [prevPosition, jsonData.data.sequence[ctr].position],
							geodesic: false,
							strokeColor: '#0000FF',
							strokeOpacity: 1.0,
							strokeWeight: 2,
							clickable: false,
							map: map
						}));
					}
					prevPosition = jsonData.data.sequence[ctr].position;
				}
			}, "Memproses...", URL_ALGORITHM_AJAX);
			
			reset_gui();
		};
		update_gui();
	}
	
	function new_node() {
		currentState = STATE_PLACENODE;
		update_gui();
	}
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
				
				reversibleLabel = (response.data.reversible?"Yes":"No");
				$("#table_edge tbody").append(
						'<tr><td>'+response.data.id+
						'</td><td>'+response.data.distance+
						'</td><td>'+reversibleLabel+'</td><td>edit | <a href="#">hapus</a></td></tr>');
				
				hide_modal();
				reset_gui();
			}, function(){
				
			});
		};
		update_gui();
	}
	
	function edit_edge(idEdge) {
		//-- Fetch data
		_ajax_send({
			verb: 'edge.getbyid',
			id: idEdge
		}, function(jsonData){
			currentState = STATE_EDGESELECTED;
			clear_lines();
			
			var polyLineData = jsonData.edgedata.polyline_data;
			polyLineData.unshift(jsonData.edgedata.from.position);
			polyLineData.push(jsonData.edgedata.dest.position);
			
			//-- Draw polylines in the map
			var editingPolyLine = new google.maps.Polyline({
				path: polyLineData,
				geodesic: false,
				strokeColor: '#162953',
				strokeOpacity: 1.0,
				strokeWeight: 2,
				clickable: false,
				editable: true,
				map: map
			});
			
			activeLines.push(editingPolyLine);
			
			var deleteMenu;
			deleteMenu = new DeleteMenu();
			
			google.maps.event.addListener(editingPolyLine, 'rightclick', function(e) {
				// Check if click was on a vertex control point
				if (e.vertex == undefined) {
					return;
				}
				deleteMenu.open(map, editingPolyLine.getPath(), e.vertex);
			});

		}, "Memuat...", URL_DATA_AJAX);
		
		update_gui();
	}
	function clear_markers() {
		var dLength = activeMarkers.length;
		var ctr;
		for (ctr=dLength-1; ctr >= 0; ctr--) {
			activeMarkers[ctr].setMap(null);
			activeMarkers.splice(ctr, 1);
		}
	}
	function clear_lines() {
		var dLength = activeLines.length;
		var ctr;
		for (ctr=dLength-1; ctr >= 0; ctr--) {
			activeLines[ctr].setMap(null);
			activeLines.splice(ctr, 1);
		}
	}
	function clear_dirlines() {
		var dLength = activeDirLines.length;
		var ctr;
		for (ctr=dLength-1; ctr >= 0; ctr--) {
			activeDirLines[ctr].setMap(null);
			activeDirLines.splice(ctr, 1);
		}
	}
	function download_json() {
		_ajax_send({
			verb: 'node.get'
		}, function(jsonData){
			clear_markers();
			var dLength = jsonData.data.length;
			var ctr;
			for (ctr=0; ctr < dLength; ctr++) {
				tmpMarker = new google.maps.Marker({
					position: jsonData.data[ctr].position,
					map: map,
					//label: labels[ctr % 26],
					title: '#'+jsonData.data[ctr].id+': '+jsonData.data[ctr].name,
					id_node: jsonData.data[ctr].id,
					icon: MARKERBASE + 'dot-red.png'
				});
				
				activeMarkers.push(tmpMarker);
				google.maps.event.addListener(tmpMarker, 'click', marker_click);
				$('#site_nodeselector select[name=nodeid]').append(
					'<option value="'+(jsonData.data[ctr].id)+'">'+jsonData.data[ctr].name+'</option>');
			}
		}, "Mengunduh...", URL_DATA_AJAX);
		return false;
	}
	
	function init_map() {
		//-- Load components
		loadScripts(scripts, function(){
			download_json();
			reset_gui();
		});
		  
		//-- Setup map
		map = new google.maps.Map(document.getElementById('site_googlemaps'), {
			streetViewControl: false,
			zoom: 14,
			center: {lat: -6.985525006479515, lng: 110.46021435493}
		});
	
		map.addListener('click', map_click);
		var gadjahmada = [
			{lat: -6.989012710126586, lng: 110.4227093610417},
			{lat: -6.988825841377558, lng: 110.4227603227451},
			{lat: -6.988569138869423, lng: 110.4227642023467},
			{lat: -6.988278086828849, lng: 110.4227390275668},
			{lat: -6.987862299792476, lng: 110.4226958387416},
			{lat: -6.987478119569217, lng: 110.4226213734101},
			{lat: -6.987092119396447, lng: 110.4225445545423},
			{lat: -6.986689296955397, lng: 110.4224654566942},
			{lat: -6.986405664686092, lng: 110.4224057513408},
			{lat: -6.986079646350691, lng: 110.4223366662844}
		];
		var flightPath = new google.maps.Polyline({
			path: gadjahmada,
			geodesic: false,
			strokeColor: '#FF0000',
			strokeOpacity: 1.0,
			strokeWeight: 2,
			clickable: false
		});
	
		//flightPath.setMap(map);
		$('#site_nodeselector select[name=nodeid]').change(function(){
			focus_node($(this).val());
		});
	}