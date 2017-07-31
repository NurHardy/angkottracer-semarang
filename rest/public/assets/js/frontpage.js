/**
 * js/frontpage.js
 * ----------------
 * Javascipt halaman depan utama (publik)...
 * 
 */

var map;

var currentState;

//-- Put state 'session' data here...
var currentStateData = [];
var _clear_workspace_callback = null;

var placeService = null;

var fromMarker = null;
var destMarker = null;

function set_from_point(fromLatLng) {
	if (fromMarker === null) {
		fromMarker = new google.maps.Marker({
			position: fromLatLng,
			map: map,
			title: "Start point",
			label: "A"
		});
	} else {
		fromMarker.setPosition(fromLatLng);
	}
	
	var latFix = fromMarker.getPosition().lat().toFixed(7);
	var lngFix = fromMarker.getPosition().lng().toFixed(7);
	
	$('#site_start_geo').html('<i class="fa fa-map-marker"></i> (' + latFix + ', ' + lngFix + ')');
	
	if (destMarker === null) {
		map.panTo(fromLatLng);		
	} else {
		panto_start_dest();
	}
	
	refresh_search_form();
}

function set_dest_point(destLatLng) {
	if (destMarker === null) {
		destMarker = new google.maps.Marker({
			position: destLatLng,
			map: map,
			title: "Finish point",
			label: "B"
		});
	} else {
		destMarker.setPosition(destLatLng);
	}
	
	var latFix = destMarker.getPosition().lat().toFixed(7);
	var lngFix = destMarker.getPosition().lng().toFixed(7);
	
	$('#site_dest_geo').html('<i class="fa fa-map-marker"></i> (' + latFix + ', ' + lngFix + ')');
	map.panTo(destLatLng);
	
	if (fromMarker === null) {
		map.panTo(fromLatLng);		
	} else {
		panto_start_dest();
	}
	
	refresh_search_form();
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
		currentStateData = [];
	}
}

//Cancel picker, bring current state to the last state before picker state.
function cancel_picker() {
	if ('lastState' in currentStateData) {
		if (currentStateData.lastState == STATE_NODESELECTED) {
			
		} else {
			change_state(STATE_DEFAULT, null);
			update_gui();
		}
	} else {
		change_state(STATE_DEFAULT, null);
		update_gui();
	}
	
	return false;
}

function update_gui() {
	$('.site_actionpanel, .site_defaultpanel').hide();
	$('#site_floatpanel .fpanel_item, #fpanel_home, #site_floatpanel_extension, .fpanelext_item').hide();
	map.setOptions({ draggableCursor: 'url(http://maps.google.com/mapfiles/openhand.cur), move' });
	
	//-- Setup active menu
	
	if (currentState == STATE_PLACENODE) {
		map.setOptions({ draggableCursor: 'crosshair' });
		$('#site_panel_placenode').show();
	} else if (currentState == STATE_ROUTERESULT) {
		$('#site_panel_searchresult').show();
	} else { // Default
		$('#fpanel_home').show();
		$('.site_defaultpanel').show();
	}
}

var _placenode_callback = null;

function place_node_do(pickerMessage, clickCallback) {
	currentState = STATE_PLACENODE;
	$('#site_panel_placenode p.message').html(pickerMessage);
	
	if (typeof(clickCallback) === 'function') {
		_placenode_callback = clickCallback;
	} else {
		_placenode_callback = null;
	}
	update_gui();
}

function pick_start_point() {
	place_node_do("Klik pada map untuk meletakkan start point.", function(newPos) {
		set_from_point(newPos.position);
		$('#txt_node_start').val(newPos.name);
	});
	return false;
}

function pick_dest_point() {
	place_node_do("Klik pada map untuk meletakkan destination point.", function(newPos) {
		set_dest_point(newPos.position);
		$('#txt_node_dest').val(newPos.name);
	});
	return false;
}

