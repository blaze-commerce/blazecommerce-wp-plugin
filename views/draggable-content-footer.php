<?php
$tab = $_GET['tab'];
?>
<div id="my-plugin-root"></div>
<div class="blaze-wooless-layout-editor">
	<div>
		<?php require_once BLAZE_COMMERCE_PLUGIN_DIR . 'views/block-panel-simple.php'; ?>
	</div>
	<div class="blaze-wooless-draggable-canvas-container">
		<?php do_action( 'before_wooless_draggable_canvas' ) ?>
		<div class="blaze-wooless-draggable-canvas" id="footer_content_1"></div>
		<div class="blaze-wooless-draggable-canvas"></div>
		<div class="blaze-wooless-draggable-canvas"></div>
	</div>

	<?php do_action( $tab . '_before_draggable_layout_editor_end' ) ?>
</div>