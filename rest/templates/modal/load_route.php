<?php
/*
 * controller/modal/load_route.php
 * -------------------------------------
 * Controller menangani request modal untuk entitas vertex
 * 
 */
	$formVerb = "route.load";
	
	if (!isset($routeList)) $routeList = [];
?>
<script>
function init_modal(onSubmitSuccess, onCancel) {
	
}
function postinit_modal() {
	$('#modal_form_add_edge .request-focus').first().focus();
}
</script>
<table class="table table-striped table-condensed table-hover table-bordered" id="table_routeedge">
	<thead>
		<tr>
			<th style="width: 100px;">Kode</th>
			<th>Nama Trayek</th>
			<th style="width: 100px;">Aksi</th>
		</tr>
	</thead>
	<tbody>
<?php
	foreach ($routeList as $itemRoute) { //--------
?>
		<tr>
			<td><?php echo $itemRoute['route_code']; ?></td>
			<td><?php echo htmlspecialchars($itemRoute['route_name']); ?>
				<div><small><?php echo null; ?></small></div>
			</td>
			<td><a href="#select" class="btn btn-warning btn-xs" onclick="return load_route(<?php echo $itemRoute['id_route']; ?>);">
				Select <i class="fa fa-chevron-right"></i></a></td>
		</tr>
<?php
	} //----- End Foreach
?>
	</tbody>
</table>
<div style="height: 48px;">
	<div class="pull-right">
		<button type="button" class="btn btn-danger modal-closebtn"><i class="fa fa-remove"></i> Cancel</button>
	</div>
</div>