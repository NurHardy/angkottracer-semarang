<?php
/*
 * controller/modal/add_vertex.php
 * -------------------------------------
 * Controller menangani request modal untuk entitas vertex
 * 
 */
	$formVerb = "edge.add";
?>
<script>
function init_modal(onSubmitSuccess, onCancel) {
	$('#modal_form_add_edge').submit(function(){
		var postData = $(this).serialize();
		_ajax_send(postData, function(response){
			if (typeof(onSubmitSuccess) === 'function') {
				onSubmitSuccess(response);
			}
		}, "Memproses", AJAX_REQ_URL + '/edge/add', 'POST');
		
		return false;
	});
}
function postinit_modal() {
	$('#modal_form_add_edge .request-focus').first().focus();
}
</script>
<form action="#submit" id="modal_form_add_edge">
	<div><strong>Node #1</strong>: <span><?php echo htmlspecialchars($data['dataNode1']['node_name']); ?></span></div>
	<div><strong>Node #2</strong>: <span><?php echo htmlspecialchars($data['dataNode2']['node_name']);; ?></span></div>
	<div class="form-group">
		<label for="modal_form_add_edge_direction">Arah busur:</label>
		<select class="form-control request-focus" id="modal_form_add_edge_direction"
			required name="edge_direction">
			<option value="0">Bolak-balik</option>
			<option value="1">Dari node 1 ke node 2</option>
			<option value="-1">Dari node 2 ke node 1</option>
		</select>
	</div>
	<div style="height: 48px;">
		<div class="pull-right">
			<button type="button" class="btn btn-danger modal-closebtn">
				<i class="fa fa-remove"></i> Cancel</button>
			<button type="submit" class="btn btn-primary">
				<i class="fa fa-check-circle"></i> Submit</button>
		</div>
	</div>
	<input type="hidden" name="data" value="<?php echo htmlspecialchars($edgeData); ?>" />
	<input type="hidden" name="verb" value="<?php echo $formVerb; ?>" />
</form>