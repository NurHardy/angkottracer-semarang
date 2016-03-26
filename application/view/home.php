<div id="site_mainwrapper">
	<div id="site_leftpanel">
		<h1>Angkot Tracer</h1>
		<small>Ayo naik angkutan umum!</small>
		<hr>
		<form class="horizontal-form">
			<label>Tampilkan rute:</label>
			<select name="route" class="form-control">
				<option value="0">- Pilih -</option>
			</select>
		</form>
		<button onclick="return download_json();" class="btn btn-default btn-block">Get Data</button>
	</div>
	<div id="site_googlemaps"></div>
</div>
<script>
var map;
var activeMarkers = [];

function clear_markers() {
	var dLength = activeMarkers.length;
	var ctr;
	for (ctr=0; ctr < dLength; ctr++) {
		activeMarkers.splice(ctr, 1);
	}
}
function download_json() {
	_ajax_send({
		verb: 'search'
	}, function(jsonData){
		clear_markers();
		var dLength = jsonData.data.length;
		var ctr;
		for (ctr=0; ctr < dLength; ctr++) {
			activeMarkers.push(new google.maps.Marker({
				position: {lat: jsonData.data[ctr].lat, lng: jsonData.data[ctr].lng},
				map: map,
				title: jsonData.data[ctr].name
			}));
		}
	}, "Mengunduh...", "<?php echo _base_url('/?p=ajax'); ?>");
	return false;
}
function init_map() {
	map = new google.maps.Map(document.getElementById('site_googlemaps'), {
		zoom: 14,
		center: {lat: -6.985525006479515, lng: 110.46021435493}
	});

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

	flightPath.setMap(map);
}
</script>
<script async defer
	src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCB_Tzs_EZ1exoXELhuq_sOlkqhrifjezw&signed_in=true&callback=init_map"></script>
