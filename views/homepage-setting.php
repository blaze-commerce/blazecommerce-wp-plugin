<?php

add_action( 'bwl_setting_menu', 'bwl_setting_menu_homepage', 10 );
add_action( 'admin_enqueue_scripts', 'homepage_enqueue_scripts' );
function bwl_setting_menu_homepage( $menu_slug ) {
	add_submenu_page(
		$menu_slug,
		'Homepage',
		'Homepage',
		'manage_options',
		$menu_slug . '-homepage',
		'typesense_homepage_page'
	);
}


function typesense_homepage_page() {
	?>
	<div class="wrap">
		<h1>
			<?php _e( 'Homepage Settings', 'typesense' ); ?>
		</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'typesense_homepage_settings' );
			do_settings_sections( 'typesense_homepage_settings' );
			submit_button( 'Save Settings' );
			?>
		</form>
	</div>
	<?php
}
function typesense_register_homepage_settings() {

	register_setting( 'typesense_homepage_settings', 'typesense_homepage_settings', 'typesense_homepage_settings_sanitize' );


	// Add the homepage banner settings section
	add_settings_section(
		'typesense_homepage_banner_settings',
		__( 'Homepage Banner Settings', 'typesense' ),
		'typesense_homepage_banner_settings_callback',
		'typesense_homepage_settings'
	);
	// Add the popular categories settings section
	add_settings_section(
		'typesense_homepage_popular_categories_settings',
		__( 'Popular Categories Settings', 'typesense' ),
		'typesense_homepage_popular_categories_settings_callback',
		'typesense_homepage_settings'
	);

	// Add other settings sections here
}

add_action( 'admin_init', 'typesense_register_homepage_settings' );

function typesense_homepage_banner_settings_callback() {
	// Add the settings fields for the homepage banner
	add_settings_field(
		'typesense_homepage_banner_image',
		__( 'Image', 'typesense' ),
		'typesense_homepage_banner_image_callback',
		'typesense_homepage_settings',
		'typesense_homepage_banner_settings'
	);

	// Primary Message field
	add_settings_field(
		'typesense_homepage_primary_message',
		__( 'Primary Message', 'typesense' ),
		'typesense_homepage_primary_message_callback',
		'typesense_homepage_settings',
		'typesense_homepage_banner_settings'
	);

	// Secondary Message field
	add_settings_field(
		'typesense_homepage_secondary_message',
		__( 'Secondary Message', 'typesense' ),
		'typesense_homepage_secondary_message_callback',
		'typesense_homepage_settings',
		'typesense_homepage_banner_settings'
	);

	// Button Text field
	add_settings_field(
		'typesense_homepage_button_text',
		__( 'Button Text', 'typesense' ),
		'typesense_homepage_button_text_callback',
		'typesense_homepage_settings',
		'typesense_homepage_banner_settings'
	);

	// Button Link field
	add_settings_field(
		'typesense_homepage_button_link',
		__( 'Button Link', 'typesense' ),
		'typesense_homepage_button_link_callback',
		'typesense_homepage_settings',
		'typesense_homepage_banner_settings'
	);

}

function typesense_homepage_banner_image_callback() {
	$options   = get_option( 'typesense_homepage_settings' );
	$image_url = isset( $options['typesense_homepage_banner_image'] ) ? $options['typesense_homepage_banner_image'] : '';
	?>
	<input type="text" name="typesense_homepage_settings[typesense_homepage_banner_image]"
		id="typesense_homepage_banner_image" value="<?php echo esc_attr( $image_url ); ?>">
	<input type="button" id="typesense_homepage_banner_image_button" class="button"
		value="<?php _e( 'Upload Image', 'typesense' ); ?>">
	<script>
		jQuery(document).ready(function ($) {
			var categories = <?php echo json_encode( $popular_categories ); ?> || []; // default to empty array if null

			var container = $('#popular-categories-container');

			if (Array.isArray(categories)) { // check if categories is an array
				categories.forEach(function (category) {
					addCategory(category);
				});
			}
			var custom_uploader;
			$('#typesense_homepage_banner_image_button').click(function (e) {
				e.preventDefault();

				//If the uploader object has already been created, reopen the dialog
				if (custom_uploader) {
					custom_uploader.open();
					return;
				}
				//Extend the wp.media object
				custom_uploader = wp.media.frames.file_frame = wp.media({
					title: '<?php _e( 'Upload Image', 'typesense' ); ?>',
					multiple: false
				});

				//When a file is selected, grab the URL and set it as the text field's value
				custom_uploader.on('select', function () {
					var attachment = custom_uploader.state().get('selection').first().toJSON();
					$('#typesense_homepage_banner_image').val(attachment.url);
				});

				//Open the uploader dialog
				custom_uploader.open();
			});
		});
	</script>
	<?php
}

