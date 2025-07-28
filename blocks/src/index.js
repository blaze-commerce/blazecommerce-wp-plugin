import { GeneralConfig } from "./components/maxmegamenu/general-config";
import { TypographyConfig } from "./components/maxmegamenu/typography-config";
import { MainMenuConfig } from "./components/maxmegamenu/main-menu-config";
import { SubmenuConfig } from "./components/maxmegamenu/submenu-config";

// Import blocks
import "./service-features";
import "./kajal-collection-menu";
import "./stock-status";
import "./product-description";
import "./product-detail";

const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls, store: editorStore } = wp.editor;
const { addFilter } = wp.hooks;
const { select } = wp.data;

// Enable spacing control on the following blocks
const enableSpacingControlOnBlocks = ["maxmegamenu/location"];

/**
 * Add spacing control attribute to block.
 *
 * @param {object} settings Current block settings.
 * @param {string} name Name of block.
 *
 * @returns {object} Modified block settings.
 */
const addMenuAttributes = (settings, name) => {
	// Do nothing if it's another block than our defined ones.
	if (!enableSpacingControlOnBlocks.includes(name)) {
		return settings;
	}

	// Use Lodash's assign to gracefully handle if attributes are undefined
	settings.attributes = Object.assign(
		settings.attributes,
		Object.assign(
			{},
			GeneralConfig.attributeSchema,
			TypographyConfig.attributeSchema,
			MainMenuConfig.attributeSchema,
			SubmenuConfig.attributeSchema,
		),
	);

	return settings;
};

addFilter(
	"blocks.registerBlockType",
	"extend-block-example/attribute/spacing",
	addMenuAttributes,
);

/**
 * Create HOC to add spacing control to inspector controls of block.
 */
const withSpacingControl = createHigherOrderComponent((BlockEdit) => {
	return (props) => {
		// Do nothing if it's another block than our defined ones.
		if (!enableSpacingControlOnBlocks.includes(props.name)) {
			return <BlockEdit {...props} />;
		}

		return (
			<Fragment>
				<BlockEdit {...props} />
				<InspectorControls>
					<GeneralConfig {...props} />
					<MainMenuConfig {...props} />
					<SubmenuConfig {...props} />
					<TypographyConfig {...props} />
				</InspectorControls>
			</Fragment>
		);
	};
}, "withSpacingControl");

addFilter(
	"editor.BlockEdit",
	"extend-block-example/with-spacing-control",
	withSpacingControl,
);

/**
 * Add margin style attribute to save element of block.
 *
 * @param {object} saveElementProps Props of save element.
 * @param {Object} blockType Block type information.
 * @param {Object} attributes Attributes of block.
 *
 * @returns {object} Modified props of save element.
 */
const addSpacingExtraProps = (saveElementProps, blockType, attributes) => {
	// Do nothing if it's another block than our defined ones.
	if (!enableSpacingControlOnBlocks.includes(blockType.name)) {
		return saveElementProps;
	}

	const margins = {
		small: "5px",
		medium: "15px",
		large: "30px",
	};

	if (attributes.spacing in margins) {
		// Use Lodash's assign to gracefully handle if attributes are undefined
		saveElementProps = Object.assign(saveElementProps, {
			style: { "margin-bottom": margins[attributes.spacing] },
		});
	}

	return saveElementProps;
};

addFilter(
	"blocks.getSaveContent.extraProps",
	"extend-block-example/get-save-content/extra-props",
	addSpacingExtraProps,
);
