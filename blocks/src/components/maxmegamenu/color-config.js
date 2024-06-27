import { ElementColorSelector } from '../element-color-selector'

const {
    PanelBody,
    __experimentalDivider: Divider,
    Flex,
    FlexBlock,
    FlexItem,
} = wp.components;
const { __ } = wp.i18n;

export const ColorConfig = ({ attributes, setAttributes }) => {
    const {
        mainNavigationBackgroundColor,

        menuTextColor,
        menuHoverTextColor,
        menuBackgroundColor,
        menuHoverBackgroundColor,

        submenuTextColor,
        submenuHoverTextColor,
        submenuBackgroundColor,
        submenuHoverBackgroundColor,

        menuSeparatorColor,
    } = attributes;

    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Colors' ) }
            initialOpen={ false }
        >
            <p></p>
            <Flex>
                <FlexBlock>
                    Main Navigation Background Color
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={mainNavigationBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ mainNavigationBackgroundColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>

            <Divider />
            
            <Flex>
                <FlexBlock>
                    Menu Text
                </FlexBlock>
                <FlexItem>
                    <ElementColorSelector
                        value={menuTextColor}
                        setValue={(selectedColor) => setAttributes({ menuTextColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={menuHoverTextColor}
                        setValue={(selectedColor) => setAttributes({ menuHoverTextColor: selectedColor })}
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
                        setValue={(selectedColor) => setAttributes({ menuBackgroundColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={menuHoverBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ menuHoverBackgroundColor: selectedColor })}
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
                        setValue={(selectedColor) => setAttributes({ submenuTextColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuHoverTextColor}
                        setValue={(selectedColor) => setAttributes({ submenuHoverTextColor: selectedColor })}
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
                        setValue={(selectedColor) => setAttributes({ submenuBackgroundColor: selectedColor })}
                    />
                </FlexItem>
                <FlexItem>
                    <ElementColorSelector
                        value={submenuHoverBackgroundColor}
                        setValue={(selectedColor) => setAttributes({ submenuHoverBackgroundColor: selectedColor })}
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
                        setValue={(selectedColor) => setAttributes({ menuSeparatorColor: selectedColor })}
                    />
                </FlexItem>
            </Flex>
        </PanelBody>
    )
}