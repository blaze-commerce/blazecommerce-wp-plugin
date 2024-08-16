<?php

$private_key_master = get_option( 'private_key_master', '' );
$api_key            = bw_get_general_settings( 'api_key' );
?>
<div class="wrap">
	<?php settings_errors(); ?>
	<form method="post" action="options.php" enctype="multipart/form-data">
		<?php
		$active_tab = "general";
		if ( isset( $_GET["tab"] ) ) {
			$active_tab = $_GET["tab"];
		}
		?>
		<nav class="nav-tab-wrapper">
			<?php do_action( 'blaze_wooless_settings_navtab', $active_tab ) ?>
		</nav>

		<?php
		do_action( 'blaze_wooless_render_settings_tab', $active_tab );
		do_action( 'blaze_wooless_render_settings_tab_footer', $active_tab );
		?>

		<?php
		submit_button();
		?>
	</form>
</div>
<?php
