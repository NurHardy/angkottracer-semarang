/**
 * home.js
 * ----------------------------------
 * File JavaScript untuk halaman depan aplikasi angkotTracer
 * 
 */

	var map;
	
	var nodeMarkerMap_ = {}; // Hash memetakan id_node ke index activeMarkers;
	
	var activeMarkers = [];
	var activeMarkerId = [];
	var focusedMarker = null;
	
	var activeLines = [];
	var activeDirLines = [];
	
	var activeEditingPolyLine;
	
	var edgeNetworkPreview = [];
	
	var labels = 'ABCDEFGHIJKLMNOPQRSTUVWYZ';
	var iconBase = 'https://maps.google.com/mapfiles/kml/shapes/';

	// Marker saat editing edge.
	var labelMarkers = [null, null];
	
	//-- Context menu
	var ctxMenu;
	
	// Cursor saat node terpilih.
	var nodeCursor = null;
	
	var currentState;
	 
	//-- Marker clusterer
	var markerCluster;
	
	//-- Callbacks
	var node_selected_callback;		// Node selected callback
	var _clear_workspace_callback;	// Workspace clear up callback
	
	//-------- GUI Functions ----------------------------
	function update_gui(resetEditingPolyLine) {
		if ((resetEditingPolyLine === true) || (resetEditingPolyLine == undefined)) {
			//-- Disable active editing line
			if (activeEditingPolyLine) {
				activeEditingPolyLine.setMap(null);
			}
		}
		
		$('.site_actionpanel, .site_defaultpanel').hide();
		$('#site_floatpanel .fpanel_item, #fpanel_home').hide();
		map.setOptions({ draggableCursor: 'url(http://maps.google.com/mapfiles/openhand.cur), move' });
		if (currentState == STATE_PLACENODE) {
			map.setOptions({ draggableCursor: 'crosshair' });
			$('#site_panel_placenode').show();
		} else if (currentState == STATE_NODESELECTED) {
			$('#site_panel_nodeselected, #fpanel_nodeedit').show();
		} else if (currentState == STATE_EDGESELECTED) {
			$('#site_panel_selectedge, #fpanel_edgeedit').show();
		} else if (currentState == STATE_SELECTNODE) {
			$('#site_panel_selectnode').show();
		} else if (currentState == STATE_MOVENODE) {
			map.setOptions({ draggableCursor: 'crosshair' });
			$('#site_panel_movenode, #fpanel_nodemove').show();
		} else { // Default
			$('#fpanel_home').show();
			$('.site_defaultpanel').show();
		}
	}
	
	/*
	 * Ganti state GUI. Panggil fungsi clear workspace dan ganti fungsi dengan yang baru.
	 */
	function change_state(newState, clearCallback) {
		if (currentState != newState) {
			if (typeof(_clear_workspace_callback) === 'function') {
				_clear_workspace_callback(currentState, newState);
			}
			_clear_workspace_callback = clearCallback;
			currentState = newState;
		}
	}
	
	function reset_gui() {
		change_state(STATE_DEFAULT, null);
		update_gui();
	}
	function map_click(e) {
		if (currentState == STATE_PLACENODE) {
			if (typeof(_new_vertex) === 'function') _new_vertex(e);
			else {
				alert("Error happened! Please reload.");
			}
			
		// End if currentState is PLACENODE
		} else if (currentState == STATE_MOVENODE) {
			nodeCursor.setPosition(e.latLng);
		}
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
				toastr.success('Done. ' + jsonData.data.benchmark);
				
				var edgeCount = jsonData.data.sequence.length;
				var prevPosition = null;
				for (ctr = 0; ctr < edgeCount; ctr++) {
					if (prevPosition != null) {
						activeDirLines.push(new google.maps.Polyline({
							path: [prevPosition, jsonData.data.sequence[ctr].position],
							geodesic: false,
							strokeColor: '#B0A800',
							strokeOpacity: 1.0,
							strokeWeight: 3,
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
	
	/**************************************
	 * GLOBAL FUNCTIONS
	 **************************************/
	function clear_markers() {
		nodeMarkerMap_ = {};
		/*
		 * if (activeMarkers[ctr].id_node in nodeMarkerMap_) {
				delete nodeMarkerMap_[activeMarkers[ctr].id_node];
			}
		 */
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
	
	function rebuild_markermap_() {
		nodeMarkerMap_ = {};
		
		var markerCount = activeMarkers.length;
		var ctr;
		for (ctr=0; ctr < markerCount; ctr++) {
			nodeMarkerMap_[activeMarkers[ctr].id_node] = ctr;
		}
	}
	/**************************************
	 * GUI FUNCTIONS
	 **************************************/
	
	//-- Tambah node ke GUI
	function _gui_push_node(idNode, latLngPos, nodeName) {
		var newId = activeMarkers.length;
		
		var tmpMarker = new google.maps.Marker({
			id_node: idNode,
			id_marker: newId,
			position: latLngPos,
			map: map,
			title: nodeName,
			icon: MARKERBASE + 'dot-red.png'
		});
		
		activeMarkers.push(tmpMarker);
		nodeMarkerMap_[idNode] = newId;
		
		google.maps.event.addListener(tmpMarker, 'click', marker_click);
	}
	
	//-- Tambah edge ke GUI
	function _gui_push_edge(idEdge, edgePath, isReversible, edgeData, idNodeFrom, idNodeDest) {
		edgeNetworkPreview.push(new google.maps.Polyline({
			id_edge: idEdge,
			edge_data: {
				node_from: idNodeFrom,
				node_dest: idNodeDest
			},
			reversible: isReversible,
			path: edgePath,
			geodesic: false,
			strokeColor: (isReversible ? SYS_MULTIDIR_POLYLINE_COLOR : SYS_SINGLEDIR_POLYLINE_COLOR),
			strokeOpacity: 0.75,
			strokeWeight: 2,
			clickable: false,
			map: map,
			icons: (isReversible ? [] : SYS_SINGLEDIR_POLYLINE_ICONS)
		}));
	}
	
	//-- Modify edge di GUI
	function _gui_modify_edge(idEdge, edgePath, isReversible) {
		var i;
		for (i=0; i < edgeNetworkPreview.length; i++) {
			if (edgeNetworkPreview[i].id_edge == idEdge) {
				if (edgePath === null) {
					// Hapus...
					edgeNetworkPreview[i].setMap(null);
					edgeNetworkPreview.splice(i, 1);
				} else {
					edgeNetworkPreview[i].setPath(edgePath);
					edgeNetworkPreview[i].setOptions({
						reversible: isReversible,
						icons: (isReversible ? [] : SYS_SINGLEDIR_POLYLINE_ICONS),
			            strokeColor: (isReversible ? SYS_MULTIDIR_POLYLINE_COLOR : SYS_SINGLEDIR_POLYLINE_COLOR)
		            });
				}
				break;
			}
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
				_gui_push_node(jsonData.data[ctr].id, jsonData.data[ctr].position,
						'#'+jsonData.data[ctr].id+': '+jsonData.data[ctr].name);
				$('#site_nodeselector select[name=nodeid]').append(
					'<option value="'+(jsonData.data[ctr].id)+'">'+jsonData.data[ctr].name+'</option>');
			}
			
			//------- Load edge network preview
			dLength = jsonData.edge.length;
			for (ctr=0; ctr < dLength; ctr++) {
				var decodedPath = google.maps.geometry.encoding.decodePath(jsonData.edge[ctr].polyline);
				_gui_push_edge(jsonData.edge[ctr].id_edge, decodedPath, jsonData.edge[ctr].reversible,
						jsonData.edge[ctr].id_node_from, jsonData.edge[ctr].id_node_dest);
			}
			// Add a marker clusterer to manage the markers.
	        markerCluster = new MarkerClusterer(map, activeMarkers,{
	        		imagePath: MARKERBASE + 'm',
	        		maxZoom: 16
	        });
		}, "Initializing...", URL_DATA_AJAX);
		return false;
	}
	
	function init_map() {
		//-- Set constants
		SYS_SINGLEDIR_POLYLINE_ICONS.push({
	        icon: {path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW},
	        offset: '100%',
	        repeat:'50px'
	    });
		
		//-- Load components
		loadScripts(scripts, function(){
			download_json();
			reset_gui();
		});
		
		//-- Setup map
		var noPoi = [{
		    featureType: "poi",
		    stylers: [
		      { visibility: "off" }
		    ]   
		  }
		];

		map = new google.maps.Map(document.getElementById('site_googlemaps'), {
			streetViewControl: false,
			zoom: 14,
			center: {lat: -6.985525006479515, lng: 110.46021435493}
		});
		map.setOptions({styles: noPoi});
	
		map.addListener('click', map_click);
		
		//-- Listener esc untuk tutup jendela modal
		google.maps.event.addDomListener(document, 'keyup', function (e) {
		    var code = (e.keyCode ? e.keyCode : e.which);
		    if (code === 27) {
		    	if ($("#site_overlay_modal").is(":visible")) {
		    		if (typeof(_on_modal_cancelled) === 'function')
		    			_on_modal_cancelled();
		    	} else {
		    		reset_gui();
		    	}
		    }
		});
		/*
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
		}); */
	
		//flightPath.setMap(map);
		$('#site_nodeselector select[name=nodeid]').change(function(){
			focus_node($(this).val());
		});
		
		toastr.options = {
		  "positionClass": "toast-bottom-center"
		};
	}