<?php
/*
 * view/home.php
 * --------------
 * Main application interface
 */
	if (!defined('GOOGLE_APIKEY')) return;
	
?>
<style>
.context-menu {
	width: 200px;
	position: absolute;
	background: white;
	color: #666;
	font-weight: bold;
	border: 1px solid #999;
	font-family: sans-serif;
	font-size: 12px;
	box-shadow: 1px 3px 3px rgba(0, 0, 0, .3);
	margin-top: -15px;
	margin-left: 10px;
	cursor: pointer;
}
.context-menu hr {
	margin-top: 5px;
	margin-bottom: 5px;
}
.context-menu .arrow {
	position: absolute;
	left: -5px;
	top: 8px;
	width: 0;
	height: 0;
	border-top: 5px solid transparent;
	border-bottom: 5px solid transparent;
	border-right: 5px solid #fff;
}
.context-menu .arrowborder {
	position: absolute;
	left: -7px;
	top: 6px;
	width: 0;
	height: 0;
	border-top: 7px solid transparent;
	border-bottom: 7px solid transparent;
	border-right: 7px solid #999;
}

.context-menu .menu-item {
	display: block;
	padding: 5px;
	text-decoration: none;
}
.context-menu .menu-item:hover {
	background: #eee;
	text-decoration: none;
}

#ctxmenu-0-delete, #ctxmenu-0-delete:hover {
	color: #a94442;
}
#site_floatpanel {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 5;
  background-color: #fff;
  padding: 7px;
  border: 1px solid #999;
  font-family: 'Roboto','sans-serif';
  line-height: 30px;
  min-height:32px;
}

#site_floatpanel_extension {
  position: absolute;
  top: 10px;
  right: 180px;
  z-index: 5;
  background-color: #fff;
  padding: 7px;
  border: 1px solid #999;
  font-family: 'Roboto','sans-serif';
  line-height: 30px;
  min-height:32px;
}

