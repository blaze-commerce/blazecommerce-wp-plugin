const { useState } = wp.element;
const {
    PanelBody,
    FontSizePicker,
    SelectControl,
} = wp.components;
// const { FontSizePicker } = wp.editor;
const { __ } = wp.i18n;

const attributeSchema = {
    fontSize: {
        type: 'string',
        default: '16',
    },
    fontWeight: {
        type: 'string',
        default: '400',
    },
    letterCase: {
        type: 'string',
        default: 'none',
    },
};

const fontSizes = [
    {
        name: __( 'Extra Small' ),
        slug: 'xs',
        size: 12,
    },
    {
        name: __( 'Small' ),
        slug: 'sm',
        size: 14,
    },
    {
        name: __( 'Base' ),
        slug: 'base',
        size: 16,
    },
    {
        name: __( 'Large' ),
        slug: 'lg',
        size: 18,
    },
    {
        name: __( 'Extra Large' ),
        slug: 'xl',
        size: 20,
    },
    {
        name: __( '2x Extra Large' ),
        slug: '2xl',
        size: 24,
    },
];

const fallbackFontSize = 16;

export const TypographyConfig = ({ attributes, setAttributes }) => {
    const {
        fontSize,
        fontWeight,
        letterCase,
    } = attributes;

    return (
        <PanelBody
            title={__('Blaze Commerce - Typography')}
            initialOpen={false}
        >
            <p></p>
            <FontSizePicker
                fontSizes={fontSizes}
                value={parseInt(fontSize)}
                fallbackFontSize={fallbackFontSize}
                withReset={true}
                withSlider={false}
                onChange={(newFontSize) => setAttributes({ fontSize: newFontSize })}
            />

            <p></p>
            <SelectControl
                label="Font weight"
                value={ fontWeight }
                options={ [
                    { label: 'Thin', value: '100' },
                    { label: 'Extra Light', value: '200' },
                    { label: 'Light', value: '300' },
                    { label: 'Normal', value: '400' },
                    { label: 'Medium', value: '500' },
                    { label: 'Semi Bold', value: '600' },
                    { label: 'Bold', value: '700' },
                    { label: 'Extra Bold', value: '800' },
                ] }
                onChange={(newFontWeight) => setAttributes({ fontWeight: newFontWeight })}
                __nextHasNoMarginBottom
            />

            <p></p>
            <SelectControl
                label="Letter case"
                value={ letterCase }
                options={ [
                    { label: 'None', value: 'none' },
                    { label: 'Uppercase', value: 'uppercase' },
                    { label: 'Lowercase', value: 'lowercase' },
                    { label: 'Capitalize', value: 'capitalize' },
                ] }
                onChange={ ( newLetterCase ) => setAttributes({ letterCase: newLetterCase }) }
                __nextHasNoMarginBottom
            />
        </PanelBody>
    )
}

TypographyConfig.attributeSchema = attributeSchema;