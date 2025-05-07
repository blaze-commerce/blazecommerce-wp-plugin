/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor || wp.editor;
const { PanelBody, ToggleControl } = wp.components;

/**
 * Add lazy loading attribute to Image block
 *
 * @param {Object} settings Block settings.
 * @param {string} name Block name.
 * @return {Object} Modified block settings.
 */
function addLazyLoadAttribute(settings, name) {
	// Only add attribute to image block
	if (name !== "core/image") {
		return settings;
	}

	// Add new lazyLoad attribute
	if (settings.attributes) {
		settings.attributes = Object.assign(settings.attributes, {
			lazyLoad: {
				type: "boolean",
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
		if (props.name !== "core/image") {
			return <BlockEdit {...props} />;
		}

		const { attributes, setAttributes } = props;
		const { lazyLoad } = attributes;

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody
						title={__("Loading Settings", "blaze-commerce")}
						initialOpen={false}>
						<ToggleControl
							label={__("Enable Lazy Loading", "blaze-commerce")}
							checked={lazyLoad}
							onChange={(value) => setAttributes({ lazyLoad: value })}
							help={
								lazyLoad
									? __("Image will be lazy loaded.", "blaze-commerce")
									: __("Image will load immediately.", "blaze-commerce")
							}
						/>
					</PanelBody>
				</InspectorControls>
			</Fragment>
		);
	};
}, "withLazyLoadControl");

/**
 * Apply lazy loading attribute to the block's HTML
 *
 * @param {Object} extraProps Additional props applied to save element.
 * @param {Object} blockType Block type.
 * @param {Object} attributes Current block attributes.
 * @return {Object} Modified props.
 */
function addLazyLoadExtraProps(extraProps, blockType, attributes) {
	// Only add loading attribute to image block
	if (blockType.name !== "core/image") {
		return extraProps;
	}

	// If lazyLoad is true, add loading="lazy" attribute
	// If lazyLoad is false, add loading="eager" attribute
	// If lazyLoad is undefined (for existing blocks), default to lazy loading
	const shouldLazyLoad =
		typeof attributes.lazyLoad !== "undefined" ? attributes.lazyLoad : true;
	extraProps.loading = shouldLazyLoad ? "lazy" : "eager";

	return extraProps;
}

/**
 * Filter to handle block migration for existing blocks
 *
 * @param {Object} block Block object.
 * @return {Object} Migrated block object.
 */
function migrateImageBlock(block) {
	// Only migrate image blocks
	if (block.name !== "core/image") {
		return block;
	}

	// If the block doesn't have the lazyLoad attribute, add it with default value
	if (block.attributes && typeof block.attributes.lazyLoad === "undefined") {
		block.attributes.lazyLoad = true;
	}

	return block;
}

// Register filters
addFilter(
	"blocks.registerBlockType",
	"blaze-commerce/image-lazy-load/add-attribute",
	addLazyLoadAttribute,
);

addFilter(
	"editor.BlockEdit",
	"blaze-commerce/image-lazy-load/add-control",
	withLazyLoadControl,
);

addFilter(
	"blocks.getSaveContent.extraProps",
	"blaze-commerce/image-lazy-load/add-lazy-load-prop",
	addLazyLoadExtraProps,
);

// Add filter to migrate existing blocks
addFilter(
	"blocks.getBlockAttributes",
	"blaze-commerce/image-lazy-load/migrate-attributes",
	function (attributes, blockType) {
		// Only process image blocks
		if (blockType.name !== "core/image") {
			return attributes;
		}

		// If lazyLoad attribute is missing, add it with default value
		if (typeof attributes.lazyLoad === "undefined") {
			return {
				...attributes,
				lazyLoad: true,
			};
		}

		return attributes;
	},
);
