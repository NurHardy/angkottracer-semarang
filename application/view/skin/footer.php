		<div id='site_overlay_modal'>
			<div class="container">
				<div class="row">
					<div class="col-md-6 col-md-offset-3">
						<div id='site_ov_box_modal'>
							<div id="site_modal_loader">
								<img src='<?php echo _base_url('/assets/images/loader.gif'); ?>' alt='Loading...' /> Memuat...
							</div>
							<div id="site_modal_content">							
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div id='site_overlay_process'>
			<div id='site_ov_box_process'>
				<div id='site_ov_msg_process'>Sedang memproses... Mohon tunggu...</div>
				<img src='<?php echo _base_url('/assets/images/loader.gif'); ?>' alt='Loading...' />
			</div>
		</div>
		
		<script>
		var AJAX_REQ_URL = "<?php echo _base_url('/?p=ajax'); ?>";
		var URL_DATA_AJAX = "<?php echo _base_url('/?p=ajax&mod=data'); ?>";
		var URL_MODAL = "<?php echo _base_url('/?p=ajax&mod=modal'); ?>";
		</script>
		<script src="<?php echo _base_url('/assets/js/global.js'); ?>"></script>
	</body>
</html>