(function() {
    const { __ } = wp.i18n;
    const { addFilter } = wp.hooks;
    const { Fragment, createElement } = wp.element;
    const { InspectorControls } = wp.blockEditor;
    const { PanelBody, ToggleControl } = wp.components;
    const { createHigherOrderComponent } = wp.compose;

    // Add showShortDescription attribute to WooCommerce Product Details block
    function addShowShortDescriptionAttribute(settings, name) {
        if (name !== 'woocommerce/product-details') {
            return settings;
        }

        return {
            ...settings,
            attributes: {
                ...settings.attributes,
                showShortDescription: {
                    type: 'boolean',
                    default: true,
                },
            },
        };
    }

    // Add the checkbox control to the block's inspector controls
    const withShowShortDescriptionControl = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { attributes, setAttributes, name } = props;

            // Only add control to WooCommerce Product Details block
            if (name !== 'woocommerce/product-details') {
                return createElement(BlockEdit, props);
            }

            const { showShortDescription = true } = attributes;

            return createElement(
                Fragment,
                {},
                createElement(BlockEdit, props),
                createElement(
                    InspectorControls,
                    {},
                    createElement(
                        PanelBody,
                        {
                            title: __('Display Options', 'blaze-commerce'),
                            initialOpen: false,
                        },
                        createElement(ToggleControl, {
                            label: __('Show Short Description', 'blaze-commerce'),
                            checked: showShortDescription,
                            onChange: (value) => {
                                setAttributes({ showShortDescription: value });
                            },
                            help: __('Toggle to show or hide the product short description.', 'blaze-commerce'),
                        })
                    )
                )
            );
        };
    }, 'withShowShortDescriptionControl');

    // Apply the filters
    addFilter(
        'blocks.registerBlockType',
        'blaze-commerce/woocommerce-product-details-extension/add-attribute',
        addShowShortDescriptionAttribute
    );

    addFilter(
        'editor.BlockEdit',
        'blaze-commerce/woocommerce-product-details-extension/add-control',
        withShowShortDescriptionControl
    );

    // Add custom CSS to hide short description when the option is disabled
    const addCustomCSS = createHigherOrderComponent((BlockListBlock) => {
        return (props) => {
            const { attributes, name } = props;

            if (name !== 'woocommerce/product-details') {
                return createElement(BlockListBlock, props);
            }

            const { showShortDescription = true } = attributes;

            // Add custom CSS class when short description should be hidden
            const customProps = {
                ...props,
                className: showShortDescription 
                    ? props.className 
                    : `${props.className || ''} hide-short-description`.trim(),
            };

            return createElement(BlockListBlock, customProps);
        };
    }, 'addCustomCSS');

    addFilter(
        'editor.BlockListBlock',
        'blaze-commerce/woocommerce-product-details-extension/add-css',
        addCustomCSS
    );

    // Add CSS to hide short description in editor
    const style = document.createElement('style');
    style.textContent = `
        .hide-short-description .wc-block-components-product-summary__short-description,
        .hide-short-description .woocommerce-product-details__short-description,
        .hide-short-description [data-block-name="woocommerce/product-summary"] {
            display: none !important;
        }
    `;
    document.head.appendChild(style);

})();
