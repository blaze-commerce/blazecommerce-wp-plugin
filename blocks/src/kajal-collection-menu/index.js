/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import Edit from './edit';
import save from './save';
import metadata from './block.json';

/**
 * Register the Kajal Collection Menu block.
 */
registerBlockType(metadata.name, {
	...metadata,
	edit: Edit,
	save,
});
