<div class="field">

	<style type="text/css" >
	/* Import Styles for the CC License Chooser */
		<?php include('cc.css') ?>
	</style>
	<div class="inputs">		
		<!-- <form id="cc_js_seed_container"> -->
			<input type="hidden" id="cc_js_seed_uri" value="<?php echo get_option('default_license_uri') ?>" />
		<!-- </form> -->
		
     	<div id="cc_widget_container" style='display:block;clear:both;'>
			<script type="text/javascript" src="http://api.creativecommons.org/jswidget/tags/0.97/complete.js?locale=en_US"></script>
		</div>
</div>
