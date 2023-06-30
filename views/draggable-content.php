<?php
    $hompage_layout = get_option('blaze_wooless_homepage_layout', '');
?>
<div id="my-plugin-root"></div>
<div class="blaze-wooless-layout-editor">
    <?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-panel.php'; ?>
    <?php require_once BLAZE_WOOLESS_PLUGIN_DIR . 'views/block-canvas.php'; ?> 
    <input type="hidden" name="homepage_layout" value='<?php echo json_encode($hompage_layout) ?>'/>
</div>
