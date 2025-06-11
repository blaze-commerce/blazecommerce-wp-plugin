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
	SelectControl,
	ToggleControl,
	RangeControl,
	Placeholder,
	__experimentalDivider as Divider,
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
		containerClass,
		itemClass,
		logoClass,
		showDivider,
		dividerClass,
		iconWidth,
		iconHeight,
	} = attributes;
	// Generate Tailwind classes based on attributes
	const getAlignmentClass = () => {
		switch (align) {
			case "center":
				return itemsDirection === "horizontal"
					? "justify-center"
					: "items-center text-center";
			case "right":
				return itemsDirection === "horizontal"
					? "justify-end"
					: "items-end text-right";
			default:
				return itemsDirection === "horizontal"
					? "justify-start"
					: "items-start text-left";
		}
	};

	const getDirectionClass = () => {
		return itemsDirection === "vertical" ? "flex-col" : "flex-row flex-wrap";
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
					<TextControl
						label={__("Container CSS Classes", "blaze-commerce")}
						value={containerClass}
						onChange={(value) => setAttributes({ containerClass: value })}
						help={__(
							"Add Tailwind CSS classes for the main container",
							"blaze-commerce",
						)}
						placeholder="flex gap-4 p-4"
					/>

					<TextControl
						label={__("Item CSS Classes", "blaze-commerce")}
						value={itemClass}
						onChange={(value) => setAttributes({ itemClass: value })}
						help={__(
							"Add Tailwind CSS classes for each item container",
							"blaze-commerce",
						)}
						placeholder="bg-white rounded-lg shadow-md p-6"
					/>

					<TextControl
						label={__("Logo CSS Classes", "blaze-commerce")}
						value={logoClass}
						onChange={(value) => setAttributes({ logoClass: value })}
						help={__(
							"Add Tailwind CSS classes for each logo",
							"blaze-commerce",
						)}
						placeholder="bg-white rounded-lg shadow-md p-6"
					/>

					<RangeControl
						label={__("Icon Width (px)", "blaze-commerce")}
						value={iconWidth}
						onChange={(value) => setAttributes({ iconWidth: value })}
						min={16}
						max={128}
					/>

					<RangeControl
						label={__("Icon Height (px)", "blaze-commerce")}
						value={iconHeight}
						onChange={(value) => setAttributes({ iconHeight: value })}
						min={16}
						max={128}
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
							<TextControl
								label={__("Divider CSS Classes", "blaze-commerce")}
								value={dividerClass}
								onChange={(value) => setAttributes({ dividerClass: value })}
								help={__(
									"Add Tailwind CSS classes for divider",
									"blaze-commerce",
								)}
								placeholder="h-px bg-gray-300"
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
																size="small">
																{__("Replace", "blaze-commerce")}
															</Button>
															<Button
																onClick={handleLogoRemove}
																isDestructive
																size="small">
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
					className={`flex ${getDirectionClass()} ${getAlignmentClass()} ${containerClass}`}>
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
							const handleItemClick = () => setSelectedItemId(item.id);

							return (
								<>
									<div
										key={item.id}
										className={`flex flex-row items-center relative cursor-pointer pb-8 ${itemClass}`}
										onClick={handleItemClick}>
										{item.logo.url && (
											<img
												src={item.logo.url}
												alt={item.logo.alt}
												className={`${logoClass} object-contain flex-shrink-0`}
												style={{
													width: `${iconWidth}px`,
													height: `${iconHeight}px`,
												}}
											/>
										)}

										{item.text && <div className="m-0">{item.text}</div>}

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
											} ${dividerClass}`}
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
