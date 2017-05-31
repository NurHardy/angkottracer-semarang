/**
 * js/frontpage.js
 * ----------------
 * Javascipt halaman depan utama (publik)...
 * 
 */

var map;
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
}

function init_map() {
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

    // Bind the map's bounds (viewport) property to the autocomplete object,
    // so that the autocomplete requests use the current map bounds for the
    // bounds option in the request.
    autocomplete1.bindTo('bounds', map);

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
		refresh_search_form();
    });

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
		refresh_search_form();
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
	alert("On submit!");
	return false;
}