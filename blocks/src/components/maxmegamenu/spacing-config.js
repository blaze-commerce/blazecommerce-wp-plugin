const {
    PanelBody,
    __experimentalBoxControl: BoxControl
} = wp.components;
const { __ } = wp.i18n;

export const SpacingConfig = ({ attributes, setAttributes }) => {
    const {
        menuTextPadding,
        menuTextMargin,
        submenuTextPadding,
        submenuTextMargin,
    } = attributes;

    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Spacing' ) }
            initialOpen={ false }
        >
            <BoxControl
                label={ __( 'Menu Text Padding' ) }
                values={ menuTextPadding }
                onChange={ ( nextValues ) => setAttributes({ menuTextPadding: nextValues }) }
            />
            <p></p>

            <BoxControl
                label={ __( 'Menu Text Margin' ) }
                values={ menuTextMargin }
                onChange={ ( nextValues ) => setAttributes({ menuTextMargin: nextValues }) }
            />
            <p></p>

            <BoxControl
                label={ __( 'Submenu Text Padding' ) }
                values={ submenuTextPadding }
                onChange={ ( nextValues ) => setAttributes({ submenuTextPadding: nextValues }) }
            />
            <p></p>

            <BoxControl
                label={ __( 'Submenu Text Margin' ) }
                values={ submenuTextMargin }
                onChange={ ( nextValues ) => setAttributes({ submenuTextMargin: nextValues }) }
            />
        </PanelBody>
    )
}