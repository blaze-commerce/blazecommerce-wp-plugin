<?php
    $hompage_layout = get_option('blaze_wooless_homepage_layout', '');
?>
<div id="my-plugin-root"></div>
<div class="blaze-wooless-layout-editor">
    <?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-panel.php'; ?>
    <?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-canvas.php'; ?> 
    
    <?php do_action( 'before_draggable_layout_editor_end' ) ?>
</div>