function _place_node_click(e) {
	var selectedPoint = e.latLng;
	var clickLat = selectedPoint.lat();
	var clickLng = selectedPoint.lng();
	
	if (e.placeId) {
		//console.log('You clicked on place:' + e.placeId);
		placeService.getDetails({placeId: e.placeId}, function(place, status) {
			var placeLabel = "Unknown";
			if (status === 'OK') {
				//place.icon;
				//place.name;
				//place.formatted_address;
				placeLabel = place.name + ", " + place.formatted_address;
			}
			if (typeof(_placenode_callback) === 'function') {
				_placenode_callback({position:{lat:clickLat,lng:clickLng},name:placeLabel});
			}
		});

		e.stop();
	} else {
		if (typeof(_placenode_callback) === 'function') {
			_placenode_callback({position:{lat:clickLat,lng:clickLng},name:'Manual'});
		}
	}
	
	cancel_picker();
}
function map_click(e) {
	if (currentState == STATE_PLACENODE) {
		if (typeof(_place_node_click) === 'function') {
			_place_node_click(e);
		}
		
	// End if currentState is PLACENODE
	}
}

function panto_start_dest() {
	if ((fromMarker === null) || (destMarker === null)) return;
	
	//-- Pan viewport
	var bounds = new google.maps.LatLngBounds();
	bounds.extend(fromMarker.getPosition());
	bounds.extend(destMarker.getPosition());
	
	map.fitBounds(bounds);
	return false;
}

function init_map() {
	currentState = STATE_DEFAULT;
	
	SYS_SINGLEDIR_POLYLINE_ICONS.push({
        icon: {path: google.maps.SymbolPath.FORWARD_CLOSED_ARROW},
        offset: '100%',
        repeat:'50px'
    });
	
	SYS_NODEMARKER_ICON = {
		url: MARKERBASE + 'dot-red.png',
		size: new google.maps.Size(9, 9),
		origin: new google.maps.Point(0, 0),
		anchor: new google.maps.Point(5, 5)
	};
	
	map = new google.maps.Map(document.getElementById('site_googlemaps'), {
		streetViewControl: false,
		zoom: 16,
		center: {lat: -6.990402, lng: 110.422958},
		mapTypeControl: false
	});

	var input1 = document.getElementById('txt_node_start');
	var input2 = document.getElementById('txt_node_dest');
	var autocomplete1 = new google.maps.places.Autocomplete(input1);
	var autocomplete2 = new google.maps.places.Autocomplete(input2);

	map.addListener('click', map_click);
	
	placeService = new google.maps.places.PlacesService(map);
    // Bind the map's bounds (viewport) property to the autocomplete object,
    // so that the autocomplete requests use the current map bounds for the
    // bounds option in the request.
    autocomplete1.bindTo('bounds', map);
    
    autocomplete1.setComponentRestrictions({country: 'id'});

    autocomplete1.addListener('place_changed', function() {
    	var place = autocomplete1.getPlace();
    	//console.log(place);

    	if (!place.geometry) {
            // User entered the name of a Place that was not suggested and
            // pressed the Enter key, or the Place Details request failed.
            window.alert("No details available for input: '" + place.name + "'");
            return;
		}

		// If the place has a geometry, then present it on a map.
		if (place.geometry.viewport) {
			map.fitBounds(place.geometry.viewport);
		} else {
			map.setCenter(place.geometry.location);
			//map.setZoom(17);  // Why 17? Because it looks good.
		}

		set_from_point(place.geometry.location);
    });

    autocomplete2.setComponentRestrictions({country: 'id'});
    autocomplete2.addListener('place_changed', function() {
    	var place = autocomplete2.getPlace();
    	//console.log(place);

    	if (!place.geometry) {
            // User entered the name of a Place that was not suggested and
            // pressed the Enter key, or the Place Details request failed.
            window.alert("No details available for input: '" + place.name + "'");
            return;
		}

		// If the place has a geometry, then present it on a map.
		if (place.geometry.viewport) {
			map.fitBounds(place.geometry.viewport);
		} else {
			map.setCenter(place.geometry.location);
			//map.setZoom(17);  // Why 17? Because it looks good.
		}

		set_dest_point(place.geometry.location);
    });
}

