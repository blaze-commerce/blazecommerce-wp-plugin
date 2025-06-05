import { useBlockProps } from '@wordpress/block-editor';

export default function save({ attributes }) {
	const {
		productId,
		showShortDescription,
		showSku,
		showPrice,
		showStockStatus,
		showStockQuantity,
		showCategories,
		showTags,
		textColor,
		fontSize,
		fontWeight,
		lineHeight,
		alignment,
		shortDescriptionColor,
		shortDescriptionFontSize,
		priceColor,
		priceFontSize,
		priceFontWeight
	} = attributes;

	const blockProps = useBlockProps.save({
		className: 'blaze-product-detail-block',
		'data-product-id': productId,
		style: {
			textAlign: alignment,
		}
	});

	// Return null for dynamic rendering on frontend
	// The actual content will be rendered by PHP
	return (
		<div {...blockProps}>
			<div 
				className="blaze-product-detail"
				data-product-id={productId}
				data-show-short-description={showShortDescription}
				data-show-sku={showSku}
				data-show-price={showPrice}
				data-show-stock-status={showStockStatus}
				data-show-stock-quantity={showStockQuantity}
				data-show-categories={showCategories}
				data-show-tags={showTags}
				data-text-color={textColor}
				data-font-size={fontSize}
				data-font-weight={fontWeight}
				data-line-height={lineHeight}
				data-alignment={alignment}
				data-short-description-color={shortDescriptionColor}
				data-short-description-font-size={shortDescriptionFontSize}
				data-price-color={priceColor}
				data-price-font-size={priceFontSize}
				data-price-font-weight={priceFontWeight}
			>
				{/* Content will be rendered by PHP on frontend */}
			</div>
		</div>
	);
}
