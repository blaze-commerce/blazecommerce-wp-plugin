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
		textColor,
		textSize,
		triggerColor,
		triggerSize,
		logoSize,
		spacing,
	} = attributes;
	const blockProps = useBlockProps.save({
		className: `service-features align-${align} items-${itemsDirection}`,
		style: {
			"--text-color": textColor,
			"--text-size": `${textSize}px`,
			"--trigger-color": triggerColor,
			"--trigger-size": `${triggerSize}px`,
			"--logo-size": `${logoSize}px`,
			"--spacing": `${spacing}px`,
		},
	});

	return (
		<div {...blockProps}>
			<div className="service-features-container">
				{items.map((item) => (
					<div className="service-feature-item" key={item.id}>
						{item.logo.url && (
							<div className="service-feature-logo">
								<img src={item.logo.url} alt={item.logo.alt} />
							</div>
						)}

						<div className="service-feature-content">
							{item.text && (
								<div className="service-feature-text">
									<p>{item.text}</p>
								</div>
							)}

							{item.triggerText && (
								<div className="service-feature-trigger">
									{item.triggerType === "link" ? (
										<a
											href={item.triggerLink || "#"}
											className="service-feature-link">
											{item.triggerText}
										</a>
									) : (
										<span
											className="service-feature-target"
											data-target={item.triggerTarget}
											onClick={() => {
												const target = document.getElementById(
													item.triggerTarget,
												);
												if (target) {
													target.scrollIntoView({ behavior: "smooth" });
												}
											}}>
											{item.triggerText}
										</span>
									)}
								</div>
							)}
						</div>
					</div>
				))}
			</div>
		</div>
	);
}
