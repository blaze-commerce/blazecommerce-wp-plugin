const { useState } = wp.element;
const {
    PanelBody,
    FontSizePicker,
} = wp.components;
// const { FontSizePicker } = wp.editor;
const { __ } = wp.i18n;

const fontSizes = [
    {
        name: __( 'Small' ),
        slug: 'small',
        size: 12,
    },
    {
        name: __( 'Medium' ),
        slug: 'medium',
        size: 18,
    },
];

const fallbackFontSize = 16;

export const TypographyConfig = ({ attributes, setAttributes }) => {
    const [ fontSize, setFontSize ] = useState( 12 );
    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Typography' ) }
            initialOpen={ false }
        >
            <FontSizePicker
                fontSizes={ fontSizes }
                value={ fontSize }
                fallbackFontSize={ fallbackFontSize }
                onChange={ ( newFontSize ) => {
                    setFontSize( newFontSize );
                } }
            />
        </PanelBody>
    )
}