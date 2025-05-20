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

		setAttributes({
			items: [...items, newItem],
		});
	};

	// Function to remove an item
	const removeItem = (id) => {
		setAttributes({
			items: items.filter((item) => item.id !== id),
		});
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
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="service-features-container">
					{items.map((item, index) => (
						<div className="service-feature-item" key={item.id}>
							<div className="service-feature-header">
								<h4>
									{__("Service Feature", "blaze-commerce")} #{index + 1}
								</h4>
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
														className="logo-upload-button">
														{__("Upload Logo", "blaze-commerce")}
													</Button>
												) : (
													<div className="logo-preview">
														<img src={item.logo.url} alt={item.logo.alt} />
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
										{ label: __("Link", "blaze-commerce"), value: "link" },
										{ label: __("Text", "blaze-commerce"), value: "text" },
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
					))}

					<Button
						variant="primary"
						className="add-service-feature"
						onClick={addItem}
						icon="plus">
						{__("Add Service Feature", "blaze-commerce")}
					</Button>
				</div>
			</div>
		</>
	);
}
