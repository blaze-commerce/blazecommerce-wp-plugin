<?php
$synonyms = get_option( 'wooless_synonyms', array() );
?>

<div class="notice notice-info update-nag inline">
	<p>
		The synonyms feature allows you to define search terms that should be considered equivalent. For eg: when you
		define
		a synonym for <strong>sneaker</strong> as <strong>shoe</strong>, searching for <strong>sneaker</strong> will now
		return all records with the word <strong>shoe</strong> in them, in
		addition to records with the word <strong>sneaker</strong>.
	</p>

	<p>
		We supports two types of synonyms:
	</p>

	<ul style="font-size: 13px; line-height:1.5">
		<li>
			- One-way synonyms: Defining the words <strong>iphone</strong> and <strong>android</strong> as one-way
			synonyms of <strong>smart phone</strong> will cause
			searches for <strong>smart phone</strong> to return documents containing <strong>iphone</strong> or
			<strong>android</strong> or both.
		</li>

		<li>
			- Multi-way synonyms: Defining the words <strong>blazer</strong>, <strong>coat</strong> and
			<strong>jacket</strong> as multi-way synonyms will cause searches
			for any one of those words (eg: <strong>coat</strong>) to return documents containing at least one of the
			words in the synonym set
			(eg: records with <strong>blazer</strong> or <strong>coat</strong> or <strong>jacket</strong> are returned).
		</li>
	</ul>

</div>

<h2>Synonim Settings</h2>

<div id="blaze-wooless-synonym-holder">
	<div id="blaze-wooless-synonym-row-holder">
		<?php
		if ( is_array( $synonyms ) && count( $synonyms ) > 0 )
			foreach ( (array) $synonyms as $index => $synonym ) : ?>
				<div class="blaze-wooless-synonym-row" data-row="<?php echo $index; ?>">
					<div class="blaze-wooless-synonym-row__input">
						<div class="blaze-wooless-synonym-row__input__type field-holder">
							<label>Type</label>
							<select class="blaze-wooless-synonym-row__input__type__select"
								name="synonym[<?php echo $index; ?>][type]">
								<option value="one-way" <?php echo $synonym['type'] === 'one-way' ? 'selected' : ''; ?>>
									One-way
								</option>
								<option value="multi-way" <?php echo $synonym['type'] === 'multi-way' ? 'selected' : ''; ?>>
									Multi-way
								</option>
							</select>
						</div>
						<div
							class="blaze-wooless-synonym-row__input__key field-holder <?php echo $synonym['type'] === 'one-way' ? 'required' : 'hide'; ?>">
							<label>Key <sup>*</sup></label>
							<input class="blaze-wooless-synonym-row__input__key__input" type="text"
								name="synonym[<?php echo $index; ?>][key]" value="<?php echo $synonym['key']; ?>"
								placeholder="Enter key" <?php echo $synonym['type'] === 'one-way' ? 'required' : ''; ?>>
						</div>
						<div class="blaze-wooless-synonym-row__input__words field-holder required">
							<label>Words <sup>*</sup></label>
							<textarea class="blaze-wooless-synonym-row__input__words__input"
								placeholder="Enter words separated by commas" name="synonym[<?php echo $index; ?>][words]"
								required><?php echo implode( ', ', $synonym['words'] ); ?></textarea>
						</div>
						<div class="blaze-wooless-synonym-row__actions">
							<button class="blaze-wooless-synonym-row__actions__remove">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
	</div>
	<div id="blaze-wooless-synonym-add-row">
		<button id="blaze-wooless-synonym-add-row__button button" type="button">Add Synonym</button>
	</div>
</div>

<script id="blaze-wooless-synonym-template" type="text/x-jsrender">
	<div class="blaze-wooless-synonym-row" data-row="{^{>row}}">
		<div class="blaze-wooless-synonym-row__input">
			<div class="blaze-wooless-synonym-row__input__type field-holder">
				<label>Type</label>
				<select class="blaze-wooless-synonym-row__input__type__select" name="synonym[{^{>row}}][type]">
					<option value="one-way">One-way</option>
					<option value="multi-way">Multi-way</option>
				</select>
			</div>
			<div class="blaze-wooless-synonym-row__input__key field-holder required">
				<label>Key <sup>*</sup></label>
				<input class="blaze-wooless-synonym-row__input__key__input" type="text" name="synonym[{^{>row}}][key]" value="" placeholder="Enter key" required>
			</div>
			<div class="blaze-wooless-synonym-row__input__words field-holder required">
				<label>Words <sup>*</sup></label>
				<textarea class="blaze-wooless-synonym-row__input__words__input" placeholder="Enter words separated by commas" name="synonym[{^{>row}}][words]" required></textarea>
			</div>
			<div class="blaze-wooless-synonym-row__actions">
				<button class="blaze-wooless-synonym-row__actions__remove">
					<span class="dashicons dashicons-trash"></span>
				</button>
			</div>
		</div>
	</div>
</script>
<script type="text/javascript">
	(function ($) {


		const appendTemplate = () => {
			// count how many rows are there
			let totalRows = $('.blaze-wooless-synonym-row').length;

			console.log({ totalRows })

			if (totalRows > 0) {
				console.log($('#blaze-wooless-synonym-row-holder').find('.blaze-wooless-synonym-row').last())
				// find last .blaze-wooles-synonym-row
				totalRows = parseInt($('#blaze-wooless-synonym-row-holder').find('.blaze-wooless-synonym-row').last().data('row')) + 1;
			}

			console.log({ totalRows })

			// get the template using jsview
			const template = $.templates('#blaze-wooless-synonym-template');

			// set row to the number of rows
			const html = template.render({ row: totalRows });

			// append the html to the row holder
			$('#blaze-wooless-synonym-row-holder').append(html);

		}

		$(document).ready(function () {
			var $synonymHolder = $('#blaze-wooless-synonym-holder');
			var $synonymRowHolder = $('#blaze-wooless-synonym-row-holder');
			var $synonymAddRow = $('#blaze-wooless-synonym-add-row');
			var $synonymTemplate = $('#blaze-wooless-synonym-template').html();

			$synonymAddRow.on('click', function () {
				appendTemplate()
			});

			$synonymRowHolder.on('click', '.blaze-wooless-synonym-row__actions__remove', function () {
				$(this).closest('.blaze-wooless-synonym-row').remove();
			});

			$synonymRowHolder.on('change', '.blaze-wooless-synonym-row__input__type__select', function () {
				var $keyInput = $(this).closest('.blaze-wooless-synonym-row__input').find('.blaze-wooless-synonym-row__input__key__input');
				var $keyHolder = $(this).closest('.blaze-wooless-synonym-row__input').find('.blaze-wooless-synonym-row__input__key');
				if ($(this).val() === 'one-way') {
					$keyHolder.addClass('required').removeClass('hide')
					$keyInput.prop('required', true);
				} else {
					$keyHolder.removeClass('required').addClass('hide')
					$keyInput.prop('required', false);
				}
			});
		});
	})(jQuery)
</script>