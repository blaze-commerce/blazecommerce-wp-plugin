/**
 * WordPress dependencies
 */
import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";

/**
 * The save function for the Service Features block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({ attributes }) {
	const {
		items,
		align,
		itemsDirection,
		containerClass,
		itemClass,
		showDivider,
		iconWidth,
		iconHeight,
		logoClass,
		dividerClass,
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

	const blockProps = useBlockProps.save({
		className: `w-full`,
		style: {
			width: "100%",
		},
	});

	return (
		<div {...blockProps}>
			<div
				className={`flex ${getDirectionClass()} ${getAlignmentClass()} ${containerClass}`}>
				{items.map((item, index) => (
					<div key={item.id}>
						<div className={`flex flex-row items-center ${itemClass}`}>
							{item.logo.url && (
								<div className="flex-shrink-0">
									<img
										src={item.logo.url}
										alt={item.logo.alt}
										style={{
											width: `${iconWidth}px`,
											height: `${iconHeight}px`,
										}}
										className={`${logoClass} object-contain`}
									/>
								</div>
							)}

							<div className="flex-1">
								{item.text && (
									<div>
										<p className="m-0">{item.text}</p>
									</div>
								)}
							</div>
						</div>

						{/* Divider between items */}
						{showDivider && index < items.length - 1 && (
							<div
								className={`${
									itemsDirection === "vertical"
										? "w-full h-px my-4"
										: "w-px h-full mx-4"
								} ${dividerClass}`}
							/>
						)}
					</div>
				))}
			</div>
		</div>
	);
}
