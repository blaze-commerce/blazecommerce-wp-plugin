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

        // Initialize Select2 for related page dropdown with AJAX
        $('#blaze_related_page.blaze-select2-ajax').select2({
            placeholder: 'Search for a page...',
            allowClear: true,
            width: '100%',
            theme: 'default',
            dropdownParent: $('#blaze-page-meta-fields'),
            dropdownAutoWidth: true,
            minimumInputLength: 2, // Require at least 2 characters to search
            ajax: {
                url: blazePageMeta.ajax_url,
                type: 'POST', // Explicitly set to POST
                dataType: 'json',
                delay: 250, // Delay to reduce server requests
                data: function (params) {
                    return {
                        action: 'blaze_search_pages',
                        search: params.term || '',
                        page: params.page || 1,
                        nonce: blazePageMeta.nonce,
                        current_page_id: blazePageMeta.current_page_id
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;

                    // Handle WordPress AJAX response format
                    if (data.success && data.data) {
                        return {
                            results: data.data.results || [],
                            pagination: {
                                more: (data.data.pagination && data.data.pagination.more) || false
                            }
                        };
                    } else {
                        console.error('AJAX Error:', data.data || 'Unknown error');
                        return {
                            results: [],
                            pagination: {
                                more: false
                            }
                        };
                    }
                },
                cache: true,
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Response:', xhr.responseText);
                }
            },
            // Show loading message
            language: {
                searching: function () {
                    return 'Searching pages...';
                },
                inputTooShort: function () {
                    return 'Please enter 2 or more characters';
                },
                noResults: function () {
                    return 'No pages found';
                }
            }
        });

        console.log('Blaze Select2 initialized for page meta fields');
    }

    // Initialize on page load
    initBlazeSelect2();

    // Re-initialize if the meta box is dynamically loaded (for Gutenberg compatibility)
    $(document).on('DOMNodeInserted', function(e) {
        if ($(e.target).find('.blaze-select2, .blaze-select2-ajax').length > 0 ||
            $(e.target).hasClass('blaze-select2') ||
            $(e.target).hasClass('blaze-select2-ajax')) {
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
