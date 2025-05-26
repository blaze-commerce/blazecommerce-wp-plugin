/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	RichText,
} from "@wordpress/block-editor";
import {
	PanelBody,
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	Placeholder,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalDivider as Divider,
	TabPanel,
	Icon,
	ColorPicker,
	RangeControl,
} from "@wordpress/components";
import { useState, useCallback, useMemo } from "@wordpress/element";

/**
 * Internal dependencies
 */
import "./editor.scss";

/**
 * The edit function for the Service Features block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		items,
		align,
		itemsDirection,
		textColor,
		textSize,
		textTransform,
		textFontWeight,
		textLineHeight,
		triggerColor,
		triggerSize,
		logoSize,
		spacing,
		showDivider,
		dividerColor,
	} = attributes;
	// Generate Tailwind classes based on attributes (same as save.js)
	const getAlignmentClass = () => {
		switch (align) {
			case "center":
				return "justify-center text-center";
			case "right":
				return "justify-end text-right";
			default:
				return "justify-start text-left";
		}
	};

	const getDirectionClass = () => {
		return itemsDirection === "vertical" ? "flex-col" : "flex-row flex-wrap";
	};

	const getSpacingClass = () => {
		const spacingMap = {
			10: "gap-2.5",
			15: "gap-4",
			20: "gap-5",
			25: "gap-6",
			30: "gap-8",
			35: "gap-9",
			40: "gap-10",
			45: "gap-11",
			50: "gap-12",
		};
		return spacingMap[spacing] || "gap-5";
	};

	const getLogoSizeClass = () => {
		const sizeMap = {
			16: "w-4 h-4",
			20: "w-5 h-5",
			24: "w-6 h-6",
			28: "w-7 h-7",
			32: "w-8 h-8",
			36: "w-9 h-9",
			40: "w-10 h-10",
			44: "w-11 h-11",
			48: "w-12 h-12",
			52: "w-13 h-13",
			56: "w-14 h-14",
			60: "w-15 h-15",
			64: "w-16 h-16",
			68: "w-17 h-17",
			72: "w-18 h-18",
			76: "w-19 h-19",
			80: "w-20 h-20",
			84: "w-21 h-21",
			88: "w-22 h-22",
			92: "w-23 h-23",
			96: "w-24 h-24",
		};
		return sizeMap[logoSize] || "w-16 h-16";
	};

	const getTextSizeClass = () => {
		const sizeMap = {
			12: "text-xs",
			14: "text-sm",
			16: "text-base",
			18: "text-lg",
			20: "text-xl",
			22: "text-xl",
			24: "text-2xl",
		};
		return sizeMap[textSize] || "text-base";
	};

	const getTriggerSizeClass = () => {
		const sizeMap = {
			10: "text-xs",
			12: "text-xs",
			14: "text-sm",
			16: "text-base",
			18: "text-lg",
			20: "text-xl",
		};
		return sizeMap[triggerSize] || "text-sm";
	};

	const getTextTransformClass = () => {
		const transformMap = {
			uppercase: "uppercase",
			lowercase: "lowercase",
			capitalize: "capitalize",
			none: "normal-case",
		};
		return transformMap[textTransform] || "normal-case";
	};

	const getTextFontWeightClass = () => {
		const weightMap = {
			thin: "font-thin",
			extralight: "font-extralight",
			light: "font-light",
			normal: "font-normal",
			medium: "font-medium",
			semibold: "font-semibold",
			bold: "font-bold",
			extrabold: "font-extrabold",
			black: "font-black",
		};
		return weightMap[textFontWeight] || "font-normal";
	};

	const getTextLineHeightClass = () => {
		const lineHeightMap = {
			1: "leading-none",
			1.25: "leading-tight",
			1.375: "leading-snug",
			1.5: "leading-normal",
			1.625: "leading-relaxed",
			2: "leading-loose",
		};
		return lineHeightMap[textLineHeight] || "leading-normal";
	};

	const blockProps = useBlockProps({
		className: `w-full`,
		style: {
			width: "100%",
		},
	});

	// State to track the currently selected item for editing in the sidebar
	const [selectedItemId, setSelectedItemId] = useState(
		items.length > 0 ? items[0].id : null,
	);

	// Memoized function to add a new item
	const addItem = useCallback(() => {
		const newItem = {
			id: `item-${Date.now()}`,
			logo: {
				id: "",
				url: "",
				alt: "",
			},
			text: "Service Feature",
			triggerType: "link",
			triggerText: "Learn More",
			triggerLink: "#",
			triggerTarget: "",
			triggerRichText: "",
		};

		const newItems = [...items, newItem];
		setAttributes({
			items: newItems,
		});

		// Select the newly added item
		setSelectedItemId(newItem.id);
	}, [items, setAttributes]);

	// Memoized function to remove an item
	const removeItem = useCallback(
		(id) => {
			const newItems = items.filter((item) => item.id !== id);
			setAttributes({
				items: newItems,
			});

			// If we removed the selected item, select the first remaining item or null if none left
			if (id === selectedItemId) {
				setSelectedItemId(newItems.length > 0 ? newItems[0].id : null);
			}
		},
		[items, selectedItemId, setAttributes],
	);

	// Memoized function to update an item
	const updateItem = useCallback(
		(id, property, value) => {
			setAttributes({
				items: items.map((item) => {
					if (item.id === id) {
						if (property.includes(".")) {
							const [parent, child] = property.split(".");
							return {
								...item,
								[parent]: {
									...item[parent],
									[child]: value,
								},
							};
						}
						return {
							...item,
							[property]: value,
						};
					}
					return item;
				}),
			});
		},
		[items, setAttributes],
	);

	// Memoized selected item
	const selectedItem = useMemo(() => {
		return items.find((item) => item.id === selectedItemId) || null;
	}, [items, selectedItemId]);

	// Memoized alignment options
	const alignmentOptions = useMemo(
		() => [
			{ label: __("Left", "blaze-commerce"), value: "left" },
			{ label: __("Center", "blaze-commerce"), value: "center" },
			{ label: __("Right", "blaze-commerce"), value: "right" },
		],
		[],
	);

	// Memoized items direction options
	const itemsDirectionOptions = useMemo(
		() => [
			{
				label: __("Horizontal (Side by Side)", "blaze-commerce"),
				value: "horizontal",
			},
			{ label: __("Vertical (Stacked)", "blaze-commerce"), value: "vertical" },
		],
		[],
	);

	// Memoized trigger type options
	const triggerTypeOptions = useMemo(
		() => [
			{ label: __("Link", "blaze-commerce"), value: "link" },
			{ label: __("Shipping", "blaze-commerce"), value: "shipping" },
			{ label: __("Text", "blaze-commerce"), value: "text" },
		],
		[],
	);

	// Memoized text transform options
	const textTransformOptions = useMemo(
		() => [
			{ label: __("Normal", "blaze-commerce"), value: "none" },
			{ label: __("Uppercase", "blaze-commerce"), value: "uppercase" },
			{ label: __("Lowercase", "blaze-commerce"), value: "lowercase" },
			{ label: __("Capitalize", "blaze-commerce"), value: "capitalize" },
		],
		[],
	);

	// Memoized font weight options
	const fontWeightOptions = useMemo(
		() => [
			{ label: __("Thin", "blaze-commerce"), value: "thin" },
			{ label: __("Extra Light", "blaze-commerce"), value: "extralight" },
			{ label: __("Light", "blaze-commerce"), value: "light" },
			{ label: __("Normal", "blaze-commerce"), value: "normal" },
			{ label: __("Medium", "blaze-commerce"), value: "medium" },
			{ label: __("Semi Bold", "blaze-commerce"), value: "semibold" },
			{ label: __("Bold", "blaze-commerce"), value: "bold" },
			{ label: __("Extra Bold", "blaze-commerce"), value: "extrabold" },
			{ label: __("Black", "blaze-commerce"), value: "black" },
		],
		[],
	);

	// Memoized line height options
	const lineHeightOptions = useMemo(
		() => [
			{ label: __("None (1.0)", "blaze-commerce"), value: 1 },
			{ label: __("Tight (1.25)", "blaze-commerce"), value: 1.25 },
			{ label: __("Snug (1.375)", "blaze-commerce"), value: 1.375 },
			{ label: __("Normal (1.5)", "blaze-commerce"), value: 1.5 },
			{ label: __("Relaxed (1.625)", "blaze-commerce"), value: 1.625 },
			{ label: __("Loose (2.0)", "blaze-commerce"), value: 2 },
		],
		[],
	);

	// Memoized item selector options
	const itemSelectorOptions = useMemo(() => {
		return items.map((item, index) => ({
			label:
				__("Item", "blaze-commerce") +
				" " +
				(index + 1) +
				(item.text ? ` - ${item.text}` : ""),
			value: item.id,
		}));
	}, [items]);

	// Memoized media upload handlers
	const handleMediaSelect = useCallback(
		(media) => {
			console.log("Media received:", media);
			console.log("Selected item before:", selectedItem);

			if (selectedItem && media && media.url) {
				console.log("Updating with URL:", media.url);

				// Update the entire logo object at once
				const updatedLogo = {
					id: media.id || "",
					url: media.url,
					alt: media.alt || media.title || "",
				};

				console.log("New logo object:", updatedLogo);

				setAttributes({
					items: items.map((item) => {
						if (item.id === selectedItem.id) {
							return {
								...item,
								logo: updatedLogo,
							};
						}
						return item;
					}),
				});
			} else {
				console.log("Conditions not met:", {
					hasSelectedItem: !!selectedItem,
					hasMedia: !!media,
					hasMediaUrl: !!(media && media.url),
				});
			}
		},
		[selectedItem, items, setAttributes],
	);

	const handleLogoRemove = useCallback(() => {
		if (selectedItem) {
			const emptyLogo = {
				id: "",
				url: "",
				alt: "",
			};

			setAttributes({
				items: items.map((item) => {
					if (item.id === selectedItem.id) {
						return {
							...item,
							logo: emptyLogo,
						};
					}
					return item;
				}),
			});
		}
	}, [selectedItem, items, setAttributes]);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Service Features Settings", "blaze-commerce")}
					initialOpen={true}>
					<SelectControl
						label={__("Items Direction", "blaze-commerce")}
						value={itemsDirection}
						options={itemsDirectionOptions}
						onChange={(value) => setAttributes({ itemsDirection: value })}
						help={__("How multiple items are arranged", "blaze-commerce")}
					/>

					<Divider />

					<SelectControl
						label={__("Alignment", "blaze-commerce")}
						value={align}
						options={alignmentOptions}
						onChange={(value) => setAttributes({ align: value })}
						help={__(
							"How items are aligned within the container",
							"blaze-commerce",
						)}
					/>

					<Button
						variant="primary"
						className="add-service-feature-sidebar"
						onClick={addItem}
						icon="plus"
						style={{ marginTop: "10px", width: "100%" }}>
						{__("Add Service Feature", "blaze-commerce")}
					</Button>
				</PanelBody>

				<PanelBody title={__("Styling", "blaze-commerce")} initialOpen={false}>
					<RangeControl
						label={__("Logo Size (px)", "blaze-commerce")}
						value={logoSize}
						onChange={(value) => setAttributes({ logoSize: value })}
						min={20}
						max={120}
					/>

					<RangeControl
						label={__("Spacing (px)", "blaze-commerce")}
						value={spacing}
						onChange={(value) => setAttributes({ spacing: value })}
						min={10}
						max={50}
					/>

					<Divider />

					<p>{__("Text Color", "blaze-commerce")}</p>
					<ColorPicker
						color={textColor}
						onChange={(value) => setAttributes({ textColor: value })}
					/>

					<RangeControl
						label={__("Text Size (px)", "blaze-commerce")}
						value={textSize}
						onChange={(value) => setAttributes({ textSize: value })}
						min={12}
						max={24}
					/>

					<SelectControl
						label={__("Text Transform", "blaze-commerce")}
						value={textTransform}
						options={textTransformOptions}
						onChange={(value) => setAttributes({ textTransform: value })}
					/>

					<SelectControl
						label={__("Font Weight", "blaze-commerce")}
						value={textFontWeight}
						options={fontWeightOptions}
						onChange={(value) => setAttributes({ textFontWeight: value })}
					/>

					<SelectControl
						label={__("Line Height", "blaze-commerce")}
						value={textLineHeight}
						options={lineHeightOptions}
						onChange={(value) => setAttributes({ textLineHeight: value })}
					/>

					<Divider />

					<p>{__("Trigger Color", "blaze-commerce")}</p>
					<ColorPicker
						color={triggerColor}
						onChange={(value) => setAttributes({ triggerColor: value })}
					/>

					<Divider />

					<ToggleControl
						label={__("Show Divider Between Items", "blaze-commerce")}
						checked={showDivider}
						onChange={(value) => setAttributes({ showDivider: value })}
						help={__(
							"Add a border/divider line between items",
							"blaze-commerce",
						)}
					/>

					{showDivider && (
						<>
							<p>{__("Divider Color", "blaze-commerce")}</p>
							<ColorPicker
								color={dividerColor}
								onChange={(value) => setAttributes({ dividerColor: value })}
								disableAlpha={false}
							/>
						</>
					)}
				</PanelBody>

				{items.length > 0 && selectedItem && (
					<PanelBody
						title={__("Service Feature Items", "blaze-commerce")}
						initialOpen={true}>
						<div className="service-features-item-selector">
							<SelectControl
								label={__("Select Item to Edit", "blaze-commerce")}
								value={selectedItemId}
								options={itemSelectorOptions}
								onChange={setSelectedItemId}
							/>
						</div>

						<div className="service-feature-item-settings">
							<div className="service-feature-item-header">
								<h3>
									{__("Item", "blaze-commerce")} #
									{items.findIndex((i) => i.id === selectedItem.id) + 1}
								</h3>
								<Button
									isDestructive
									onClick={() => removeItem(selectedItem.id)}
									icon="trash"
									label={__("Remove item", "blaze-commerce")}
								/>
							</div>

							<Divider />

							<div className="service-feature-logo">
								<p>{__("Logo", "blaze-commerce")}</p>
								<MediaUploadCheck>
									<MediaUpload
										onSelect={handleMediaSelect}
										allowedTypes={["image"]}
										value={selectedItem.logo.id || selectedItem.logo.url}
										render={({ open }) => (
											<div className="logo-upload-container">
												{!selectedItem.logo.url ? (
													<Button
														onClick={open}
														icon="format-image"
														className="logo-upload-button"
														style={{
															width: "100%",
															justifyContent: "center",
															height: "80px",
														}}>
														{__("Upload Logo", "blaze-commerce")}
													</Button>
												) : (
													<div className="logo-preview">
														<img
															src={selectedItem.logo.url}
															alt={selectedItem.logo.alt}
															style={{
																maxHeight: "80px",
																maxWidth: "100%",
																objectFit: "contain",
															}}
														/>
														<div className="logo-actions">
															<Button
																onClick={open}
																variant="secondary"
																isSmall>
																{__("Replace", "blaze-commerce")}
															</Button>
															<Button
																onClick={handleLogoRemove}
																isDestructive
																isSmall>
																{__("Remove", "blaze-commerce")}
															</Button>
														</div>
													</div>
												)}
											</div>
										)}
									/>
								</MediaUploadCheck>
							</div>

							<Divider />

							<div className="service-feature-text">
								<TextControl
									label={__("Text", "blaze-commerce")}
									value={selectedItem.text}
									onChange={(value) =>
										updateItem(selectedItem.id, "text", value)
									}
								/>
							</div>

							<Divider />

							<div className="service-feature-trigger">
								<SelectControl
									label={__("Trigger Type", "blaze-commerce")}
									value={selectedItem.triggerType}
									options={triggerTypeOptions}
									onChange={(value) =>
										updateItem(selectedItem.id, "triggerType", value)
									}
								/>

								{selectedItem.triggerType === "link" && (
									<TextControl
										label={__("Link URL", "blaze-commerce")}
										value={selectedItem.triggerLink}
										onChange={(value) =>
											updateItem(selectedItem.id, "triggerLink", value)
										}
									/>
								)}

								{selectedItem.triggerType === "text" && (
									<>
										<div
											style={{
												border: "1px solid #ddd",
												borderRadius: "4px",
												padding: "10px",
												minHeight: "100px",
												backgroundColor: "#fff",
											}}>
											<RichText
												value={selectedItem.triggerRichText}
												onChange={(value) =>
													updateItem(selectedItem.id, "triggerRichText", value)
												}
												placeholder={__(
													"Enter rich text content here...",
													"blaze-commerce",
												)}
												allowedFormats={[
													"core/bold",
													"core/italic",
													"core/link",
													"core/underline",
												]}
											/>
										</div>
									</>
								)}
							</div>
						</div>
					</PanelBody>
				)}
			</InspectorControls>

			<div {...blockProps}>
				<div
					className={`flex ${getDirectionClass()} ${getSpacingClass()} ${getAlignmentClass()}`}>
					{items.length === 0 ? (
						<Placeholder
							icon="admin-generic"
							label={__("Service Features", "blaze-commerce")}
							instructions={__(
								"Add service features using the sidebar controls.",
								"blaze-commerce",
							)}>
							<Button variant="primary" onClick={addItem} icon="plus">
								{__("Add Service Feature", "blaze-commerce")}
							</Button>
						</Placeholder>
					) : (
						items.map((item, index) => {
							const isSelected = selectedItemId === item.id;
							const handleItemClick = () => setSelectedItemId(item.id);

							return (
								<>
									<div
										key={item.id}
										className={`flex flex-row items-center relative cursor-pointer transition-all duration-200 ${
											itemsDirection === "horizontal"
												? "flex-1 min-w-[200px]"
												: "w-full"
										} ${
											itemsDirection === "horizontal"
												? "gap-4"
												: `gap-${spacing === 20 ? "4" : "3"}`
										}`}
										onClick={handleItemClick}>
										{item.logo.url && (
											<img
												src={item.logo.url}
												alt={item.logo.alt}
												className={`${getLogoSizeClass()} object-contain flex-shrink-0`}
											/>
										)}

										{item.text && (
											<div
												className={`m-0 ${getTextSizeClass()} ${getTextTransformClass()} ${getTextFontWeightClass()} ${getTextLineHeightClass()} ${getTriggerSizeClass()} `}
												style={{ color: textColor }}>
												{item.text}
											</div>
										)}

										<div className="absolute top-1 right-1">
											<span className="flex items-center justify-center w-6 h-6 bg-blue-500 text-white rounded-full text-xs font-bold">
												{index + 1}
											</span>
										</div>
									</div>

									{/* Divider between items */}
									{showDivider && index < items.length - 1 && (
										<div
											className={`${
												itemsDirection === "vertical"
													? "w-full h-px my-4"
													: "w-px h-[30px] mx-4"
											}`}
											style={{ backgroundColor: dividerColor }}
										/>
									)}
								</>
							);
						})
					)}
				</div>
			</div>
		</>
	);
}
