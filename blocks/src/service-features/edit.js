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
import { useState } from "@wordpress/element";

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

	// Function to add a new item
	const addItem = () => {
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
	};

	// Function to remove an item
	const removeItem = (id) => {
		const newItems = items.filter((item) => item.id !== id);
		setAttributes({
			items: newItems,
		});

		// If we removed the selected item, select the first remaining item or null if none left
		if (id === selectedItemId) {
			setSelectedItemId(newItems.length > 0 ? newItems[0].id : null);
		}
	};

	// Function to update an item
	const updateItem = (id, property, value) => {
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
	};

	// Get the currently selected item
	const selectedItem = items.find((item) => item.id === selectedItemId) || null;

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
					title={__("Service Features Settings", "blaze-commerce")}
					initialOpen={true}>
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

					<Button
						variant="primary"
						className="add-service-feature-sidebar"
						onClick={addItem}
						icon="plus"
						style={{ marginTop: "10px", width: "100%" }}>
						{__("Add Service Feature", "blaze-commerce")}
					</Button>
				</PanelBody>

				{items.length > 0 && (
					<PanelBody
						title={__("Service Feature Items", "blaze-commerce")}
						initialOpen={true}>
						<TabPanel
							className="service-features-tabs"
							activeClass="active-tab"
							tabs={items.map((item, index) => ({
								name: item.id,
								title: __("Item", "blaze-commerce") + " " + (index + 1),
								className: "service-feature-tab",
							}))}
							onSelect={(tabName) => setSelectedItemId(tabName)}>
							{(tab) => {
								const item = items.find((i) => i.id === tab.name);
								if (!item) return null;

								return (
									<div className="service-feature-item-settings">
										<div className="service-feature-item-header">
											<h3>
												{__("Item", "blaze-commerce")} #
												{items.findIndex((i) => i.id === item.id) + 1}
											</h3>
											<Button
												isDestructive
												onClick={() => removeItem(item.id)}
												icon="trash"
												label={__("Remove item", "blaze-commerce")}
											/>
										</div>

										<Divider />

										<div className="service-feature-logo">
											<p>{__("Logo", "blaze-commerce")}</p>
											<MediaUploadCheck>
												<MediaUpload
													onSelect={(media) => {
														updateItem(item.id, "logo.url", media.url);
														updateItem(item.id, "logo.alt", media.alt || "");
													}}
													allowedTypes={["image"]}
													value={item.logo.url}
													render={({ open }) => (
														<div className="logo-upload-container">
															{!item.logo.url ? (
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
																		src={item.logo.url}
																		alt={item.logo.alt}
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
																			onClick={() => {
																				updateItem(item.id, "logo.url", "");
																				updateItem(item.id, "logo.alt", "");
																			}}
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
												value={item.text}
												onChange={(value) => updateItem(item.id, "text", value)}
											/>
										</div>

										<Divider />

										<div className="service-feature-trigger">
											<SelectControl
												label={__("Trigger Type", "blaze-commerce")}
												value={item.triggerType}
												options={[
													{
														label: __("Link", "blaze-commerce"),
														value: "link",
													},
													{
														label: __("Text", "blaze-commerce"),
														value: "text",
													},
												]}
												onChange={(value) =>
													updateItem(item.id, "triggerType", value)
												}
											/>

											<TextControl
												label={__("Trigger Text", "blaze-commerce")}
												value={item.triggerText}
												onChange={(value) =>
													updateItem(item.id, "triggerText", value)
												}
											/>

											{item.triggerType === "link" && (
												<TextControl
													label={__("Link URL", "blaze-commerce")}
													value={item.triggerLink}
													onChange={(value) =>
														updateItem(item.id, "triggerLink", value)
													}
												/>
											)}

											{item.triggerType === "text" && (
												<TextControl
													label={__("Target ID", "blaze-commerce")}
													value={item.triggerTarget}
													onChange={(value) =>
														updateItem(item.id, "triggerTarget", value)
													}
													help={__(
														"ID of the element to scroll to when clicked",
														"blaze-commerce",
													)}
												/>
											)}
										</div>
									</div>
								);
							}}
						</TabPanel>
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
						items.map((item, index) => (
							<div
								className={`service-feature-item ${
									selectedItemId === item.id ? "is-selected" : ""
								}`}
								key={item.id}
								onClick={() => setSelectedItemId(item.id)}>
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
						))
					)}
				</div>
			</div>
		</>
	);
}
