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
<!-- <div class="indexer_page">
	<h1>Typesense Product Indexer</h1>
	<div id="wrapper-id" class="message-wrapper">
		<div class="message-image">
			<img src="<?php echo plugins_url( 'blaze-wooless/assets/images/Shape.png' ); ?>" alt="" srcset="">
		</div>
		<div class="wooless_message">
			<div class="message_success">Success</div>
			<div id="message"></div>
		</div>
	</div>
	<div class="wrapper">
		<label class="api_label" for="api_key">API Private Key: </label>
		<div class="input-wrapper">
			<input class="input_p" type="password" id="api_key" name="api_key"
				value="<?php echo esc_attr( $private_key_master ); ?>" />
			<div class="error-icon" id="error_id" style="display: none;">
				<img src="<?php echo plugins_url( 'blaze-wooless/assets/images/error.png' ); ?>" alt="" srcset="">
				<div id="error_message"></div>
			</div>
		</div>
		<input type="checkbox" id="show_api_key" onclick="toggleApiKeyVisibility()">
		<label class="checkbox_Label">Show API Key</label>
	</div>
	<div class="item_wrapper_indexer_page">
		<button id="index_products" onclick="indexData()" store-id="<?php // echo $wooless_site_id; ?>" disabled>Manual

			<button id="index_products" onclick="indexData()" disabled>Manual Sync

			</button>
			<button id="check_api_key" onclick="checkApiKey()">Save</button>
			<div id="jsdecoded" style="margin-top: 10px;"></div>
			<div id="phpdecoded" style="margin-top: 10px;"></div>
	</div>
</div> -->



<script>
	function toggleApiKeyVisibility() {
		var apiKeyInput = document.getElementById("api_key");
		var showApiKeyCheckbox = document.getElementById("show_api_key");

		if (showApiKeyCheckbox.checked) {
			apiKeyInput.type = "text";
		} else {
			apiKeyInput.type = "password";
		}
	}

	function decodeAndSaveApiKey(apiKey) {
		var decodedApiKey = atob(apiKey);
		var trimmedApiKey = decodedApiKey.split(':');
		var typesensePrivateKey = trimmedApiKey[0];
		var woolessSiteId = trimmedApiKey[1];

		// Display API key and store ID for testing purposes
		//document.getElementById("jsdecoded").innerHTML = 'Typesense Private Key: ' + typesensePrivateKey +
		//  '<br> Store ID: ' +
		//woolessSiteId;

		// Save the API key, store ID, and private key
		jQuery.post(ajaxurl, {
			'action': 'save_typesense_api_key',
			'api_key': apiKey, // Add the private key in the request
			'typesense_api_key': typesensePrivateKey,
			'store_id': woolessSiteId,
		}, function (save_response) {
			setTimeout(function () {
				document.getElementById("message").textContent += ' - ' + save_response;
			}, 1000);
		});

	}

	function checkApiKey() {
		var apiKey = document.getElementById("api_key").value;
		var data = {
			'action': 'get_typesense_collections',
			'api_key': apiKey,
		};
		document.getElementById("wrapper-id").style.display = "none";
		document.getElementById("index_products").disabled = true;
		document.getElementById("check_api_key").disabled = true;
		document.getElementById("check_api_key").style.cursor = "no-drop";
		document.getElementById("index_products").style.cursor = "no-drop";
		jQuery.post(ajaxurl, data, function (response) {
			console.log(response);
			var parsedResponse = JSON.parse(response);
			if (parsedResponse.status === "success") {
				//alert(parsedResponse.message);

				// Log the collection data
				console.log("Collection data:", parsedResponse.collection);
				// Decode and save the API key
				decodeAndSaveApiKey(apiKey);
				indexData();
				document.getElementById("index_products").disabled = false;
				document.getElementById("wrapper-id").style.display = "none";
				document.getElementById("error_id").style.display = "none";
				document.getElementById("index_products").style.cursor = "pointer";
			} else {
				//alert("Invalid API key. There was an error connecting to Typesense.");
				var errorMessage = "Invalid API key.";
				document.getElementById("error_message").textContent = errorMessage;
				document.getElementById("index_products").disabled = true;
				document.getElementById("error_id").style.display = "flex";
				document.getElementById("index_products").disabled = false;
				document.getElementById("check_api_key").disabled = false;
				document.getElementById("check_api_key").style.cursor = "pointer";
				document.getElementById("index_products").style.cursor = "pointer";

			}
		});
	}



	function indexData() {
		var apiKey = document.getElementById("api_key").value;
		var data = {
			'action': 'index_data_to_typesense',
			'api_key': apiKey,
			'collection_name': 'products',

		};
		document.getElementById("wrapper-id").style.display = "none";
		document.getElementById("message").textContent = "Indexing Data...";
		document.getElementById("check_api_key").textContent = "Indexing Data...";
		document.getElementById("index_products").disabled = true;
		document.getElementById("check_api_key").disabled = true;
		document.getElementById("check_api_key").style.cursor = "no-drop";
		document.getElementById("index_products").style.display = "none";
		jQuery.post(ajaxurl, data, function (response) {
			document.getElementById("message").textContent = response;
			data.collection_name = 'taxonomy';
			jQuery.post(ajaxurl, data, function (response) {
				data.collection_name = 'menu';
				jQuery.post(ajaxurl, data, function (response) {
					data.collection_name = 'page';
					jQuery.post(ajaxurl, data, function (response) {
						data.collection_name = 'site_info';
						jQuery.post(ajaxurl, data, function (response) {
							document.getElementById("message").textContent =
								response;
							document.getElementById("check_api_key").disabled =
								false;
							document.getElementById("check_api_key").textContent =
								"Save";
							document.getElementById("index_products").style
								.display =
								"flex";
							document.getElementById("check_api_key").style.cursor =
								"pointer";
							document.getElementById("wrapper-id").style.display =
								"flex";
						});
					});
				});
			});
		});
	}
	// Enable or disable the 'Index Products' button based on the saved API key
	// if (document.getElementById("api_key").value !== "") {
	//     document.getElementById("index_products").disabled = false;
	// }
</script>
<?php
