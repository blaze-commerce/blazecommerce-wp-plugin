import { ElementColorSelector } from '../element-color-selector'
import { boxControlDefaults } from '../types';

const { store: editorStore } = wp.editor;
const { select } = wp.data;

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
    menuCentered: {
        type: 'boolean',
    },
    menuFullWidth: {
        type: 'boolean',
    },
    mainNavigationBackgroundColor: {
        type: 'string',
    },

    menuLinkColor: {
        type: 'string',
    },
    menuLinkHoverColor: {
        type: 'string',
    },
    menuLinkBackgroundColor: {
        type: 'string',
    },
    menuLinkHoverBackgroundColor: {
        type: 'string',
    },
    menuLinkActiveBackgroundColor: {
        type: 'string',
    },

    mobileMenuLinkColor: {
        type: 'string',
    },

    menuLinkPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    menuLinkMargin: {
        type: 'object',
        default: boxControlDefaults,
    },

    menuSeparatorColor: {
        type: 'string',
    },

    // TODO: deprecate moving forward
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
    menuTextPadding: {
        type: 'object',
        default: boxControlDefaults,
    },
    menuTextMargin: {
        type: 'object',
        default: boxControlDefaults,
    },
};

export const MainMenuConfig = ({ attributes, setAttributes }) => {
    const {
        menuCentered,
        menuFullWidth,
        mainNavigationBackgroundColor,

        menuLinkColor,
        menuLinkHoverColor,
        menuLinkBackgroundColor,
        menuLinkHoverBackgroundColor,
        menuLinkActiveBackgroundColor,

        mobileMenuLinkColor,

        menuLinkPadding,
        menuLinkMargin,

        menuSeparatorColor,

        // TODO: deprecate moving forward
        menuTextColor,
        menuHoverTextColor,
        menuBackgroundColor,
        menuHoverBackgroundColor,
        menuTextPadding,
        menuTextMargin,
    } = attributes;

    const isMobile = select(editorStore).getDeviceType() === 'Mobile';

    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Main Menu' ) }
            initialOpen={ false }
        >
            <p></p>
            <ToggleControl
                label="Centered"
                help={
                    menuCentered
                        ? 'Menu is centered.'
                        : 'Menu starts on the left.'
                }
                checked={ menuCentered }
                onChange={ (newValue) => {
                    setAttributes({ menuCentered: newValue });
                } }
            />
            <p></p>
            <ToggleControl
                label="Full Width"
                help={
                    menuCentered
                        ? 'Menu is full width.'
                        : 'Menu width is auto.'
                }
                checked={ menuFullWidth }
                onChange={ (newValue) => {
                    setAttributes({ menuFullWidth: newValue });
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
                        value={mainNavigationBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ mainNavigationBackgroundColor: selectedColor })}
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
                        value={isMobile ? mobileMenuLinkColor : (menuLinkColor || menuTextColor)}
                        setValue={(selectedColor) => setAttributes({ [isMobile ? 'mobileMenuLinkColor' : 'menuLinkColor']: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={menuLinkHoverColor || menuHoverTextColor}
                        setValue={(selectedColor) => setAttributes({ menuLinkHoverColor: selectedColor })}
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
                        value={menuLinkBackgroundColor || menuBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ menuLinkBackgroundColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={menuLinkHoverBackgroundColor || menuHoverBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ menuLinkHoverBackgroundColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <p></p>
            <Flex>
                <FlexBlock>
                    Active Link Background Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={menuLinkActiveBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ menuLinkActiveBackgroundColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <p></p>
            <Flex>
                <FlexBlock>
                    Link Separator Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={menuSeparatorColor}
                        setValue={(selectedColor) => setAttributes({ menuSeparatorColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <Divider />

            <p></p>
            <BoxControl
                label={ __( 'Link Padding' ) }
                values={ menuLinkPadding || menuTextPadding }
                onChange={ ( nextValues ) => setAttributes({ menuLinkPadding: nextValues }) }
            />

            <p></p>
            <BoxControl
                label={ __( 'Link Margin' ) }
                values={ menuLinkMargin || menuTextMargin }
                onChange={ ( nextValues ) => setAttributes({ menuLinkMargin: nextValues }) }
            />
        </PanelBody>
    )
}

MainMenuConfig.attributeSchema = attributeSchema;