function refresh_search_form() {
	if ((fromMarker === null) || (destMarker === null)) {
		$('#btn_beginsearch').attr('disabled','disabled');
	} else {
		$('#btn_beginsearch').removeAttr('disabled');
	}
}
function mainform_submit() {
	if ((fromMarker === null) || (destMarker === null)) {
		alert("Starting point or destination point is not specified!");
		return false;
	}
	
	var requestParam = {
		start:{
			lat: fromMarker.getPosition().lat(),
			lng: fromMarker.getPosition().lng()
		},
		dest:{
			lat: destMarker.getPosition().lat(),
			lng: destMarker.getPosition().lng()
		}
	};
	
	var optAvoid = "";
	if (!$('#chk_angkot').is(':checked'))	optAvoid += "angkot,";
	if (!$('#chk_brt').is(':checked'))		optAvoid += "brt,";
	
	if (optAvoid.length > 0) {
		// Trim karakter koma terakhir...
		optAvoid = optAvoid.substr(0, optAvoid.length-1);
		requestParam.avoid = optAvoid;		
	}
	
	if ($('#chk_verbose').is(':checked')) {
		requestParam.verbose = 1;	
	}
	
	_ajax_send(requestParam, function(jsonData){
		console.log(jsonData);
		render_searchresult(jsonData);
	}, "Please wait...", URL_ALGORITHM_AJAX + '/debug', 'GET');
	return false;
}

var activeDirLines = [];
function clear_dirlines() {
	var dLength = activeDirLines.length;
	var ctr;
	for (ctr=dLength-1; ctr >= 0; ctr--) {
		activeDirLines[ctr].setMap(null);
		activeDirLines.splice(ctr, 1);
	}
}

function clear_array(arrayObject) {
	var dLength = arrayObject.length;
	var ctr;
	for (ctr=dLength-1; ctr >= 0; ctr--) {
		arrayObject.splice(ctr, 1);
	}
}

function clear_array_marker(arrayObject) {
	var dLength = arrayObject.length;
	var ctr;
	for (ctr=dLength-1; ctr >= 0; ctr--) {
		arrayObject[ctr].setMap(null);
		arrayObject.splice(ctr, 1);
	}
}

function searchresult_comboupdated() {
	var selectedAltId = $('#combo_routeway_list').val();
	//alert(selectedAltId);
	render_searchresult_route(selectedAltId);
}

var searchResultData = [];
var cursorInfoWindow = null;

function clear_searchresult_data() {
	var dLength = searchResultData.length;
	var ctr; var ctrStep;
	for (ctr=dLength-1; ctr >= 0; ctr--) {
		var stepLength = searchResultData[ctr].length;
		for (ctrStep = stepLength-1; ctrStep >= 0; ctrStep--) {
			if (searchResultData[ctr][ctrStep].marker)
				searchResultData[ctr][ctrStep].marker.setMap(null);
			searchResultData[ctr][ctrStep].polyline.setMap(null);
			searchResultData[ctr].splice(ctrStep, 1);
		}
		//OLD:clear_array_marker(searchResultData[ctr].polylines);
		searchResultData.splice(ctr, 1);
	}
}

