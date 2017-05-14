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
function init_modal(onSubmitSuccess, onCancel, preOnSubmit) {
	$('#modal_form_add_node').submit(function(){
		var _onPreSubmit;
		if (typeof(preOnSubmit) === 'function') {
			_onPreSubmit = preOnSubmit;
		} else {
			_onPreSubmit = function(formElmt, proceedFunc) {
				var postData = $(formElmt).serialize();
				proceedFunc(postData);
			};
		}

		_onPreSubmit(this, function(postData){
			_ajax_send(postData, function(response){
				if (typeof(onSubmitSuccess) === 'function') {
					onSubmitSuccess(response);
				}
			}, "Memproses", AJAX_REQ_URL + '/node/add', "POST");
		});
		
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
			<button type="button" class="btn btn-danger modal-closebtn">
				<i class="fa fa-remove"></i> Cancel</button>
			<button type="submit" class="btn btn-primary">
				<i class="fa fa-check-circle"></i> Submit</button>
		</div>
	</div>
	<input type="hidden" name="data" value="<?php echo htmlspecialchars($nodeData); ?>" />
	<input type="hidden" name="connect_to" value="<?php echo htmlspecialchars($idNodeToConnect); ?>" />
	<input type="hidden" name="verb" value="<?php echo $formVerb; ?>" />
</form>