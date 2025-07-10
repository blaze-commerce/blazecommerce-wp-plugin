<?php
$tab = $_GET['tab'];
?>
<div id="my-plugin-root"></div>
<div class="blaze-wooless-layout-editor">
	<?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-panel-simple.php'; ?>
	<?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-canvas.php'; ?>

	<?php do_action( $tab . '_before_draggable_layout_editor_end' ) ?>
</div>