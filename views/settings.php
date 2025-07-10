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
			<?php do_action( 'blazecommerce/settings/register_tab_link', $active_tab ) ?>
		</nav>

		<?php
		do_action( 'blazecommerce/settings/render_settings_tab_content', $active_tab );
		do_action( 'blazecommerce/settings/render_settings_tab_content_footer', $active_tab );
		?>

		<?php
		submit_button();
		?>
	</form>
</div>
<?php
