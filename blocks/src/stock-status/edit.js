/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	BlockControls,
	AlignmentToolbar,
	InspectorControls,
} from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	Placeholder,
	Icon,
	SelectControl,
} from "@wordpress/components";

/**
 * The edit function for the Stock Status block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const { align, showQuantity } = attributes;
	const blockProps = useBlockProps({
		className: `stock-status align-${align}`,
	});

	// Sample stock status for editor preview
	const previewStockStatus = "instock";
	const previewStockQuantity = 10;

	// Function to get stock status label
	const getStockStatusLabel = (status) => {
		switch (status) {
			case "instock":
				return __("In stock", "blaze-commerce");
			case "outofstock":
				return __("Out of stock", "blaze-commerce");
			case "onbackorder":
				return __("On backorder", "blaze-commerce");
			default:
				return __("Unknown", "blaze-commerce");
		}
	};

	// Function to get stock status class
	const getStockStatusClass = (status) => {
		switch (status) {
			case "instock":
				return "in-stock";
			case "outofstock":
				return "out-of-stock";
			case "onbackorder":
				return "on-backorder";
			default:
				return "";
		}
	};

	return (
		<>
			<BlockControls>
				<AlignmentToolbar
					value={align}
					onChange={(newAlign) => setAttributes({ align: newAlign })}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody
					title={__("Stock Status Settings", "blaze-commerce")}
					initialOpen={true}>
					<ToggleControl
						label={__("Show Stock Quantity", "blaze-commerce")}
						help={
							showQuantity
								? __(
										"Stock quantity will be displayed when product is in stock.",
										"blaze-commerce",
								  )
								: __("Stock quantity will be hidden.", "blaze-commerce")
						}
						checked={showQuantity}
						onChange={(value) => setAttributes({ showQuantity: value })}
					/>
					<SelectControl
						label={__("Alignment", "blaze-commerce")}
						value={align}
						options={[
							{ label: __("Left", "blaze-commerce"), value: "left" },
							{ label: __("Center", "blaze-commerce"), value: "center" },
							{ label: __("Right", "blaze-commerce"), value: "right" },
						]}
						onChange={(newAlign) => setAttributes({ align: newAlign })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="stock-status-container">
					<div
						className={`stock-status-indicator ${getStockStatusClass(
							previewStockStatus,
						)}`}>
						<span className="stock-status-text">
							{getStockStatusLabel(previewStockStatus)}
						</span>
						{showQuantity && previewStockStatus === "instock" && (
							<span className="stock-quantity">
								{__("Quantity:", "blaze-commerce")} {previewStockQuantity}
							</span>
						)}
					</div>
					<div className="stock-status-editor-note">
						<em>
							{__(
								"This is a preview. Actual stock status will be displayed on the frontend.",
								"blaze-commerce",
							)}
						</em>
					</div>
				</div>
			</div>
		</>
	);
}
