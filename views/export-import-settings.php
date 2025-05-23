<?php
// Security check
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="blaze-export-import-container">
	<div class="notice notice-info">
		<p>
			<strong>Export/Import Settings</strong><br>
			Use this feature to backup and restore all your Blaze Commerce plugin settings.
			The exported file will contain all configuration data in JSON format.
		</p>
	</div>

	<div class="blaze-export-import-sections">
		<!-- Export Section -->
		<div class="blaze-export-section">
			<h3><?php _e( 'Export Settings', 'blaze-commerce' ); ?></h3>
			<p><?php _e( 'Download all your current plugin settings as a JSON file.', 'blaze-commerce' ); ?></p>

			<div class="export-actions">
				<button type="button" id="blaze-export-btn" class="button button-primary">
					<?php _e( 'Export Settings', 'blaze-commerce' ); ?>
				</button>
				<span class="spinner" id="export-spinner"></span>

				<!-- Fallback form for export -->
				<form id="export-fallback-form" method="post" style="display: none;">
					<?php wp_nonce_field( 'blaze_export_settings_form', 'export_form_nonce' ); ?>
					<input type="hidden" name="action" value="blaze_export_settings_form">
				</form>
			</div>
		</div>

		<hr>

		<!-- Import Section -->
		<div class="blaze-import-section">
			<h3><?php _e( 'Import Settings', 'blaze-commerce' ); ?></h3>
			<p><?php _e( 'Upload a JSON file to restore plugin settings. This will overwrite your current settings.', 'blaze-commerce' ); ?>
			</p>

			<div class="notice notice-warning">
				<p>
					<strong><?php _e( 'Warning:', 'blaze-commerce' ); ?></strong>
					<?php _e( 'Importing settings will overwrite your current configuration. Make sure to export your current settings first as a backup.', 'blaze-commerce' ); ?>
				</p>
			</div>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="import_file"><?php _e( 'Select JSON File', 'blaze-commerce' ); ?></label>
					</th>
					<td>
						<input type="file" id="import_file" name="import_file" accept=".json" class="regular-text" />
						<p class="description">
							<?php _e( 'Choose a JSON file exported from Blaze Commerce settings.', 'blaze-commerce' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="import-actions">
				<button type="submit" id="blaze-import-btn" class="button button-secondary" disabled>
					<?php _e( 'Import Settings', 'blaze-commerce' ); ?>
				</button>
				<span class="spinner" id="import-spinner"></span>
			</div>

			<?php wp_nonce_field( 'blaze_import_settings_nonce', 'import_nonce' ); ?>
		</div>
	</div>
</div>

<style>
	.blaze-export-import-container {
		max-width: 800px;
		margin: 20px 0;
	}

	.blaze-export-import-sections {
		background: #fff;
		border: 1px solid #ccd0d4;
		border-radius: 4px;
		padding: 20px;
		margin-top: 20px;
	}

	.blaze-export-section,
	.blaze-import-section {
		margin-bottom: 20px;
	}

	.blaze-export-section h3,
	.blaze-import-section h3 {
		margin-top: 0;
		color: #23282d;
	}

	.export-actions,
	.import-actions {
		margin-top: 15px;
	}

	.export-actions .spinner,
	.import-actions .spinner {
		float: none;
		margin-left: 10px;
		visibility: hidden;
	}

	.export-actions .spinner.is-active,
	.import-actions .spinner.is-active {
		visibility: visible;
	}

	#import_file {
		margin-bottom: 10px;
	}

	.notice {
		margin: 15px 0;
	}

	hr {
		margin: 30px 0;
		border: none;
		border-top: 1px solid #ddd;
	}
</style>

<script type="text/javascript">
	jQuery(document).ready(function ($) {
		// Export functionality
		$('#blaze-export-btn').on('click', function () {
			var $btn = $(this);
			var $spinner = $('#export-spinner');

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			// Create form data
			var formData = new FormData();
			formData.append('action', 'blaze_export_settings');
			formData.append('nonce', '<?php echo wp_create_nonce( 'blaze_export_import_nonce' ); ?>');

			// Use XMLHttpRequest for better blob handling
			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxurl, true);
			xhr.responseType = 'blob';

			xhr.onload = function () {
				if (xhr.status === 200) {
					// Check if response is actually a blob
					if (xhr.response instanceof Blob) {
						// Create download link
						var blob = xhr.response;
						var url = window.URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'blaze-commerce-settings-' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
						document.body.appendChild(a);
						a.click();
						window.URL.revokeObjectURL(url);
						document.body.removeChild(a);
					} else {
						console.error('Response is not a blob:', xhr.response);
						console.log('Trying fallback method...');
						// Try fallback form submission
						$('#export-fallback-form').submit();
					}
				} else {
					console.error('Export failed with status:', xhr.status);
					alert('Export failed. Please try again. (Status: ' + xhr.status + ')');
				}

				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			};

			xhr.onerror = function () {
				console.error('AJAX export failed, trying fallback method...');
				// Try fallback form submission
				$('#export-fallback-form').submit();
				$btn.prop('disabled', false);
				$spinner.removeClass('is-active');
			};

			xhr.send(formData);
		});

		// Import file selection
		$('#import_file').on('change', function () {
			var file = this.files[0];
			var $importBtn = $('#blaze-import-btn');

			if (file) {
				// Check file extension since file.type might not always be reliable
				var fileName = file.name.toLowerCase();
				var isJsonFile = fileName.endsWith('.json') || file.type === 'application/json';

				if (isJsonFile) {
					// Check file size (limit to 10MB)
					if (file.size > 10 * 1024 * 1024) {
						alert('File is too large. Please select a file smaller than 10MB.');
						$importBtn.prop('disabled', true);
						$(this).val('');
						return;
					}
					$importBtn.prop('disabled', false);
				} else {
					$importBtn.prop('disabled', true);
					alert('Please select a valid JSON file.');
					$(this).val('');
				}
			} else {
				$importBtn.prop('disabled', true);
			}
		});
	});
</script>