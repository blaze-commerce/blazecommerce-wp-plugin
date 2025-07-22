(function () {
	const { __ } = wp.i18n;
	const { addFilter } = wp.hooks;
	const { Fragment, createElement } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl } = wp.components;
	const { createHigherOrderComponent } = wp.compose;

	// Add verticalStyle attribute to WooCommerce Product Image Gallery block
	function addVerticalStyleAttribute(settings, name) {
		if (name !== "woocommerce/product-image-gallery") {
			return settings;
		}

		return {
			...settings,
			attributes: {
				...settings.attributes,
				verticalStyle: {
					type: "boolean",
					default: false,
				},
				zoomOnHover: {
					type: "boolean",
					default: false,
				},
				displayZoomButton: {
					type: "boolean",
					default: true,
				},
			},
		};
	}

	// Add control to block inspector
	const withVerticalStyleControl = createHigherOrderComponent((BlockEdit) => {
		return (props) => {
			const { attributes, setAttributes, name } = props;

			if (name !== "woocommerce/product-image-gallery") {
				return createElement(BlockEdit, props);
			}

			const { verticalStyle = false } = attributes;

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
							title: __("Style Options", "blaze-commerce"),
							initialOpen: false,
						},
						createElement(ToggleControl, {
							label: __("Vertical Style", "blaze-commerce"),
							checked: verticalStyle,
							onChange: (value) => {
								setAttributes({ verticalStyle: value });
							},
							help: __(
								"Enable vertical style layout for the image gallery.",
								"blaze-commerce",
							),
						}),
						createElement(ToggleControl, {
							label: __("Zoom on Hover", "blaze-commerce"),
							checked: zoomOnHover,
							onChange: (value) => {
								setAttributes({ zoomOnHover: value });
							},
							help: __(
								"Enable zoom on hover for the image gallery.",
								"blaze-commerce",
							),
						}),
						createElement(ToggleControl, {
							label: __("Display Zoom Button", "blaze-commerce"),
							checked: displayZoomButton,
							onChange: (value) => {
								setAttributes({ displayZoomButton: value });
							},
							help: __(
								"Display zoom button for the image gallery.",
								"blaze-commerce",
							),
						}),
					),
				),
			);
		};
	}, "withVerticalStyleControl");

	// Apply the filters
	addFilter(
		"blocks.registerBlockType",
		"blaze-commerce/woocommerce-product-image-gallery-extension/add-attribute",
		addVerticalStyleAttribute,
	);

	addFilter(
		"editor.BlockEdit",
		"blaze-commerce/woocommerce-product-image-gallery-extension/add-control",
		withVerticalStyleControl,
	);

	// Add custom CSS class when vertical style is enabled
	const addCustomCSS = createHigherOrderComponent((BlockListBlock) => {
		return (props) => {
			const { attributes, name } = props;

			if (name !== "woocommerce/product-image-gallery") {
				return createElement(BlockListBlock, props);
			}

			const { verticalStyle = false } = attributes;

			// Add custom CSS class when vertical style is enabled
			const customProps = {
				...props,
				className: verticalStyle
					? `${props.className || ""} vertical-style`.trim()
					: props.className,
			};

			return createElement(BlockListBlock, customProps);
		};
	}, "addCustomCSS");

	addFilter(
		"editor.BlockListBlock",
		"blaze-commerce/woocommerce-product-image-gallery-extension/add-css",
		addCustomCSS,
	);
})();
