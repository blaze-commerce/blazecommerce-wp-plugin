(function() {
    'use strict';

    // Debug logging
    console.log('BlazeCommerce: Loading global block config script');

    // Ensure WordPress dependencies are available
    if (typeof wp === 'undefined') {
        console.error('BlazeCommerce: WordPress (wp) object not available');
        return;
    }

    // Check individual dependencies
    const requiredDeps = ['hooks', 'element', 'components', 'blockEditor', 'compose'];
    const missingDeps = requiredDeps.filter(dep => !wp[dep]);

    if (missingDeps.length > 0) {
        console.error('BlazeCommerce: Missing WordPress dependencies:', missingDeps);
        return;
    }

    console.log('BlazeCommerce: All dependencies available');

    const { addFilter } = wp.hooks;
    const { createElement, Fragment } = wp.element;
    const { InspectorAdvancedControls } = wp.blockEditor;
    const { TextControl } = wp.components;
    const { createHigherOrderComponent } = wp.compose;

    /**
     * Add region attribute to all blocks
     */
    function addRegionAttribute(settings, name) {
        console.log('BlazeCommerce: Adding attribute to block:', name);

        // Skip core blocks that shouldn't have region settings
        const skipBlocks = [
            'core/freeform',
            'core/html',
            'core/shortcode'
        ];

        if (skipBlocks.includes(name)) {
            console.log('BlazeCommerce: Skipping attribute for block:', name);
            return settings;
        }

        // Add region attribute to block settings
        if (typeof settings.attributes !== 'object') {
            settings.attributes = {};
        }

        settings.attributes.blazeCommerceRegion = {
            type: 'string',
            default: ''
        };

        console.log('BlazeCommerce: Added region attribute to:', name);
        return settings;
    }

    /**
     * Add region control to Advanced panel
     */
    const withRegionControl = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { attributes, setAttributes, name } = props;

            console.log('BlazeCommerce: Processing block:', name);

            // Skip core blocks that shouldn't have region settings
            const skipBlocks = [
                'core/freeform',
                'core/html',
                'core/shortcode'
            ];

            if (skipBlocks.includes(name)) {
                console.log('BlazeCommerce: Skipping block:', name);
                return createElement(BlockEdit, props);
            }

            // Get current region value
            const regionValue = attributes.blazeCommerceRegion || '';
            console.log('BlazeCommerce: Region value for', name, ':', regionValue);

            return createElement(
                Fragment,
                {},
                createElement(BlockEdit, props),
                createElement(
                    InspectorAdvancedControls,
                    {},
                    createElement(TextControl, {
                        label: 'Region',
                        value: regionValue,
                        onChange: (value) => {
                            console.log('BlazeCommerce: Setting region for', name, 'to:', value);
                            setAttributes({ blazeCommerceRegion: value });
                        },
                        help: 'Specify the region where this block should be displayed',
                        className: 'blaze-commerce-region-control'
                    })
                )
            );
        };
    }, 'withRegionControl');

    /**
     * Save region data to block metadata
     */
    function addRegionSaveProps(extraProps, blockType, attributes) {
        // blockType parameter required by WordPress filter but not used
        const regionValue = attributes.blazeCommerceRegion;
        
        if (regionValue && regionValue.trim() !== '') {
            // Add region as a data attribute for frontend use
            if (!extraProps) {
                extraProps = {};
            }
            
            if (!extraProps['data-blaze-region']) {
                extraProps['data-blaze-region'] = regionValue.trim();
            }
        }

        return extraProps;
    }

    // Apply filters to extend all blocks
    addFilter(
        'blocks.registerBlockType',
        'blaze-commerce/add-region-attribute',
        addRegionAttribute
    );

    addFilter(
        'editor.BlockEdit',
        'blaze-commerce/with-region-control',
        withRegionControl
    );

    addFilter(
        'blocks.getSaveContent.extraProps',
        'blaze-commerce/add-region-save-props',
        addRegionSaveProps
    );

    // Debug logging
    if (window.console && window.console.log) {
        console.log('BlazeCommerce: Global block region configuration loaded');
    }

})();
