import { useBlockProps } from "@wordpress/block-editor";

export default function save({ attributes }) {
	const { productId } = attributes;

	const blockProps = useBlockProps.save({
		className: "blaze-product-description-block",
		"data-product-id": productId,
	});

	// Return null for dynamic rendering on frontend
	// The actual content will be rendered by PHP
	return (
		<div {...blockProps}>
			<div className="blaze-product-description" data-product-id={productId}>
				{/* Content will be rendered by PHP on frontend */}
			</div>
		</div>
	);
}