#site_floatpanel .fpanel_item {display:none;}
</style>
<div id="site_mainwrapper">
	<div id="site_leftpanel">
		<h2>Angkot Tracer</h2>
		<!-- <small>Ayo naik angkutan umum!</small> -->
		<ul class="nav nav-pills nav-stacked" id="homemenu">
			<li id="homemenu_grapheditor"><a href="#" onclick="return reset_gui();">
				<i class="fa fa-share-alt fa-fw"></i> Graph Editor</a></li>
			<li id="homemenu_routeeditor"><a href="#" onclick="return init_routeeditor();">
				<i class="fa fa-bus fa-fw"></i> Public Route Editor</a></li>
			<li id="homemenu_routedebug"><a href="#" onclick="return init_routedebug();">
				<i class="fa fa-bolt fa-fw"></i> Route Debugger</a></li>
		</ul>
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
		<div id="site_panel_movenode" class="site_actionpanel">
			<p>Drag marker node ke titik yang diinginkan, lalu klik Save.</p>
			<button onclick="return node_move_commit();" class="btn btn-primary btn-block">
				<i class="fa fa-floppy-o"></i> Save</button>
			<button onclick="return reset_gui();" class="btn btn-danger btn-block">
				<i class="fa fa-times"></i> Batal</button>
		</div>
		<div id="site_panel_selectedge" class="site_actionpanel">
			<p>Ubah polyline pada peta, lalu klik save untuk menyimpan.</p>
			<form action="#" method="POST" onsubmit="return edge_save();">
				<div class="form-group">
					<label for="edge_name">Nama Busur/Jalan</label>
					<input type="text" class="form-control input-sm" name="edge_nameid"
						id="edge_name" placeholder="Nama Edge" />
				</div>
				<div class="checkbox">
			        <label>
			          <input type="checkbox" id="edge_isreversible" name="edge_isreversible"
			          	onchange="edge_isreversible_onupdate(this);"/> Reversible
			        </label>
				</div>
				<button type="submit" class="btn btn-primary btn-block">
					<i class="fa fa-floppy-o"></i> Save</button>
				<button onclick="return reset_gui();" class="btn btn-danger btn-block" >
					<i class="fa fa-times"></i> Batal</button>
			</form>
				
			
		</div>
		
		<hr>
		<div id="site_panel_routeeditor_home" class="site_actionpanel">
			<button onclick="return new_route();" class="btn btn-default btn-block">
				<i class="fa fa-plus"></i> Create New Route...</button>
			<button onclick="return select_route();" class="btn btn-default btn-block">
				<i class="fa fa-folder-open"></i> Select Route...</button>
		</div>
		<div id="site_panel_routeeditor_draw" class="site_actionpanel">
			<form action="#" method="POST" onsubmit="return route_save(this);">
				<div class="form-group">
					<label for="txt_route_code">Kode Trayek</label>
					<input type="text" class="form-control input-sm" name="txt_route_code"
						id="txt_route_code" placeholder="Kode armada trayek." />
				</div>
				<div class="form-group">
					<label for="txt_route_name">Nama Trayek</label>
					<input type="text" class="form-control input-sm" name="txt_route_name"
						id="txt_route_name" placeholder="Nama Trayek." />
					<p class="help-block">Nama trayek. Misal: Johar-Tlogosari</p>
				</div>
				
				<button type="submit" class="btn btn-primary btn-block">
					<i class="fa fa-floppy-o"></i> Save</button>
				<button onclick="return reset_gui();" class="btn btn-danger btn-block" >
					<i class="fa fa-times"></i> Batal</button>
				<input type="hidden" name="verb" value="route.save" />
			</form>
		</div>
		<div id="site_panel_nodeselected" class="site_actionpanel">
			<button onclick="return new_node();" class="btn btn-default btn-block">
				<i class="fa fa-plus"></i> Insert New Node...</button>
			
			<!-- <h4>List Simpul</h4>
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
			</div> -->
			
			<button class="btn btn-primary btn-block" onclick="new_edge();">
				<i class="fa fa-crosshairs"></i> Connect to Vertex...</button>
			<button class="btn btn-default btn-block" onclick="get_direction();">
				<i class="fa fa-car"></i> Get Direction...</button>
			
			<form action="#" id="site_nodeform" style="display:none;">
				<label for="site_nodedest_txt">Destination Node</label>
				<select name="id_node" class="form-control"></select>
				<label for="site_nodedist_txt">Distance</label>
				<input type="text" name="node_dist" value="" id="site_nodedist_txt" class="form-control"/>
				<input type="checkbox" name="node_reversible" id="site_reversible_chk"/>
				<label for="site_reversible_chk">Reversible</label>
				
			</form>
		</div><!-- End panel -->
		
		<div id="site_panel_routedebug" class="site_defaultpanel">
			<pre id="routedebug_logpanel" style="max-height: 350px;"></pre>
		</div>
	</div>
	<div id="site_googlemaps"></div>
	
	<div id="site_floatpanel">
		<div id="fpanel_home" style="width: 150px;">
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="new_node(); return false;">
				<i class="fa fa-plus"></i> Tambah Node</a>
		</div>
		<div id="fpanel_drawroute" class="fpanel_item" style="width: 300px;">
			<h5>Route</h5>
			<div class="table-responsive" style="overflow-y: scroll; height: 400px; padding: 5px; border:solid 1px #F5F5F5;"
					id="container_tablerouteedge">
				<table class="table table-striped table-condensed table-hover table-bordered"
						 id="table_routeedge">
					<thead>
						<tr>
							<th>Node</th>
							<th>Name</th>
						</tr>
					</thead>
					<tbody>
						
					</tbody>
				</table>
			</div>
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return routeeditor_draw_backward();">
				<i class="fa fa-chevron-left"></i> Back</a>
		</div>
		<div id="fpanel_edgeedit" class="fpanel_item" style="width: 150px;">
			<div style="text-align: center; font-size:1.1em;"><b>Edge options:</b></div>
			<b>Distance</b>: <span id="edgeedit_distance">-</span> km.
			<hr />
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return edge_showprops();">
				<i class="fa fa-pencil"></i> Properties...</a>
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return edge_reset();">
				<i class="fa fa-undo"></i> Reset</a>
			<div style="text-align:center;">
				<div class="btn-group" style="margin-top:5px;">
					<button type="button" class="btn btn-default btn-sm dropdown-toggle"
							data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
						<i class="fa fa-road"></i> Get Direction <span class="caret"></button>
					<ul class="dropdown-menu" id="requestdirection_opts" style="min-width: 120px;">
						<li><a href="#reqdir" onclick="return edge_getdir();">From A to B</a></li>
						<li><a href="#reqdirrev" onclick="return edge_getdir(-1);">From B to A</a></li>
					</ul>
				</div>
			</div>
			<hr />
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return edge_interpolate();">
				<i class="fa fa-cogs"></i> Interpolate</a>
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return edge_reverse_current();">
				<i class="fa fa-exchange"></i> Reverse</a>
			<hr />
			<a href="#" class="btn btn-danger btn-sm btn-block" onclick="return edge_delete();">
				<i class="fa fa-trash"></i> Delete</a>
		</div>
		<div id="fpanel_nodeedit" class="fpanel_item" style="width: 150px;">
			<b>Node options:</b><hr />
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return node_move();"><i class="fa fa-arrows"></i> Move</a>
			<a href="#" class="btn btn-danger btn-sm btn-block" onclick="return node_delete();"><i class="fa fa-trash"></i> Delete</a>
		</div>
		<div id="fpanel_nodemove" class="fpanel_item" style="width: 150px;">
			<b>Node options:</b><hr />
			<a href="#" class="btn btn-default btn-sm btn-block" onclick="return node_move_reset();"><i class="fa fa-undo"></i> Reset</a>
			<a href="#" class="btn btn-danger btn-sm btn-block" onclick="return node_delete();"><i class="fa fa-trash"></i> Delete</a>
		</div>
		<!-- 
		<div id="fpanel_searchresult" class="fpanel_item" style="width: 300px;">
			<div class="list-group">
				<a href="#" class="list-group-item">
					<div class="media">
					  <div class="media-left">
					  	<i class="fa fa-bus fa-2x"></i>
					  </div>
					  <div class="media-body">
					    Test<br />
					    Mlampah
					  </div>
					</div>
				</a>
				<a href="#" class="list-group-item">
					<div class="media">
					  <div class="media-left">
					  	<i class="fa fa-male fa-2x"></i>
					  </div>
					  <div class="media-body">
					    Test<br />
					    Mlampah
					  </div>
					</div>
				</a>
			</div>
		</div> -->
	</div>
	<div id="site_floatpanel_extension">
		<div id="fpanel_edgeopts" class="fpanelext_item" style="width: 200px; display:none;">
			
		</div>
	</div>
</div>
<script>
var MARKERBASE = "<?php echo _base_url('/assets/images/marker/'); ?>";
var scripts = <?php echo json_encode(array(
	'markerclusterer' => _base_url('/assets/js/components/marker-clusterer.js?v='.APPVER),
	'ctxmenu' => _base_url('/assets/js/components/gmap-contextmenu.js?v='.APPVER),
	'vertex-mgmt' => _base_url('/assets/js/main/vertex.js?v='.APPVER),
	'edge-mgmt' => _base_url('/assets/js/main/edge.js?v='.APPVER),
	'route-mgmt' => _base_url('/assets/js/main/route.js?v='.APPVER)
)); ?>;
</script>
<script src="<?php echo _base_url('/assets/js/home.js?v='.APPVER); ?>"></script>
<script async defer
	src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_APIKEY; ?>&libraries=geometry&callback=init_map"></script>
	
<!-- modal dialogs -->

