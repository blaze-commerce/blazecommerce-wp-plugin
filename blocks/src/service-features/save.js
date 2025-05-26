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
	// Generate Tailwind classes based on attributes
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
			20: "w-5 h-5",
			30: "w-8 h-8",
			40: "w-10 h-10",
			50: "w-12 h-12",
			60: "w-16 h-16",
			70: "w-18 h-18",
			80: "w-20 h-20",
			90: "w-24 h-24",
			100: "w-28 h-28",
			120: "w-32 h-32",
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

	const blockProps = useBlockProps.save({
		className: `w-full`,
		style: {
			width: "100%",
		},
	});

	return (
		<div {...blockProps}>
			<div
				className={`flex ${getDirectionClass()} ${getSpacingClass()} ${getAlignmentClass()}`}>
				{items.map((item, index) => (
					<div key={item.id}>
						<div
							className={`flex flex-row items-center p-4 border border-gray-200 rounded-lg bg-gray-50 hover:shadow-md transition-all duration-300 ${
								itemsDirection === "horizontal"
									? "flex-1 min-w-[250px]"
									: "w-full"
							}`}>
							{item.logo.url && (
								<div
									className={`flex-shrink-0 ${
										itemsDirection === "horizontal"
											? "mr-4"
											: `mr-${spacing === 20 ? "5" : "4"}`
									}`}>
									<img
										src={item.logo.url}
										alt={item.logo.alt}
										className={`${getLogoSizeClass()} object-contain`}
									/>
								</div>
							)}

							<div className="flex-1">
								{item.text && (
									<div className="mb-2">
										<p
											className={`m-0 ${getTextSizeClass()} ${getTextTransformClass()} ${getTextFontWeightClass()} ${getTextLineHeightClass()}`}
											style={{ color: textColor }}>
											{item.text}
										</p>
									</div>
								)}

								{item.triggerText && (
									<div>
										{item.triggerType === "link" ? (
											<a
												href={item.triggerLink || "#"}
												className={`${getTriggerSizeClass()} no-underline cursor-pointer hover:underline`}
												style={{ color: triggerColor }}>
												{item.triggerText}
											</a>
										) : (
											<span
												className={`${getTriggerSizeClass()} cursor-pointer hover:underline`}
												style={{ color: triggerColor }}
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

						{/* Divider between items */}
						{showDivider && index < items.length - 1 && (
							<div
								className={`${
									itemsDirection === "vertical"
										? "w-full h-px my-4"
										: "w-px h-full mx-4"
								}`}
								style={{ backgroundColor: dividerColor }}
							/>
						)}
					</div>
				))}
			</div>
		</div>
	);
}
