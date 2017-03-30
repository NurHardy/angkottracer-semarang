<?php
/*
 * controller/modal/node.php
 * -------------------------------------
 * Controller menangani request modal untuk entitas node
 * 
 */
	$formVerb = "node.add";
	if (!isset($selectedNodeType)) $selectedNodeType = 0;
?>
<script>
function init_modal(onSubmitSuccess, onCancel) {
	$('#modal_form_add_node').submit(function(){
		var postData = $(this).serialize();
		_ajax_send(postData, function(response){
			if (typeof(onSubmitSuccess) === 'function') {
				onSubmitSuccess(response);
			}
		}, "Memproses", AJAX_REQ_URL + '/node/add', "POST");
		
		return false;
	});
}
function postinit_modal() {
	$('#modal_form_add_node .request-focus').first().focus();
}
</script>
<form action="#submit" id="modal_form_add_node">
	<?php echo @$formContent; ?>
	<div style="height: 48px;">
		<div class="pull-right">
			<button type="button" class="btn btn-danger modal-closebtn">Cancel</button>
			<button type="submit" class="btn btn-primary">Submit</button>
		</div>
	</div>
	<input type="hidden" name="data" value="<?php echo htmlspecialchars($nodeData); ?>" />
	<input type="hidden" name="verb" value="<?php echo $formVerb; ?>" />
</form>