/**
 * WordPress dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function for the Kajal Collection Menu block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({ attributes }) {
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

	const blockProps = useBlockProps.save({
		className: `kajal-collection-menu w-full`,
	});

	return (
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
					{menuItems.map((item) => {
						const linkUrl = item.linkType === "anchor" ? `#${item.link}` : item.link;
						
						return (
							<a
								key={item.id}
								href={linkUrl}
								target={item.target}
								rel={item.target === "_blank" ? "noopener noreferrer" : undefined}
								className={`kajal-menu-item block ${menuItemClass}`}>
								<span className="kajal-menu-item-text">
									{item.text}
								</span>
							</a>
						);
					})}
				</div>
			</div>
		</div>
	);
}
