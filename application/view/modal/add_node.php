<?php
/*
 * controller/modal/node.php
 * -------------------------------------
 * Controller menangani request modal untuk entitas node
 * 
 */
	$formVerb = "node.add";
?>
<script>
function init_modal(onSubmitSuccess, onCancel) {
	$('#modal_form_add_node').submit(function(){
		var postData = $(this).serialize();
		_ajax_send(postData, function(response){
			if (typeof(onSubmitSuccess) === 'function') {
				onSubmitSuccess(response);
			}
		}, "Memproses", URL_DATA_AJAX);
		
		return false;
	});
}
function postinit_modal() {
	$('#modal_form_add_node .request-focus').first().focus();
}
</script>
<form action="#submit" id="modal_form_add_node">
	<div class="form-group">
		<label for="modal_form_add_node_input1">Nama node:</label>
		<input type="text" class="form-control request-focus" id="modal_form_add_node_input1"
			placeholder="Nama node" required name="node_name" />
	</div>
	<div style="height: 48px;">
		<div class="pull-right">
			<button type="button" class="btn btn-danger modal-closebtn">Cancel</button>
			<button type="submit" class="btn btn-primary">Submit</button>
		</div>
	</div>
	<input type="hidden" name="data" value="<?php echo htmlspecialchars($data['nodeData']); ?>" />
	<input type="hidden" name="verb" value="<?php echo $formVerb; ?>" />
</form>