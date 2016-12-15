<?php
	if (!defined('GOOGLEMAP_APIKEY')) return;
	
?>
	<style>
      .delete-menu {
        position: absolute;
        background: white;
        padding: 3px;
        color: #666;
        font-weight: bold;
        border: 1px solid #999;
        font-family: sans-serif;
        font-size: 12px;
        box-shadow: 1px 3px 3px rgba(0, 0, 0, .3);
        margin-top: -10px;
        margin-left: 10px;
        cursor: pointer;
      }
      .delete-menu:hover {
        background: #eee;
      }
    </style>
<div id="site_mainwrapper">
	<div id="site_leftpanel">
		<h1>Angkot Tracer</h1>
		<small>Ayo naik angkutan umum!</small>
		<hr>
		<div id="site_panel_nodelist" style="display:none;">
			<form class="horizontal-form" id="site_nodeselector">
				<label>Pilih node:</label>
				<select name="nodeid" class="form-control">
					<option value="0">- Pilih -</option>
				</select>
			</form>
			<button onclick="return download_json();" class="btn btn-default btn-block">Get Data</button>
		</div>
		<div id="site_panel_nodeinsert" class="site_defaultpanel">
			<button onclick="return new_node();" class="btn btn-default btn-block">Insert New Node</button>
		</div>
		<div id="site_panel_placenode" class="site_actionpanel">
			<p>Klik pada map untuk menaruh di mana node akan ditempatkan.</p>
			<button onclick="return reset_gui();" class="btn btn-danger btn-block">Batal</button>
		</div>
		<div id="site_panel_selectnode" class="site_actionpanel">
			<p>Pilih salah satu node dengan klik..</p>
			<button onclick="return reset_gui();" class="btn btn-danger btn-block">Batal</button>
		</div>
		<div id="site_panel_selectedge" class="site_actionpanel">
			<p>Ubah polyline pada peta, lalu klik save untuk menyimpan.</p>
			<button onclick="return submit_edge();" class="btn btn-primary btn-block">Save</button>
			<button onclick="return reset_gui();" class="btn btn-danger btn-block">Batal</button>
		</div>
		
		<hr>
		<div id="site_panel_nodeselected" class="site_actionpanel">
			<button onclick="return new_node();" class="btn btn-default btn-block">Insert New Node</button>
			
			<h4>List Simpul</h4>
			<div class="table-responsive">
				<table class="table table-striped table-condensed table-hover table-bordered"
						 id="table_edge">
					<thead>
						<tr>
							<th>Node</th>
							<th>Distance</th>
							<th title="Reversible?">R</th>
							<th>Aksi</th>
						</tr>
					</thead>
					<tbody>
						
					</tbody>
				</table>
			</div>
			
			<button class="btn btn-primary btn-block" onclick="new_edge();">Tambah Busur</button>
			<button class="btn btn-default btn-block" onclick="get_direction();">Get Direction</button>
			
			<form action="#" id="site_nodeform" style="display:none;">
				<label for="site_nodedest_txt">Destination Node</label>
				<select name="id_node" class="form-control"></select>
				<label for="site_nodedist_txt">Distance</label>
				<input type="text" name="node_dist" value="" id="site_nodedist_txt" class="form-control"/>
				<input type="checkbox" name="node_reversible" id="site_reversible_chk"/>
				<label for="site_reversible_chk">Reversible</label>
				
			</form>
		</div><!-- End panel -->
	</div>
	<div id="site_googlemaps"></div>
</div>
<script>
var MARKERBASE = "<?php echo _base_url('/assets/images/marker/'); ?>";
var scripts = <?php echo json_encode(array(
	'deletemenu' => _base_url('/assets/js/components/gmap-deletemenu.js')
)); ?>;
</script>
<script src="<?php echo _base_url('/assets/js/home.js'); ?>"></script>
<script async defer
	src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLEMAP_APIKEY; ?>&callback=init_map&signed_in=false"></script>
