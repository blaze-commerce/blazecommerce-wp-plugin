const {
    PanelBody,
    ToggleControl,
} = wp.components;
const { __ } = wp.i18n;

export const LayoutConfig = ({ attributes, setAttributes }) => {
    const {
        menuCentered,
        menuFullWidth,
    } = attributes;
    return (
        <PanelBody
            title={ __( 'Blaze Commerce - Layout' ) }
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
        </PanelBody>
    );
}