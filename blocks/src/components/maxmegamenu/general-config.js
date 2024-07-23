const {
    PanelBody,
    SelectControl,
    __experimentalUnitControl: UnitControl,
} = wp.components;
const { __ } = wp.i18n;

const attributeSchema = {
    menuId: {
        type: 'string',
    },
    menuMaxWidth: {
        type: 'string',
        default: '1200x',
    },
};

export const GeneralConfig = ({ attributes, setAttributes }) => {
    const {
        menuId,
        menuMaxWidth,
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

            <p></p>
            <UnitControl
                label="Max Width (px)"
                disableUnits={true}
                onChange={(newMenuMaxWidth) => setAttributes({ menuMaxWidth: newMenuMaxWidth })}
                value={menuMaxWidth}
            />
        </PanelBody>
    );
};

GeneralConfig.attributeSchema = attributeSchema;