function typesense_homepage_primary_message_callback() {
	$options         = get_option( 'typesense_homepage_settings' );
	$primary_message = isset( $options['typesense_homepage_primary_message'] ) ? $options['typesense_homepage_primary_message'] : '';
	?>
	<input type="text" name="typesense_homepage_settings[typesense_homepage_primary_message]"
		id="typesense_homepage_primary_message" value="<?php echo esc_attr( $primary_message ); ?>">
	<?php
}

function typesense_homepage_secondary_message_callback() {
	$options           = get_option( 'typesense_homepage_settings' );
	$secondary_message = isset( $options['typesense_homepage_secondary_message'] ) ? $options['typesense_homepage_secondary_message'] : '';
	?>
	<input type="text" name="typesense_homepage_settings[typesense_homepage_secondary_message]"
		id="typesense_homepage_secondary_message" value="<?php echo esc_attr( $secondary_message ); ?>">
	<?php
}

function typesense_homepage_button_text_callback() {
	$options     = get_option( 'typesense_homepage_settings' );
	$button_text = isset( $options['typesense_homepage_button_text'] ) ? $options['typesense_homepage_button_text'] : '';
	?>
	<input type="text" name="typesense_homepage_settings[typesense_homepage_button_text]"
		id="typesense_homepage_button_text" value="<?php echo esc_attr( $button_text ); ?>">
	<?php
}

function typesense_homepage_button_link_callback() {
	$options     = get_option( 'typesense_homepage_settings' );
	$button_link = isset( $options['typesense_homepage_button_link'] ) ? $options['typesense_homepage_button_link'] : '';
	?>
	<input type="text" name="typesense_homepage_settings[typesense_homepage_button_link]"
		id="typesense_homepage_button_link" value="<?php echo esc_attr( $button_link ); ?>">
	<?php
}
function typesense_homepage_popular_categories_settings_callback() {
	// Add the settings fields for the popular categories
	add_settings_field(
		'typesense_homepage_popular_categories',
		__( 'Categories', 'typesense' ),
		'typesense_homepage_popular_categories_callback',
		'typesense_homepage_settings',
		'typesense_homepage_popular_categories_settings'
	);
}

