jQuery(document).ready(function($) {
    'use strict';

    // Handle image selection
    $(document).on('click', '.blaze-select-image', function(e) {
        e.preventDefault();

        var button = $(this);
        var container = button.closest('.blaze-country-image-row');
        var countryCode = container.data('country');

        // Create a new media frame for each selection to avoid conflicts
        var mediaUploader = wp.media({
            title: 'Select Image for ' + countryCode,
            button: {
                text: 'Use this image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        // When an image is selected, run a callback
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();

            // Update the hidden input with the attachment ID
            container.find('.blaze-image-id').val(attachment.id);

            // Update the preview image
            var preview = container.find('.blaze-image-preview');
            var thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail ?
                attachment.sizes.thumbnail.url : attachment.url;
            preview.html('<img src="' + thumbnailUrl + '" alt="' + attachment.title + '" />');

            // Update button text and add remove button
            button.text('Change Image');
            var actions = container.find('.blaze-image-actions');
            if (!actions.find('.blaze-remove-image').length) {
                actions.append('<button type="button" class="button blaze-remove-image">Remove</button>');
            }
        });

        // Open the media frame
        mediaUploader.open();
    });

    // Handle image removal
    $(document).on('click', '.blaze-remove-image', function(e) {
        e.preventDefault();

        var button = $(this);
        var container = button.closest('.blaze-country-image-row');
        
        // Clear the hidden input
        container.find('.blaze-image-id').val('');
        
        // Clear the preview
        container.find('.blaze-image-preview').html('');
        
        // Update button text and remove the remove button
        container.find('.blaze-select-image').text('Select Image');
        button.remove();
    });

    // Add some visual feedback for the image selection
    $(document).on('mouseenter', '.blaze-image-preview img', function() {
        $(this).css('opacity', '0.8');
    });

    $(document).on('mouseleave', '.blaze-image-preview img', function() {
        $(this).css('opacity', '1');
    });
});
