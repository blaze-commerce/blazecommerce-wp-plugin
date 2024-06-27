/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';
import { SelectControl, Placeholder, Disabled } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';
import metadata from './block';
const { name } = metadata;

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function Edit( { setAttributes, attributes, isSelected } ) {

    if ( window.max_mega_menu_locations.length === 0 ) {
        return (
            <div {...useBlockProps()}>
                {__('Error: max_mega_menu_locations missing.', 'megamenu')}
            </div>
        );
    }

    const options = Object.keys(
        window.max_mega_menu_locations
    ).map( ( location ) => {
        return {
            value: location,
            label: window.max_mega_menu_locations[location],
        };
    } );

    if ( options.length === 1 ) {
        return (
            <div {...useBlockProps()}>
                {__('No locations found. Go to Mega Menu > Menu Locations to create a new menu location.', 'megamenu')}
            </div>
        );
    }

    const onSaveMenu = (value) => {
        setAttributes({location: String( value )});
    };

    return (
        <div {...useBlockProps()}>
            {isSelected || !attributes.location || attributes.location === '' ? (
                    <Placeholder
                        label={__('Max Mega Menu', 'megamenu')}
                    >
                        <SelectControl
                            label={__('Select a location', 'megamenu')}
                            options={options}
                            value={attributes.location}
                            onChange={onSaveMenu}
                        />
                    </Placeholder>
            ) : (
                <Disabled>
                    <ServerSideRender block={name} attributes={attributes} />
                </Disabled>
            )}
        </div>
    );
}
