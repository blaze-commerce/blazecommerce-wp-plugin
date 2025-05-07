/**
 * Image Lazy Load Extension for Gutenberg
 * 
 * This script adds a checkbox to the Image block settings to control lazy loading.
 */
(function() {
    const { __ } = wp.i18n;
    const { addFilter } = wp.hooks;
    const { createHigherOrderComponent } = wp.compose;
    const { Fragment } = wp.element;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { PanelBody, ToggleControl } = wp.components;

    /**
     * Add lazy loading attribute to Image block
     */
    function addLazyLoadAttribute(settings, name) {
        // Only add attribute to image block
        if (name !== 'core/image') {
            return settings;
        }

        // Add new lazyLoad attribute
        if (settings.attributes) {
            settings.attributes = Object.assign(settings.attributes, {
                lazyLoad: {
                    type: 'boolean',
                    default: true, // Default to lazy loading enabled
                },
            });
        }

        return settings;
    }

    /**
     * Add lazy loading control to Image block
     */
    const withLazyLoadControl = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            // Only add control to image block
            if (props.name !== 'core/image') {
                return wp.element.createElement(BlockEdit, props);
            }

            const { attributes, setAttributes } = props;
            const { lazyLoad } = attributes;

            return wp.element.createElement(
                Fragment,
                null,
                wp.element.createElement(BlockEdit, props),
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        {
                            title: __('Loading Settings', 'blaze-commerce'),
                            initialOpen: false
                        },
                        wp.element.createElement(ToggleControl, {
                            label: __('Enable Lazy Loading', 'blaze-commerce'),
                            checked: lazyLoad,
                            onChange: (value) => setAttributes({ lazyLoad: value }),
                            help: lazyLoad
                                ? __('Image will be lazy loaded.', 'blaze-commerce')
                                : __('Image will load immediately.', 'blaze-commerce')
                        })
                    )
                )
            );
        };
    }, 'withLazyLoadControl');

    /**
     * Apply lazy loading attribute to the block's HTML
     */
    function addLazyLoadExtraProps(extraProps, blockType, attributes) {
        // Only add loading attribute to image block
        if (blockType.name !== 'core/image') {
            return extraProps;
        }

        // If lazyLoad is true, add loading="lazy" attribute
        // If lazyLoad is false, add loading="eager" attribute
        if (typeof attributes.lazyLoad !== 'undefined') {
            extraProps.loading = attributes.lazyLoad ? 'lazy' : 'eager';
        }

        return extraProps;
    }

    // Register filters
    addFilter(
        'blocks.registerBlockType',
        'blaze-commerce/image-lazy-load/add-attribute',
        addLazyLoadAttribute
    );

    addFilter(
        'editor.BlockEdit',
        'blaze-commerce/image-lazy-load/add-control',
        withLazyLoadControl
    );

    addFilter(
        'blocks.getSaveContent.extraProps',
        'blaze-commerce/image-lazy-load/add-lazy-load-prop',
        addLazyLoadExtraProps
    );
})();
