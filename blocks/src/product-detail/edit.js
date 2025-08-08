import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	ColorPicker,
} from "@wordpress/block-editor";
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	Placeholder,
	Spinner,
} from "@wordpress/components";
import { useState, useEffect } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import apiFetch from "@wordpress/api-fetch";

export default function Edit({ attributes, setAttributes }) {
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
		priceFontWeight,
	} = attributes;

	const [productData, setProductData] = useState(null);
	const [isLoading, setIsLoading] = useState(false);
	const [products, setProducts] = useState([]);

	// Get current post ID if we're editing a product
	const currentPostId = useSelect((select) => {
		return select("core/editor")?.getCurrentPostId();
	}, []);

	// Load products for selection
	useEffect(() => {
		const loadProducts = async () => {
			try {
				const response = await apiFetch({
					path: "/wp/v2/product?per_page=100&status=publish",
				});
				setProducts(response);
			} catch (error) {
				console.error("Error loading products:", error);
				// Fallback: try to get products from WooCommerce REST API
				try {
					const wcResponse = await apiFetch({
						path: "/wc/v3/products?per_page=100&status=publish",
					});
					setProducts(wcResponse);
				} catch (wcError) {
					console.error("Error loading products from WC API:", wcError);
				}
			}
		};

		loadProducts();
	}, []);

	// Load product data when productId changes
	useEffect(() => {
		const loadProductData = async () => {
			if (!productId) {
				setProductData(null);
				return;
			}

			setIsLoading(true);
			try {
				const response = await apiFetch({
					path: `/wp/v2/product/${productId}`,
				});
				// Transform WordPress API response to match WooCommerce format
				const transformedData = {
					id: response.id,
					name: response.title?.rendered || "",
					short_description: response.excerpt?.rendered || "",
					description: response.content?.rendered || "",
					sku: response.meta?._sku || "",
					price_html: response.meta?.price_html || "",
					stock_status: response.meta?._stock_status || "instock",
					stock_quantity: response.meta?._stock || null,
					categories: response.product_cat || [],
					tags: response.product_tag || [],
				};
				setProductData(transformedData);
			} catch (error) {
				console.error("Error loading product data:", error);
				// Fallback: try WooCommerce API
				try {
					const wcResponse = await apiFetch({
						path: `/wc/v3/products/${productId}`,
					});
					setProductData(wcResponse);
				} catch (wcError) {
					console.error("Error loading product data from WC API:", wcError);
					setProductData(null);
				}
			} finally {
				setIsLoading(false);
			}
		};

		loadProductData();
	}, [productId]);

	// Auto-select current product if we're editing a product post
	useEffect(() => {
		if (currentPostId && !productId && products.length > 0) {
			const currentProduct = products.find(
				(product) => product.id === currentPostId,
			);
			if (currentProduct) {
				setAttributes({ productId: currentPostId });
			}
		}
	}, [currentPostId, productId, products, setAttributes]);

	const blockProps = useBlockProps({
		style: {
			textAlign: alignment,
		},
	});

	const productOptions = [
		{ label: __("Select a product", "blaze-commerce"), value: 0 },
		...products.map((product) => ({
			label: product.name,
			value: product.id,
		})),
	];

	const fontWeightOptions = [
		{ label: __("Normal", "blaze-commerce"), value: "normal" },
		{ label: __("Bold", "blaze-commerce"), value: "bold" },
		{ label: __("Light", "blaze-commerce"), value: "300" },
		{ label: __("Medium", "blaze-commerce"), value: "500" },
		{ label: __("Semi Bold", "blaze-commerce"), value: "600" },
		{ label: __("Extra Bold", "blaze-commerce"), value: "800" },
	];

	const alignmentOptions = [
		{ label: __("Left", "blaze-commerce"), value: "left" },
		{ label: __("Center", "blaze-commerce"), value: "center" },
		{ label: __("Right", "blaze-commerce"), value: "right" },
	];

	const getStockStatusText = (stockStatus) => {
		switch (stockStatus) {
			case "instock":
				return __("In Stock", "blaze-commerce");
			case "outofstock":
				return __("Out of Stock", "blaze-commerce");
			case "onbackorder":
				return __("On Backorder", "blaze-commerce");
			default:
				return stockStatus;
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Product Selection", "blaze-commerce")}
					initialOpen={true}>
					<SelectControl
						label={__("Select Product", "blaze-commerce")}
						value={productId}
						options={productOptions}
						onChange={(value) => setAttributes({ productId: parseInt(value) })}
						help={__(
							"Choose which product details to display",
							"blaze-commerce",
						)}
					/>
				</PanelBody>

				<PanelBody
					title={__("Display Options", "blaze-commerce")}
					initialOpen={true}>
					<ToggleControl
						label={__("Show Short Description", "blaze-commerce")}
						checked={showShortDescription}
						onChange={(value) => setAttributes({ showShortDescription: value })}
						help={__("Display the product short description", "blaze-commerce")}
					/>

					<ToggleControl
						label={__("Show SKU", "blaze-commerce")}
						checked={showSku}
						onChange={(value) => setAttributes({ showSku: value })}
					/>

					<ToggleControl
						label={__("Show Price", "blaze-commerce")}
						checked={showPrice}
						onChange={(value) => setAttributes({ showPrice: value })}
					/>

					<ToggleControl
						label={__("Show Stock Status", "blaze-commerce")}
						checked={showStockStatus}
						onChange={(value) => setAttributes({ showStockStatus: value })}
					/>

					<ToggleControl
						label={__("Show Stock Quantity", "blaze-commerce")}
						checked={showStockQuantity}
						onChange={(value) => setAttributes({ showStockQuantity: value })}
						help={__(
							'Show stock quantity when status is "In Stock"',
							"blaze-commerce",
						)}
					/>

					<ToggleControl
						label={__("Show Categories", "blaze-commerce")}
						checked={showCategories}
						onChange={(value) => setAttributes({ showCategories: value })}
					/>

					<ToggleControl
						label={__("Show Tags", "blaze-commerce")}
						checked={showTags}
						onChange={(value) => setAttributes({ showTags: value })}
					/>
				</PanelBody>

				<PanelBody
					title={__("General Styling", "blaze-commerce")}
					initialOpen={false}>
					<p>{__("Text Color", "blaze-commerce")}</p>
					<ColorPicker
						color={textColor}
						onChange={(value) => setAttributes({ textColor: value })}
					/>

					<RangeControl
						label={__("Font Size (px)", "blaze-commerce")}
						value={fontSize}
						onChange={(value) => setAttributes({ fontSize: value })}
						min={12}
						max={32}
					/>

					<SelectControl
						label={__("Font Weight", "blaze-commerce")}
						value={fontWeight}
						options={fontWeightOptions}
						onChange={(value) => setAttributes({ fontWeight: value })}
					/>

					<RangeControl
						label={__("Line Height", "blaze-commerce")}
						value={lineHeight}
						onChange={(value) => setAttributes({ lineHeight: value })}
						min={1}
						max={3}
						step={0.1}
					/>

					<SelectControl
						label={__("Text Alignment", "blaze-commerce")}
						value={alignment}
						options={alignmentOptions}
						onChange={(value) => setAttributes({ alignment: value })}
					/>
				</PanelBody>

				{showShortDescription && (
					<PanelBody
						title={__("Short Description Styling", "blaze-commerce")}
						initialOpen={false}>
						<p>{__("Short Description Color", "blaze-commerce")}</p>
						<ColorPicker
							color={shortDescriptionColor}
							onChange={(value) =>
								setAttributes({ shortDescriptionColor: value })
							}
						/>

						<RangeControl
							label={__("Short Description Font Size (px)", "blaze-commerce")}
							value={shortDescriptionFontSize}
							onChange={(value) =>
								setAttributes({ shortDescriptionFontSize: value })
							}
							min={12}
							max={24}
						/>
					</PanelBody>
				)}

				{showPrice && (
					<PanelBody
						title={__("Price Styling", "blaze-commerce")}
						initialOpen={false}>
						<p>{__("Price Color", "blaze-commerce")}</p>
						<ColorPicker
							color={priceColor}
							onChange={(value) => setAttributes({ priceColor: value })}
						/>

						<RangeControl
							label={__("Price Font Size (px)", "blaze-commerce")}
							value={priceFontSize}
							onChange={(value) => setAttributes({ priceFontSize: value })}
							min={12}
							max={32}
						/>

						<SelectControl
							label={__("Price Font Weight", "blaze-commerce")}
							value={priceFontWeight}
							options={fontWeightOptions}
							onChange={(value) => setAttributes({ priceFontWeight: value })}
						/>
					</PanelBody>
				)}
			</InspectorControls>

			<div {...blockProps}>
				{!productId ? (
					<Placeholder
						icon="products"
						label={__("Product Detail", "blaze-commerce")}
						instructions={__(
							"Select a product from the sidebar to display its details.",
							"blaze-commerce",
						)}
					/>
				) : isLoading ? (
					<Placeholder
						icon="products"
						label={__("Product Detail", "blaze-commerce")}>
						<Spinner />
					</Placeholder>
				) : productData ? (
					<div className="blaze-product-detail">
						<h3
							style={{
								color: textColor,
								fontSize: `${fontSize + 4}px`,
								fontWeight: "bold",
								margin: "0 0 1rem 0",
							}}>
							{productData.name}
						</h3>

						{showShortDescription && productData.short_description && (
							<div
								className="product-short-description"
								style={{
									color: shortDescriptionColor,
									fontSize: `${shortDescriptionFontSize}px`,
									lineHeight: lineHeight,
									marginBottom: "1rem",
								}}
								dangerouslySetInnerHTML={{
									__html: productData.short_description,
								}}
							/>
						)}

						{showSku && productData.sku && (
							<p
								style={{
									color: textColor,
									fontSize: `${fontSize}px`,
									fontWeight: fontWeight,
									lineHeight: lineHeight,
									margin: "0.5rem 0",
								}}>
								<strong>{__("SKU:", "blaze-commerce")}</strong>{" "}
								{productData.sku}
							</p>
						)}

						{showPrice && (
							<p
								className="product-price"
								style={{
									color: priceColor,
									fontSize: `${priceFontSize}px`,
									fontWeight: priceFontWeight,
									margin: "0.5rem 0",
								}}
								dangerouslySetInnerHTML={{ __html: productData.price_html }}
							/>
						)}

						{showStockStatus && (
							<p
								style={{
									color: textColor,
									fontSize: `${fontSize}px`,
									fontWeight: fontWeight,
									lineHeight: lineHeight,
									margin: "0.5rem 0",
								}}>
								<strong>{__("Stock Status:", "blaze-commerce")}</strong>{" "}
								{getStockStatusText(productData.stock_status)}
								{showStockQuantity &&
									productData.stock_status === "instock" &&
									productData.stock_quantity && (
										<span>
											{" "}
											({productData.stock_quantity}{" "}
											{__("available", "blaze-commerce")})
										</span>
									)}
							</p>
						)}

						{showCategories &&
							productData.categories &&
							productData.categories.length > 0 && (
								<p
									style={{
										color: textColor,
										fontSize: `${fontSize}px`,
										fontWeight: fontWeight,
										lineHeight: lineHeight,
										margin: "0.5rem 0",
									}}>
									<strong>{__("Categories:", "blaze-commerce")}</strong>{" "}
									{productData.categories.map((cat) => cat.name).join(", ")}
								</p>
							)}

						{showTags && productData.tags && productData.tags.length > 0 && (
							<p
								style={{
									color: textColor,
									fontSize: `${fontSize}px`,
									fontWeight: fontWeight,
									lineHeight: lineHeight,
									margin: "0.5rem 0",
								}}>
								<strong>{__("Tags:", "blaze-commerce")}</strong>{" "}
								{productData.tags.map((tag) => tag.name).join(", ")}
							</p>
						)}
					</div>
				) : (
					<p style={{ color: "#999", fontStyle: "italic" }}>
						{__("Product not found.", "blaze-commerce")}
					</p>
				)}
			</div>
		</>
	);
}
