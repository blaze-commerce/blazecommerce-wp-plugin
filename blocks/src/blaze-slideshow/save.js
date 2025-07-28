/**
 * WordPress dependencies
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * The save function for the Blaze Slideshow block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @return {WPElement} Element to render.
 */
export default function save({ attributes }) {
	const {
		slidesToShowDesktop,
		slidesToShowTablet,
		slidesToShowMobile,
		enableArrows,
		enableDots,
		enableAutoplay,
		autoplaySpeed,
		infinite,
		speed,
		slidesToScroll,
		arrowColor,
		dotColor,
		dotActiveColor,
		containerClass,
	} = attributes;

	const blockProps = useBlockProps.save({
		className: `blaze-slideshow ${containerClass}`,
		'data-slides-desktop': slidesToShowDesktop,
		'data-slides-tablet': slidesToShowTablet,
		'data-slides-mobile': slidesToShowMobile,
		'data-enable-arrows': enableArrows,
		'data-enable-dots': enableDots,
		'data-enable-autoplay': enableAutoplay,
		'data-autoplay-speed': autoplaySpeed,
		'data-infinite': infinite,
		'data-speed': speed,
		'data-slides-to-scroll': slidesToScroll,
		'data-arrow-color': arrowColor,
		'data-dot-color': dotColor,
		'data-dot-active-color': dotActiveColor,
	});

	return (
		<div {...blockProps}>
			<div className="blaze-slideshow-container">
				<div className="blaze-slideshow-track">
					<InnerBlocks.Content />
				</div>
			</div>
		</div>
	);
}