function typesense_homepage_popular_categories_callback() {

	// Get the value of the setting we've registered with register_setting()
	$popular_categories          = get_option( 'typesense_homepage_settings' )['popular_categories'];
	$typesense_homepage_settings = get_option( 'typesense_homepage_settings' );
	$popular_categories          = isset( $typesense_homepage_settings['popular_categories'] ) ? $typesense_homepage_settings['popular_categories'] : array();

	?>
	<div id="popular-categories-container">
		<!-- Existing categories will be loaded here by PHP -->
	</div>

	<input type="button" id="add-popular-category" class="button" value="<?php _e( 'Add Category', 'typesense' ); ?>">
	<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.10.2/Sortable.min.js"></script>

	<script>
		jQuery(document).ready(function ($) {
			var categories = <?php echo json_encode( $popular_categories ); ?> || []; // default to an empty array if null
			var container = $('#popular-categories-container');
			// Extract the native DOM element
			var containerElement = document.getElementById('popular-categories-container');

			if (Array.isArray(categories)) { // check if categories is an array
				categories.forEach(function (category) {
					addCategory(category);
				});
			}

			$('#add-popular-category').click(function () {
				addCategory({
					image: '',
					title: '',
					link: ''
				});
			});

			function addCategory(category) {
				var categoryElem = $('<div class="popular-category" id="' + category.id + '" data-category-id="' +
					category.id + '"></div>');
				var imageInput = $(
					'<input id="popular-category-image-id" type="text" class="popular-category-image" name="typesense_homepage_settings[popular_categories][image][]" placeholder="Image URL" value="' +
					category.image + '">');
				var imageButton = $(
					'<input type="button" class="button upload_image_button" value="<?php _e( 'Upload Image', 'typesense' ); ?>">'
				);
				var titleInput = $(
					'<input type="text" class="popular-category-title" name="typesense_homepage_settings[popular_categories][title][]" placeholder="Title" value="' +
					category.title + '">');
				var linkInput = $(
					'<input type="text" class="popular-category-link" name="typesense_homepage_settings[popular_categories][link][]" placeholder="Link" value="' +
					category.link + '">');
				var deleteButton = $(
					'<input type="button" class="button delete-popular-category" value="<?php _e( 'Delete Category', 'typesense' ); ?>">'
				);

				categoryElem.append(imageInput);
				categoryElem.append(imageButton);
				categoryElem.append(titleInput);
				categoryElem.append(linkInput);
				categoryElem.append(deleteButton);

				container.append(categoryElem);
			}

			$(document).on('click', '.delete-popular-category', function () {
				$(this).closest('.popular-category').remove();
			});

			var custom_uploader;

			$(document).on('click', '.upload_image_button', function (e) {
				e.preventDefault();
				var inputField = $(this).prev();

				if (custom_uploader) {
					custom_uploader.open();
					return;
				}

				custom_uploader = wp.media.frames.file_frame = wp.media({
					title: 'Choose Image',
					button: {
						text: 'Choose Image'
					},
					multiple: false
				});

				custom_uploader.on('select', function () {
					var attachment = custom_uploader.state().get('selection').first().toJSON();
					inputField.val(attachment.url);
				});

				custom_uploader.open();
			});

			var containerElement = container[0];
			// Initialize Sortable on your container
			var sortable = Sortable.create(containerElement, {
				animation: 150, // ms, animation speed moving items when sorting, `0` — without animation
				draggable: ".popular-category", // Specifies which items inside the element should be draggable
				// ... other options ...
				onEnd: function (evt) {
					// TODO: Call your function to save the new order here
					var itemEl = evt.item; // dragged HTMLElement
					var newIndex = evt.newIndex; // New index within parent

					// Create an array of category IDs in their new order
					var newOrder = [];
					$('.popular-category').each(function () {
						var id = $(this).data('category-id');
						newOrder.push(id);
					});

					// Send new order to server
					$.ajax({
						url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
						type: 'POST',
						data: {
							action: 'save_category_order', // This should be the same as the action in your add_action() function
							order: newOrder
						},
						success: function (response) {
							console.log(response);
						},
						error: function (errorThrown) {
							console.log(errorThrown);
						}
					});
				}
			});
		});
	</script>
	<?php
}
function save_category_order() {
	$new_order = $_POST['order'];

	// Handle new order here.
	// This will depend on how you're storing the data in your database.

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_save_category_order', 'save_category_order' );


function typesense_render_popular_categories_field() {
	$popular_categories = get_option( 'typesense_homepage_settings' )['popular_categories'];

}


function typesense_homepage_settings_sanitize( $input ) {


	if ( isset( $input['popular_categories'] ) ) {
		$sanitized_categories = array();
		$categories           = $input['popular_categories'];

		for ( $i = 0; $i < count( $categories['title'] ); $i++ ) {
			$sanitized_categories[] = array(
				'image' => sanitize_text_field( $categories['image'][ $i ] ),
				'title' => sanitize_text_field( $categories['title'][ $i ] ),
				'link' => sanitize_text_field( $categories['link'][ $i ] ),
			);
		}

		$input['popular_categories'] = $sanitized_categories;
	}


	return $input;
}

add_action( 'admin_enqueue_scripts', 'typesense_enqueue_scripts' );

function typesense_enqueue_scripts() {
	wp_enqueue_script( 'jquery-ui-sortable' );
	wp_enqueue_script( 'jquery' );
	wp_enqueue_media();
	wp_enqueue_style( 'jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css' );
}


$options = get_option( 'typesense_homepage_settings' );

$popular_categories = array();
if ( isset( $options['typesense_homepage_popular_categories'] ) ) {
	$popular_categories = json_decode( wp_unslash( $options['typesense_homepage_popular_categories'] ), true );
}


if ( ! class_exists( 'Blaze_Wooless_Homepage_Settings_Compatibility' ) ) {
	class Blaze_Wooless_Homepage_Settings_Compatibility {
		private static $instance = null;

		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function __construct() {
			add_filter( 'blaze_wooless_additional_site_info', array( $this, 'add_homepage_settings' ), 10, 1 );
		}

		public function add_homepage_settings( $additional_settings ) {
			$homepage_settings = get_option( 'typesense_homepage_settings', array() );
			if ( ! empty( $homepage_settings ) ) {
				foreach ( $homepage_settings as $setting_name => $setting_value ) {
					if ( $setting_name == 'popular_categories' && is_array( $setting_value ) ) {
						foreach ( $setting_value as $index => $category ) {
							$json_category = json_encode( $category );
							if ( $json_category !== false ) {
								$additional_settings[ $setting_name . '_' . $index ] = $json_category;
							}
						}
					} else if ( ! empty( $setting_value ) ) {
						$additional_settings[ $setting_name ] = $setting_value;
					}
				}
			}

			return $additional_settings;
		}

	}

	Blaze_Wooless_Homepage_Settings_Compatibility::get_instance();
}
