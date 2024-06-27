const {
    PanelBody,
    SelectControl,
} = wp.components;
const { __ } = wp.i18n;

export const GeneralConfig = ({ attributes, setAttributes }) => {
    const {
        menuId,
    } = attributes;

    let options = [{
        label: 'Select a menu',
        value: '',
    }];

    const menus = blaze_commerce_block_config.menus || [];
    const menuOptions = menus.map((menu) => ({
        label: menu.name,
        value: menu.term_id,
    }));

    return (
        <PanelBody
            title={ __( 'Blaze Commerce - General' ) }
            initialOpen={ true }
        >
            <p></p>
            <SelectControl
                label="Menu"
                value={menuId}
                options={[...options, ...menuOptions]}
                onChange={(newMenuId) => setAttributes({ menuId: newMenuId })}
                __nextHasNoMarginBottom
            />
        </PanelBody>
    );
}