<?php
/*
 * templates/modal/user_editormenu.php
 * -------------------------------------
 * Model menu user di halaman editor...
 * 
 */
	
	if (!isset($currentSessionData)) $currentSessionData = null;
?>
<script>
function init_modal(onSubmitSuccess, onCancel) {
	$('#site_ov_box_modal').css('width', '300px');
}
function postinit_modal() {
	
}
</script>
<div>
	<div>
		<div style="padding:10px;">
			<strong><?php echo htmlspecialchars($currentSessionData['userNickName']); ?></strong><br />
			<?php echo htmlspecialchars($currentSessionData['userEmail']); ?></div>
		<div style="background-color: #eee;padding:10px;">
			<b>Account Menu</b></div>
		<ul class="nav nav-pills nav-stacked" id="usermenu">
			<li id="usermenu_grapheditor"><a href="#" onclick="return refresh_cache();">
				<i class="fa fa-refresh fa-fw"></i> Refresh Cache</a></li>
			<li id="usermenu_grapheditor"><a href="<?php echo $baseUrl.('/auth/logout?token='.$currentSessionData['activeToken']); ?>"
					>
				<i class="fa fa-sign-out fa-fw"></i> Logout</a></li>
		</ul>
	</div>
	<hr />
	<div style="height: 48px;">
		<div style="text-align:center;">
			<button type="button" class="btn btn-danger modal-closebtn"><i class="fa fa-remove"></i> Cancel</button>
		</div>
	</div>
</div>