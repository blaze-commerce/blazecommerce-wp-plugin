/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
} from "@wordpress/block-editor";
import {
	PanelBody,
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Placeholder,
	__experimentalDivider as Divider,
} from "@wordpress/components";
import { useState, useCallback, useMemo } from "@wordpress/element";

/**
 * Internal dependencies
 */
import "./editor.scss";

/**
 * The edit function for the Kajal Collection Menu block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
	const {
		title,
		titleClass,
		showUnderline,
		underlineClass,
		showBadge,
		badgeText,
		badgeClass,
		containerClass,
		menuItemClass,
		menuItems,
	} = attributes;

	const blockProps = useBlockProps({
		className: `kajal-collection-menu w-full`,
	});

	// State to track the currently selected item for editing in the sidebar
	const [selectedItemId, setSelectedItemId] = useState(
		menuItems.length > 0 ? menuItems[0].id : null,
	);

	// Memoized function to add a new menu item
	const addMenuItem = useCallback(() => {
		const newItem = {
			id: `item-${Date.now()}`,
			text: "New Menu Item",
			link: "#",
			linkType: "url",
			target: "_self",
		};

		const newItems = [...menuItems, newItem];
		setAttributes({
			menuItems: newItems,
		});

		// Select the newly added item
		setSelectedItemId(newItem.id);
	}, [menuItems, setAttributes]);

	// Memoized function to remove a menu item
	const removeMenuItem = useCallback(
		(id) => {
			const newItems = menuItems.filter((item) => item.id !== id);
			setAttributes({
				menuItems: newItems,
			});

			// If we removed the selected item, select the first remaining item or null if none left
			if (id === selectedItemId) {
				setSelectedItemId(newItems.length > 0 ? newItems[0].id : null);
			}
		},
		[menuItems, selectedItemId, setAttributes],
	);

	// Memoized function to update a menu item
	const updateMenuItem = useCallback(
		(id, property, value) => {
			setAttributes({
				menuItems: menuItems.map((item) => {
					if (item.id === id) {
						return {
							...item,
							[property]: value,
						};
					}
					return item;
				}),
			});
		},
		[menuItems, setAttributes],
	);

	// Memoized selected item
	const selectedItem = useMemo(() => {
		return menuItems.find((item) => item.id === selectedItemId) || null;
	}, [menuItems, selectedItemId]);

	// Memoized link type options
	const linkTypeOptions = useMemo(
		() => [
			{ label: __("URL", "blaze-commerce"), value: "url" },
			{ label: __("Anchor", "blaze-commerce"), value: "anchor" },
		],
		[],
	);

	// Memoized target options
	const targetOptions = useMemo(
		() => [
			{ label: __("Same Window", "blaze-commerce"), value: "_self" },
			{ label: __("New Window", "blaze-commerce"), value: "_blank" },
		],
		[],
	);

	// Memoized item selector options
	const itemSelectorOptions = useMemo(() => {
		return menuItems.map((item, index) => ({
			label:
				__("Item", "blaze-commerce") +
				" " +
				(index + 1) +
				(item.text ? ` - ${item.text}` : ""),
			value: item.id,
		}));
	}, [menuItems]);

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__("Menu Settings", "blaze-commerce")}
					initialOpen={true}>
					<TextControl
						label={__("Title", "blaze-commerce")}
						value={title}
						onChange={(value) => setAttributes({ title: value })}
						help={__("The main title of the menu", "blaze-commerce")}
					/>

					<ToggleControl
						label={__("Show Underline", "blaze-commerce")}
						checked={showUnderline}
						onChange={(value) => setAttributes({ showUnderline: value })}
						help={__("Show decorative underline below title", "blaze-commerce")}
					/>

					<Divider />

					<ToggleControl
						label={__("Show Badge", "blaze-commerce")}
						checked={showBadge}
						onChange={(value) => setAttributes({ showBadge: value })}
						help={__("Show badge below title", "blaze-commerce")}
					/>

					{showBadge && (
						<TextControl
							label={__("Badge Text", "blaze-commerce")}
							value={badgeText}
							onChange={(value) => setAttributes({ badgeText: value })}
							help={__("Text to display in the badge", "blaze-commerce")}
						/>
					)}

					<Divider />

					<Button
						variant="primary"
						className="add-menu-item-sidebar"
						onClick={addMenuItem}
						icon="plus"
						style={{ marginTop: "10px", width: "100%" }}>
						{__("Add Menu Item", "blaze-commerce")}
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
						placeholder="p-6 bg-white"
					/>

					<TextControl
						label={__("Title CSS Classes", "blaze-commerce")}
						value={titleClass}
						onChange={(value) => setAttributes({ titleClass: value })}
						help={__(
							"Add Tailwind CSS classes for the title",
							"blaze-commerce",
						)}
						placeholder="text-2xl font-bold text-gray-800"
					/>

					{showUnderline && (
						<TextControl
							label={__("Underline CSS Classes", "blaze-commerce")}
							value={underlineClass}
							onChange={(value) => setAttributes({ underlineClass: value })}
							help={__(
								"Add Tailwind CSS classes for the underline",
								"blaze-commerce",
							)}
							placeholder="border-b-2 border-yellow-500"
						/>
					)}

					{showBadge && (
						<TextControl
							label={__("Badge CSS Classes", "blaze-commerce")}
							value={badgeClass}
							onChange={(value) => setAttributes({ badgeClass: value })}
							help={__(
								"Add Tailwind CSS classes for the badge",
								"blaze-commerce",
							)}
							placeholder="border border-yellow-500 text-yellow-600 px-4 py-2 rounded-full"
						/>
					)}

					<TextControl
						label={__("Menu Item CSS Classes", "blaze-commerce")}
						value={menuItemClass}
						onChange={(value) => setAttributes({ menuItemClass: value })}
						help={__(
							"Add Tailwind CSS classes for each menu item",
							"blaze-commerce",
						)}
						placeholder="bg-gray-100 hover:bg-gray-200 px-4 py-3 rounded-lg"
					/>
				</PanelBody>

				{menuItems.length > 0 && selectedItem && (
					<PanelBody
						title={__("Menu Items", "blaze-commerce")}
						initialOpen={true}>
						<div className="menu-item-selector">
							<SelectControl
								label={__("Select Item to Edit", "blaze-commerce")}
								value={selectedItemId}
								options={itemSelectorOptions}
								onChange={setSelectedItemId}
							/>
						</div>

						<div className="menu-item-settings">
							<div className="menu-item-header">
								<h3>
									{__("Item", "blaze-commerce")} #
									{menuItems.findIndex((i) => i.id === selectedItem.id) + 1}
								</h3>
								<Button
									isDestructive
									onClick={() => removeMenuItem(selectedItem.id)}
									icon="trash"
									label={__("Remove item", "blaze-commerce")}
								/>
							</div>

							<Divider />

							<TextControl
								label={__("Text", "blaze-commerce")}
								value={selectedItem.text}
								onChange={(value) =>
									updateMenuItem(selectedItem.id, "text", value)
								}
								help={__("The text to display for this menu item", "blaze-commerce")}
							/>

							<SelectControl
								label={__("Link Type", "blaze-commerce")}
								value={selectedItem.linkType}
								options={linkTypeOptions}
								onChange={(value) =>
									updateMenuItem(selectedItem.id, "linkType", value)
								}
								help={__("Whether this is a URL or anchor link", "blaze-commerce")}
							/>

							<TextControl
								label={selectedItem.linkType === "anchor" ? __("Anchor", "blaze-commerce") : __("URL", "blaze-commerce")}
								value={selectedItem.link}
								onChange={(value) =>
									updateMenuItem(selectedItem.id, "link", value)
								}
								help={selectedItem.linkType === "anchor" 
									? __("Enter anchor without # (e.g., section-1)", "blaze-commerce")
									: __("Enter the URL for this menu item", "blaze-commerce")
								}
								placeholder={selectedItem.linkType === "anchor" ? "section-1" : "https://example.com"}
							/>

							<SelectControl
								label={__("Target", "blaze-commerce")}
								value={selectedItem.target}
								options={targetOptions}
								onChange={(value) =>
									updateMenuItem(selectedItem.id, "target", value)
								}
								help={__("How the link should open", "blaze-commerce")}
							/>
						</div>
					</PanelBody>
				)}
			</InspectorControls>

			<div {...blockProps}>
				<div className={`kajal-collection-menu-container ${containerClass}`}>
					{/* Title */}
					<div className="kajal-menu-title-section">
						<h2 className={`kajal-menu-title ${titleClass}`}>
							{title}
						</h2>
						{showUnderline && (
							<div className={`kajal-menu-underline ${underlineClass}`}></div>
						)}
					</div>

					{/* Badge */}
					{showBadge && badgeText && (
						<div className={`kajal-menu-badge ${badgeClass}`}>
							{badgeText}
						</div>
					)}

					{/* Menu Items */}
					<div className="kajal-menu-items">
						{menuItems.length === 0 ? (
							<Placeholder
								icon="menu"
								label={__("Kajal Collection Menu", "blaze-commerce")}
								instructions={__(
									"Add menu items using the sidebar controls.",
									"blaze-commerce",
								)}>
								<Button variant="primary" onClick={addMenuItem} icon="plus">
									{__("Add Menu Item", "blaze-commerce")}
								</Button>
							</Placeholder>
						) : (
							menuItems.map((item, index) => {
								const handleItemClick = () => setSelectedItemId(item.id);

								return (
									<div
										key={item.id}
										className={`kajal-menu-item relative cursor-pointer ${menuItemClass}`}
										onClick={handleItemClick}>
										<span className="kajal-menu-item-text">
											{item.text}
										</span>
										
										<div className="absolute top-1 right-1">
											<span className="flex items-center justify-center w-6 h-6 bg-blue-500 text-white rounded-full text-xs font-bold">
												{index + 1}
											</span>
										</div>
									</div>
								);
							})
						)}
					</div>
				</div>
			</div>
		</>
	);
}
