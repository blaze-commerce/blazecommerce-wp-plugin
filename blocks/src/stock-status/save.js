/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function for the Stock Status block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({ attributes }) {
	const { align, showQuantity } = attributes;
	const blockProps = useBlockProps.save({
		className: `stock-status align-${align}`,
	});

	// This is a dynamic block, so we'll render a placeholder that will be replaced on the server side
	return (
		<div {...blockProps}>
			<div className="stock-status-container">
				<div className="stock-status-placeholder" data-show-quantity={showQuantity}>
					{__('Product stock status will be displayed here.', 'blaze-commerce')}
				</div>
			</div>
		</div>
	);
}
