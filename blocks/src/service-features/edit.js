/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	BlockControls,
	AlignmentToolbar,
} from "@wordpress/block-editor";
import {
	PanelBody,
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Placeholder,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalDivider as Divider,
	TabPanel,
	Icon,
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
	const { items, align } = attributes;
	const blockProps = useBlockProps({
		className: `service-features align-${align}`,
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
				url: "",
				alt: "",
			},
			text: "Service Feature",
			triggerType: "link",
			triggerText: "Learn More",
			triggerLink: "#",
			triggerTarget: "",
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

	// Memoized alignment change handler
	const handleAlignmentChange = useCallback(
		(newAlign) => {
			setAttributes({ align: newAlign });
		},
		[setAttributes],
	);

	// Memoized selected item
	const selectedItem = useMemo(() => {
		return items.find((item) => item.id === selectedItemId) || null;
	}, [items, selectedItemId]);

	// Memoized tabs for TabPanel
	const tabs = useMemo(() => {
		return items.map((item, index) => ({
			name: item.id,
			title: __("Item", "blaze-commerce") + " " + (index + 1),
			className: "service-feature-tab",
		}));
	}, [items]);

	// Memoized alignment options
	const alignmentOptions = useMemo(
		() => [
			{ label: __("Left", "blaze-commerce"), value: "left" },
			{ label: __("Center", "blaze-commerce"), value: "center" },
			{ label: __("Right", "blaze-commerce"), value: "right" },
		],
		[],
	);

	// Memoized trigger type options
	const triggerTypeOptions = useMemo(
		() => [
			{ label: __("Link", "blaze-commerce"), value: "link" },
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
			if (selectedItem) {
				updateItem(selectedItem.id, "logo.url", media.url);
				updateItem(selectedItem.id, "logo.alt", media.alt || "");
			}
		},
		[selectedItem, updateItem],
	);

	const handleLogoRemove = useCallback(() => {
		if (selectedItem) {
			updateItem(selectedItem.id, "logo.url", "");
			updateItem(selectedItem.id, "logo.alt", "");
		}
	}, [selectedItem, updateItem]);

	return (
		<>
			<BlockControls>
				<AlignmentToolbar value={align} onChange={handleAlignmentChange} />
			</BlockControls>
			<InspectorControls>
				<PanelBody
					title={__("Service Features Settings", "blaze-commerce")}
					initialOpen={true}>
					<SelectControl
						label={__("Alignment", "blaze-commerce")}
						value={align}
						options={alignmentOptions}
						onChange={handleAlignmentChange}
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
										value={selectedItem.logo.url}
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

								<TextControl
									label={__("Trigger Text", "blaze-commerce")}
									value={selectedItem.triggerText}
									onChange={(value) =>
										updateItem(selectedItem.id, "triggerText", value)
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
									<TextControl
										label={__("Target ID", "blaze-commerce")}
										value={selectedItem.triggerTarget}
										onChange={(value) =>
											updateItem(selectedItem.id, "triggerTarget", value)
										}
										help={__(
											"ID of the element to scroll to when clicked",
											"blaze-commerce",
										)}
									/>
								)}
							</div>
						</div>
					</PanelBody>
				)}
			</InspectorControls>

			<div {...blockProps}>
				<div className="service-features-container">
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
								<div
									className={`service-feature-item ${
										isSelected ? "is-selected" : ""
									}`}
									key={item.id}
									onClick={handleItemClick}>
									{item.logo.url && (
										<div className="service-feature-logo">
											<img src={item.logo.url} alt={item.logo.alt} />
										</div>
									)}

									{item.text && (
										<div className="service-feature-text">
											<p>{item.text}</p>
										</div>
									)}

									{item.triggerText && (
										<div className="service-feature-trigger">
											{item.triggerType === "link" ? (
												<span className="service-feature-link">
													{item.triggerText}
												</span>
											) : (
												<span className="service-feature-target">
													{item.triggerText}
												</span>
											)}
										</div>
									)}

									<div className="service-feature-item-overlay">
										<span className="service-feature-item-number">
											{index + 1}
										</span>
									</div>
								</div>
							);
						})
					)}
				</div>
			</div>
		</>
	);
}