function render_searchresult_route(idAlt) {
	$(".listgrp_alt_item").hide();
	$("#listgrp_alt_"+idAlt).fadeIn(250);
	
	//-- Hide all markers
	var searchResultCount = searchResultData.length;
	var ctr;
	for (ctr = 0; ctr < searchResultCount; ctr++) {
		if (ctr == idAlt) {
			//-- Show markers and polylines
			searchResultData[ctr].map(function(curStep, i){
				if (curStep.marker) curStep.marker.setVisible(true);
				curStep.polyline.setVisible(true);
			});
		} else {
			//-- Hide markers and polylines
			searchResultData[ctr].map(function(curStep, i){
				if (curStep.marker) curStep.marker.setVisible(false);
				curStep.polyline.setVisible(false);
			});
		}
	}
	
	select_step(idAlt, 0);
}
function render_searchresult(jsonData) {
	currentState = STATE_ROUTERESULT;
	clear_dirlines();
	
	$("#combo_routeway_list").html(" ");
	$("#listgroup_steps").html(" ");
	var ctr; var ctr2;
	
	//-- Init variables...
	if (!cursorInfoWindow) {
		cursorInfoWindow = new google.maps.InfoWindow({
	        content: '-',
	        selectedStepData: {
	        	idAlt: null,
	        	idStep: null
	        }
		});
		
		cursorInfoWindow.addListener('closeclick', function() {
			select_step(null,null);
		});
	}
	
	var routeCount = jsonData.data.routeways.length;
	
	clear_searchresult_data();
	
	//-- Hide combobox if only one routeway found.
	/*if (routeCount == 1) {
		$("#combo_routeway_list").hide();
	} else {
		$("#combo_routeway_list").show();
	}*/
	
	//-- Foreach alternatives...
	for (ctr = 0; ctr < routeCount; ctr++) {
		var stepsData = [];
		var idAlt = searchResultData.length;
		var newIdx = idAlt+1;;
		
		var stepHtml = "<div class='list-group listgrp_alt_item' id='listgrp_alt_"+idAlt+"' data-idsolution='"+idAlt+"'>";
		$("#combo_routeway_list").append("<option value='"+idAlt+"'>[Cara "+newIdx+"] Rp. "+
				jsonData.data.routeways[ctr].est_cost+", jalan "+jsonData.data.routeways[ctr].walk_length+" km</option>");
		
		var stepCount = jsonData.data.routeways[ctr].steps.length;
		for (ctr2 = 0; ctr2 < stepCount; ctr2++) {
			var newIdStep = ctr2;
			
			stepHtml += '<a href="#" class="list-group-item" onclick="return select_step('+ctr+','+ctr2+');">'+
				'<div class="media"><div class="media-left">' +
				'<img src="'+jsonData.data.routeways[ctr].steps[ctr2].icon+'" style="width:72px;"/></div>' +
				'<div class="media-body">'+ jsonData.data.routeways[ctr].steps[ctr2].html_instruction +'</div></div> </a>';
			
			//-- Create polyline...
			var lineColor = null;
			if (jsonData.data.routeways[ctr].steps[ctr2].type == 'WALK') {
				lineColor = '#999';
			} else if (jsonData.data.routeways[ctr].steps[ctr2].type == 'SHUTTLEBUS') {
				lineColor = '#F00';
			} else {
				lineColor = '#FFA100'; //SYS_SINGLEDIR_POLYLINE_COLOR
			}
			var decodedPath = google.maps.geometry.encoding.decodePath(jsonData.data.routeways[ctr].steps[ctr2].polyline);
			var newPolyline = new google.maps.Polyline({
				path: decodedPath,
				geodesic: false,
				strokeColor: lineColor,
				strokeWeight: 2,
				clickable: false,
				map: map,
				stepData: {
					idAlt: idAlt,
					idStep: newIdStep,
				}
				//icons: SYS_SINGLEDIR_POLYLINE_ICONS
			});
			
			//-- Toleransi jalan adalah 50 m.
			var tmpMarker = null;
			if (jsonData.data.routeways[ctr].steps[ctr2].distance >= 0.05) {
				//-- Start marker...
				tmpMarker = new google.maps.Marker({
					position: jsonData.data.routeways[ctr].steps[ctr2].start_location,
					map: map,
					title: jsonData.data.routeways[ctr].steps[ctr2].html_instruction,
					icon: SYS_NODEMARKER_ICON,
					stepData: {
						idAlt: idAlt,
						idStep: newIdStep,
					}
				});
				
				tmpMarker.addListener('click', function() {
					select_step(this.stepData.idAlt, this.stepData.idStep);
				});

			}
			
			//-- Insert step data
			var tmppopupContent = '<img src="'+jsonData.data.routeways[ctr].steps[ctr2].icon+'" '+
	          'style="width:72px;position:absolute;top:0px;left:0px;"/> '+
	          '<div style="width:200px;margin-left:80px;min-height:54px;"><span>'+
	          jsonData.data.routeways[ctr].steps[ctr2].html_instruction+'</span></div><hr />';
			
			if (ctr2 > 0) {
				tmppopupContent +=  '<a href="#" onclick="return select_step('+ctr+','+(ctr2-1)+');">'+
					'<i class="fa fa-chevron-left"></i> Sebelum</a> ';
			}
			if (ctr2 < stepCount) {
				tmppopupContent += '<div class="pull-right"><a href="#" onclick="return select_step('+ctr+','+(ctr2+1)+');">'+
					'Lanjut <i class="fa fa-chevron-right"></i></a></div>';
			}
			stepsData.push({
				popupContent: tmppopupContent,
		        marker: tmpMarker,
		        polyline: newPolyline
			});
		} // End for
		
		stepHtml += "</div>";
		
		$('#listgroup_steps').append(stepHtml);
		
		searchResultData.push(stepsData);
	} // End foreach alternatives
	
	//---- Debugging purpose -----------
	if (typeof(jsonData.data.verbose) !== 'undefined') {
		$('#site_modal_loader').hide();
		$('#site_modal_content').html(
				'<div style="height: 48px;"><div class="pull-right">'+
				'<button type="button" class="btn btn-danger modal-closebtn">'+
				'<i class="fa fa-remove"></i> Cancel</button></div></div>'+
				jsonData.data.verbose+
				'<div style="height: 48px;"><div class="pull-right">'+
				'<button type="button" class="btn btn-danger modal-closebtn">'+
				'<i class="fa fa-remove"></i> Cancel</button></div></div>');
		$('#site_ov_box_modal').css('width', '100%');
		_on_modal_cancelled = function(){
			hide_modal();
			if (typeof(onCancel) === 'function') {
				onCancel();
			}
		};
		$('#site_overlay_modal .modal-closebtn').click(_on_modal_cancelled);
		$('#site_modal_content').show();
		if (typeof(postinit_modal) === 'function') {
			postinit_modal();
		}
		$("#site_overlay_modal").fadeIn(250);
	}
	//------ End debugging ---------------
	
	/*
	var edgeCount = jsonData.data.sequence.length;
	var prevPosition = null;
	for (ctr = 0; ctr < edgeCount; ctr++) {
		if (prevPosition != null) {
			var polylineData =google.maps.geometry.encoding.decodePath(
					jsonData.data.sequence[ctr].edge_data.polyline);
			activeDirLines.push(new google.maps.Polyline({
				path: polylineData,
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
	*/
	update_gui();
	if (searchResultData.length > 0) {
		// Show first alternative (best one)
		render_searchresult_route(0);		
	}
}
function select_step(idAlt, idStep, panToPolyline) {
	//-- Deselect previous selected step
	if (cursorInfoWindow.selectedStepData.idAlt != null) {
		var oldidAlt = cursorInfoWindow.selectedStepData.idAlt;
		var oldidStep = cursorInfoWindow.selectedStepData.idStep;
		if (searchResultData[oldidAlt][oldidStep].polyline) {
			searchResultData[oldidAlt][oldidStep].polyline.setOptions({
				strokeWeight: 2
			});
		}
	}
	
	// Select something...
	if ((idAlt !== null) && (idStep !== null)) {
		//-- Select step
		var selectedMarker = searchResultData[idAlt][idStep].marker;
		if (selectedMarker) {
			cursorInfoWindow.open(map, selectedMarker);
			cursorInfoWindow.setContent(searchResultData[idAlt][idStep].popupContent);
			cursorInfoWindow.selectedStepData.idAlt = idAlt;
			cursorInfoWindow.selectedStepData.idStep = idStep;
			
			if (!panToPolyline) map.panTo(selectedMarker.getPosition());
		}
		searchResultData[idAlt][idStep].polyline.setOptions({
			strokeWeight: 3
		});
		
		//-- Pan viewport
		if (panToPolyline) panto_polyline(searchResultData[idAlt][idStep].polyline);
	} else {
		cursorInfoWindow.setContent("-");
		cursorInfoWindow.selectedStepData.idAlt = null;
		cursorInfoWindow.selectedStepData.idStep = null;
	}
	
	return false;
}

function reset_gui() {
	change_state(STATE_DEFAULT, null);
	update_gui();
	return false;
}

function panto_polyline(polyline) {
	var pathObj = polyline.getPath();
	var iLen = pathObj.getLength();
	
	//-- Pan viewport
	var bounds = new google.maps.LatLngBounds();
	for (var i = 0; i < iLen; i++) {
	    bounds.extend(pathObj.getAt(i));
	}
	map.fitBounds(bounds);
	return false;
}

$(document).ready(function(){
	$('#txt_node_start').focus();
});