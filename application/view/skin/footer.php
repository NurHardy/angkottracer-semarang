		<div id='site_overlay_process'>
			<div id='site_ov_box_process'>
				<div id='site_ov_msg_process'>Sedang memproses... Mohon tunggu...</div>
				<img src='<?php echo _base_url('/assets/images/loader.gif'); ?>' alt='Loading...' />
			</div>
		</div>
		
		<div id='site_overlay_modal'>
			<div class="container">
				<div class="row">
					<div class="col-md-6 col-md-offset-3">
						<div id='site_ov_box_modal'>
							Hello, there!
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<script>
		var AJAX_REQ_URL = "<?php echo _base_url('/?p=ajax'); ?>";
		</script>
		<script src="<?php echo _base_url('/assets/js/global.js'); ?>"></script>
	</body>
</html>