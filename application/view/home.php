<div id="site_mainwrapper">
	<div id="site_leftpanel">
		<h1>Angkot Tracer</h1>
		<small>Ayo naik angkutan umum!</small>
		<hr>
		<form class="horizontal-form" id="site_nodeselector">
			<label>Pilih node:</label>
			<select name="nodeid" class="form-control">
				<option value="0">- Pilih -</option>
			</select>
		</form>
		<button onclick="return download_json();" class="btn btn-default btn-block">Get Data</button>
		
		<hr>
		<h4>List Simpul</h4>
		<div class="table-responsive">
			<table class="table table-striped table-condensed table-hover table-bordered"
					 id="table_vertex">
				<thead>
					<tr>
						<th>Node</th>
						<th>Distance</th>
						<th>Reversible</th>
						<th>Aksi</th>
					</tr>
				</thead>
				<tbody>
					
				</tbody>
			</table>
		</div>
		<form action="#" id="site_nodeform">
			<label for="site_nodedest_txt">Destination Node</label>
			<select name="id_node" class="form-control"></select>
			<label for="site_nodedist_txt">Distance</label>
			<input type="text" name="node_dist" value="" id="site_nodedist_txt" class="form-control"/>
			<input type="checkbox" name="node_reversible" id="site_reversible_chk"/>
			<label for="site_reversible_chk">Reversible</label>
			
		</form>
	</div>
	<div id="site_googlemaps"></div>
</div>
<script>
var map;
var activeMarkers = [];
var labels = 'ABCDEFGHIJKLMNOPQRSTUVWYZ';
var URL_DATA_AJAX = "<?php echo _base_url('/?p=ajax&mod=data'); ?>";

function focus_node(nodeId) { // nodeId di database
	_ajax_send({
		verb: 'node.getbyid',
		id: nodeId
	}, function(jsonData){
		map.panTo(jsonData.nodedata.position);
		$("#table_vertex tbody").empty();

		var vertexCount = jsonData.vertexes.length;
		var ctr; var reversibleLabel;
		for (ctr = 0; ctr < vertexCount; ctr++) {
			reversibleLabel = (jsonData.vertexes[ctr].reversible?"Yes":"No");
			$("#table_vertex tbody").append(
					'<tr><td>'+jsonData.vertexes[ctr].dest+
					'</td><td>'+jsonData.vertexes[ctr].distance+
					'</td><td>'+reversibleLabel+'</td><td>edit hapus</td></tr>');
		}
	}, "Memuat...", URL_DATA_AJAX);
}
function clear_markers() {
	var dLength = activeMarkers.length;
	var ctr;
	for (ctr=0; ctr < dLength; ctr++) {
		activeMarkers.splice(ctr, 1);
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
			activeMarkers.push(new google.maps.Marker({
				position: jsonData.data[ctr].position,
				map: map,
				label: labels[ctr % 26],
				title: jsonData.data[ctr].name
			}));
			$('#site_nodeselector select[name=nodeid]').append(
				'<option value="'+(jsonData.data[ctr].id)+'">'+jsonData.data[ctr].name+'</option>');
		}
	}, "Mengunduh...", URL_DATA_AJAX);
	return false;
}
function init_map() {
	map = new google.maps.Map(document.getElementById('site_googlemaps'), {
		streetViewControl: false,
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

	download_json();

	$('#site_nodeselector select[name=nodeid]').change(function(){
		focus_node($(this).val());
	});
}
</script>
<script async defer
	src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCB_Tzs_EZ1exoXELhuq_sOlkqhrifjezw&signed_in=true&callback=init_map&signed_in=false"></script>
