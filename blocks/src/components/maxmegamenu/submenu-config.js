import { ElementColorSelector } from '../element-color-selector'
import { boxControlDefaults } from '../types';

const {
    PanelBody,
    __experimentalBoxControl: BoxControl,
    __experimentalDivider: Divider,
    ToggleControl,
    Flex,
    FlexBlock,
    FlexItem,
} = wp.components;
const { __ } = wp.i18n;

const attributeSchema = {
    submenuFullWidth: {
        type: 'boolean',
    },
    submenuContainerBackgroundColor: {
        type: 'string',
    },

    submenuLinkColor: {
        type: 'string',
    },
    submenuLinkHoverColor: {
        type: 'string',
    },
    submenuLinkBackgroundColor: {
        type: 'string',
    },
    submenuLinkHoverBackgroundColor: {
        type: 'string',
    },

    submenuContainerPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    submenuLinkPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    submenuLinkMargin: {
        type: 'object',
        default: boxControlDefaults,
    },

    // TODO: deprecate moving forward
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
    submenuTextPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    submenuTextMargin: {
        type: 'object',
        default: boxControlDefaults,
    },
};

export const SubmenuConfig = ({ attributes, setAttributes }) => {
    const {
        submenuFullWidth,
        submenuContainerBackgroundColor,

        submenuLinkColor,
        submenuLinkHoverColor,
        submenuLinkBackgroundColor,
        submenuLinkHoverBackgroundColor,

        submenuContainerPadding,
        submenuLinkPadding,
        submenuLinkMargin,

        // TODO: deprecate moving forward
        submenuTextColor,
        submenuHoverTextColor,
        submenuBackgroundColor,
        submenuHoverBackgroundColor,
        submenuTextPadding,
        submenuTextMargin,
    } = attributes;

    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Submenu' ) }
            initialOpen={ false }
        >
            <p></p>
            <ToggleControl
                label="Submenu Full Width"
                help={
                    submenuFullWidth
                        ? 'Submenu is full width.'
                        : 'Submenu Menu width is auto.'
                }
                checked={ submenuFullWidth }
                onChange={ (newValue) => {
                    setAttributes({ submenuFullWidth: newValue });
                } }
            />

            <Divider />

            <p></p>
            <Flex>
                <FlexBlock>
                    Container Background Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuContainerBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ submenuContainerBackgroundColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>            
            
            <p></p>
            <Flex>
                <FlexBlock>
                    Link Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuLinkColor || submenuTextColor}
                        setValue={(selectedColor) => setAttributes({ submenuLinkColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuLinkHoverColor || submenuHoverTextColor}
                        setValue={(selectedColor) => setAttributes({ submenuLinkHoverColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <p></p>
            <Flex>
                <FlexBlock>
                    Link Background Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuLinkBackgroundColor || submenuBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ submenuLinkBackgroundColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuLinkHoverBackgroundColor || submenuHoverBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ submenuLinkHoverBackgroundColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <Divider />

            <p></p>
            <BoxControl
                label={ __( 'Container Padding' ) }
                values={ submenuContainerPadding }
                onChange={ ( nextValues ) => setAttributes({ submenuContainerPadding: nextValues }) }
            />

            <p></p>
            <BoxControl
                label={ __( 'Link Padding' ) }
                values={ submenuLinkPadding || submenuTextPadding }
                onChange={ ( nextValues ) => setAttributes({ submenuLinkPadding: nextValues }) }
            />

            <p></p>
            <BoxControl
                label={ __( 'Link Margin' ) }
                values={ submenuLinkMargin || submenuTextMargin }
                onChange={ ( nextValues ) => setAttributes({ submenuLinkMargin: nextValues }) }
            />

        </PanelBody>
    )
}

SubmenuConfig.attributeSchema = attributeSchema;