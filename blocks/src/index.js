import { ColorConfig } from "./components/maxmegamenu/color-config";
import { LayoutConfig } from "./components/maxmegamenu/layout-config";
import { SpacingConfig } from "./components/maxmegamenu/spacing-config";
import { TypographyConfig } from "./components/maxmegamenu/typography-config";

const { createHigherOrderComponent } = wp.compose;
const { Fragment } = wp.element;
const { InspectorControls } = wp.editor;
const { addFilter } = wp.hooks;

// Enable spacing control on the following blocks
const enableSpacingControlOnBlocks = [
	'maxmegamenu/location',
];

const boxControlDefaults = {
    top: '0px',
    left: '0px',
    right: '0px',
    bottom: '0px',
};

const menuAttributes = {
    mainNavigationBackgroundColor: {
        type: 'string',
    },

    menuTextColor: {
        type: 'string',
    },
    menuHoverTextColor: {
        type: 'string',
    },
    menuBackgroundColor: {
        type: 'string',
    },
    menuHoverBackgroundColor: {
        type: 'string',
    },
    submenuTextColor: {
        type: 'string',
    },
    submenuHoverTextColor: {
        type: 'string',
    },
    submenuBackgroundColor: {
        type: 'string',
    },
    submenuHoverBackgroundColor: {
        type: 'string',
    },

    menuSeparatorColor: {
        type: 'string',
    },

    menuTextPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    menuTextMargin: {
        type: 'object',
        default: boxControlDefaults,
    },
    submenuTextPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    submenuTextMargin: {
        type: 'object',
        default: boxControlDefaults,
    },

    menuCentered: {
        type: 'boolean',
    },
    menuFullWidth: {
        type: 'boolean',
    },

    fontSize: {
        tyupe: 'string',
    }
};

/**
 * Add spacing control attribute to block.
 *
 * @param {object} settings Current block settings.
 * @param {string} name Name of block.
 *
 * @returns {object} Modified block settings.
 */
const addMenuAttributes = ( settings, name ) => {
	// Do nothing if it's another block than our defined ones.
	if ( ! enableSpacingControlOnBlocks.includes( name ) ) {
		return settings;
	}

	// Use Lodash's assign to gracefully handle if attributes are undefined
	settings.attributes = Object.assign( settings.attributes, menuAttributes);
    settings.supports = Object.assign( settings.supports, {
        "typography": {
			"fontSize": true,
			"lineHeight": true,
			"__experimentalFontFamily": true,
			"__experimentalFontWeight": true,
			"__experimentalFontStyle": true,
			"__experimentalTextTransform": true,
			"__experimentalTextDecoration": true,
			"__experimentalLetterSpacing": true,
			"__experimentalDefaultControls": {
				"fontSize": true
			}
		},
    })

	return settings;
};

addFilter( 'blocks.registerBlockType', 'extend-block-example/attribute/spacing', addMenuAttributes );

/**
 * Create HOC to add spacing control to inspector controls of block.
 */
const withSpacingControl = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
        console.log('props', props)
		// Do nothing if it's another block than our defined ones.
		if ( ! enableSpacingControlOnBlocks.includes( props.name ) ) {
			return (
				<BlockEdit { ...props } />
			);
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
                    <LayoutConfig { ...props } />
                    <SpacingConfig { ...props } />
                    <ColorConfig { ...props } />
                    <TypographyConfig { ...props } />
				</InspectorControls>
			</Fragment>
		);
	};
}, 'withSpacingControl' );

addFilter( 'editor.BlockEdit', 'extend-block-example/with-spacing-control', withSpacingControl );

/**
 * Add margin style attribute to save element of block.
 *
 * @param {object} saveElementProps Props of save element.
 * @param {Object} blockType Block type information.
 * @param {Object} attributes Attributes of block.
 *
 * @returns {object} Modified props of save element.
 */
const addSpacingExtraProps = ( saveElementProps, blockType, attributes ) => {
	// Do nothing if it's another block than our defined ones.
	if ( ! enableSpacingControlOnBlocks.includes( blockType.name ) ) {
		return saveElementProps;
	}

	const margins = {
		small: '5px',
		medium: '15px',
		large: '30px',
	};

	if ( attributes.spacing in margins ) {
		// Use Lodash's assign to gracefully handle if attributes are undefined
		saveElementProps = Object.assign( saveElementProps, { style: { 'margin-bottom': margins[ attributes.spacing ] } } );
	}

	return saveElementProps;
};

addFilter( 'blocks.getSaveContent.extraProps', 'extend-block-example/get-save-content/extra-props', addSpacingExtraProps );