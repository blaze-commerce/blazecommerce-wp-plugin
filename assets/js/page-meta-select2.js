jQuery(document).ready(function($) {
    'use strict';

    // Initialize Select2 for Blaze page meta fields
    function initBlazeSelect2() {
        // Check if Select2 is available
        if (typeof $.fn.select2 === 'undefined') {
            console.warn('Select2 is not available');
            return;
        }

        // Initialize Select2 for page region dropdown
        $('#blaze_page_region.blaze-select2').select2({
            placeholder: 'Select a region...',
            allowClear: true,
            width: '100%',
            theme: 'default',
            dropdownParent: $('#blaze-page-meta-fields'),
            dropdownAutoWidth: true,
            minimumResultsForSearch: 5 // Show search only if more than 5 options
        });

        // Initialize Select2 for related page dropdown
        $('#blaze_related_page.blaze-select2').select2({
            placeholder: 'Select a related page...',
            allowClear: true,
            width: '100%',
            theme: 'default',
            dropdownParent: $('#blaze-page-meta-fields'),
            dropdownAutoWidth: true,
            // Enable search for pages since there could be many
            minimumInputLength: 0,
            minimumResultsForSearch: 0, // Always show search for pages
            // Custom matcher for better search
            matcher: function(params, data) {
                // If there are no search terms, return all data
                if ($.trim(params.term) === '') {
                    return data;
                }

                // Skip if there is no 'text' property
                if (typeof data.text === 'undefined') {
                    return null;
                }

                // Check if the text contains the term (case insensitive)
                if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                    return data;
                }

                // Return null if the term should not be displayed
                return null;
            }
        });

        console.log('Blaze Select2 initialized for page meta fields');
    }

    // Initialize on page load
    initBlazeSelect2();

    // Re-initialize if the meta box is dynamically loaded (for Gutenberg compatibility)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find('.blaze-select2').length > 0 || $(e.target).hasClass('blaze-select2')) {
            setTimeout(function() {
                initBlazeSelect2();
            }, 100);
        }
    });

    // Handle Gutenberg editor compatibility
    if (typeof wp !== 'undefined' && wp.data) {
        // Wait for Gutenberg to be ready
        wp.domReady(function() {
            // Re-initialize Select2 when meta boxes are loaded
            setTimeout(function() {
                initBlazeSelect2();
            }, 1000);
        });
    }
});
