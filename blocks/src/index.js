import { ElementColorSelector } from "./components/element-color-selector";

const { createHigherOrderComponent } = wp.compose;
const { Fragment, useState } = wp.element;
const { InspectorControls } = wp.editor;
const {
    PanelBody,
    SelectControl,
    ColorPalette,
    ColorPicker,
    __experimentalToolsPanel,
    ToolsPanelItem,
    BoxControl,
    Button,
    Popover,
    __experimentalDivider: Divider,
    Flex,
    FlexBlock,
    FlexItem,
} = wp.components;
const { addFilter } = wp.hooks;
const { __ } = wp.i18n;
const ToolsPanel = __experimentalToolsPanel;

// Enable spacing control on the following blocks
const enableSpacingControlOnBlocks = [
	'maxmegamenu/location',
];

// Available spacing control options
const spacingControlOptions = [
	{
		label: __( 'None' ),
		value: '',
	},
	{
		label: __( 'Small' ),
		value: 'small',
	},
	{
		label: __( 'Medium' ),
		value: 'medium',
	},
	{
		label: __( 'Large' ),
		value: 'large',
	},
];

const maxMegaMenuAttributes = {
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
};

/**
 * Add spacing control attribute to block.
 *
 * @param {object} settings Current block settings.
 * @param {string} name Name of block.
 *
 * @returns {object} Modified block settings.
 */
const addSpacingControlAttribute = ( settings, name ) => {
	// Do nothing if it's another block than our defined ones.
	if ( ! enableSpacingControlOnBlocks.includes( name ) ) {
		return settings;
	}

    console.log(settings.attributes)

	// Use Lodash's assign to gracefully handle if attributes are undefined
	settings.attributes = Object.assign( settings.attributes, {
		spacing: {
			type: 'string',
			default: spacingControlOptions[ 0 ].value,
		},
	}, maxMegaMenuAttributes);

	return settings;
};

addFilter( 'blocks.registerBlockType', 'extend-block-example/attribute/spacing', addSpacingControlAttribute );

/**
 * Create HOC to add spacing control to inspector controls of block.
 */
const withSpacingControl = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		// Do nothing if it's another block than our defined ones.
		if ( ! enableSpacingControlOnBlocks.includes( props.name ) ) {
			return (
				<BlockEdit { ...props } />
			);
		}

		const { 
            menuTextColor,
            menuHoverTextColor,
            menuBackgroundColor,
            menuHoverBackgroundColor,

            submenuTextColor,
            submenuHoverTextColor,
            submenuBackgroundColor,
            submenuHoverBackgroundColor,

            menuSeparatorColor,
        } = props.attributes;

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Blaze Commerce - Colors' ) }
						initialOpen={ true }
					>
                        <Flex>
                            <FlexBlock>
                                Menu Link
                            </FlexBlock>
                            <FlexItem>
                                <ElementColorSelector
                                    value={menuTextColor}
                                    setValue={(selectedColor) => props.setAttributes({ menuTextColor: selectedColor })}
                                />
                            </FlexItem>
                            <FlexItem>
                                <ElementColorSelector
                                    value={menuHoverTextColor}
                                    setValue={(selectedColor) => props.setAttributes({ menuHoverTextColor: selectedColor })}
                                />
                            </FlexItem>
                        </Flex>
                        <p></p>
                        <Flex>
                            <FlexBlock>
                                Menu Background
                            </FlexBlock>
                            <FlexItem>
                                <ElementColorSelector
                                    value={menuBackgroundColor}
                                    setValue={(selectedColor) => props.setAttributes({ menuBackgroundColor: selectedColor })}
                                />
                            </FlexItem>
                            <FlexItem>
                                <ElementColorSelector
                                    value={menuHoverBackgroundColor}
                                    setValue={(selectedColor) => props.setAttributes({ menuHoverBackgroundColor: selectedColor })}
                                />
                            </FlexItem>
                        </Flex>
                        <p></p>
                        <Flex>
                            <FlexBlock>
                                Submenu Text
                            </FlexBlock>
                            <FlexItem>
                                <ElementColorSelector
                                    value={submenuTextColor}
                                    setValue={(selectedColor) => props.setAttributes({ submenuTextColor: selectedColor })}
                                />
                            </FlexItem>
                            <FlexItem>
                                <ElementColorSelector
                                    value={submenuHoverTextColor}
                                    setValue={(selectedColor) => props.setAttributes({ submenuHoverTextColor: selectedColor })}
                                />
                            </FlexItem>
                        </Flex>

                        <p></p>
                        <Flex>
                            <FlexBlock>
                                Submenu Background
                            </FlexBlock>
                            <FlexItem>
                                <ElementColorSelector
                                    value={submenuBackgroundColor}
                                    setValue={(selectedColor) => props.setAttributes({ submenuBackgroundColor: selectedColor })}
                                />
                            </FlexItem>
                            <FlexItem>
                                <ElementColorSelector
                                    value={submenuHoverBackgroundColor}
                                    setValue={(selectedColor) => props.setAttributes({ submenuHoverBackgroundColor: selectedColor })}
                                />
                            </FlexItem>
                        </Flex>

                        <Divider />
                        
                        <Flex>
                            <FlexBlock>
                                Menu Separator Color
                            </FlexBlock>
                            <FlexItem>
                                <ElementColorSelector
                                    value={menuSeparatorColor}
                                    setValue={(selectedColor) => props.setAttributes({ menuSeparatorColor: selectedColor })}
                                />
                            </FlexItem>
                        </Flex>
					</PanelBody>
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