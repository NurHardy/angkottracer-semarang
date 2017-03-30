<?php
/*
 * templates/forms/form_node.php
 * ------------------------------
 * Elemen form node (untuk tambah, edit)
 */

	if (!isset($formId)) $formId = "nodefrm";
?>
	<div class="form-group">
		<label for="<?php echo $formId; ?>_input1">Nama node:</label>
		<input type="text" class="form-control request-focus" id="<?php echo $formId; ?>_input1"
			placeholder="Nama node" required name="node_name" />
	</div>
	<div class="form-group">
		<label for="<?php echo $formId; ?>_input2">Jenis node:</label>
		<select name="node_type" id="<?php echo $formId; ?>_input2" class="form-control" required>
<?php
	foreach ($nodeTypeList as $nodeTypeId => $nodeTypeItem) {
		echo '<option value="'.$nodeTypeId.'" '.($selectedNodeType == $nodeTypeId ? 'selected' : '').'>'.$nodeTypeItem.'</option>';
	}
?>
		</select>
	</div>
	