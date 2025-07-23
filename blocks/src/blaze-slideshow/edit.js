/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	ColorPicker,
	TextControl,
	__experimentalDivider as Divider,
} from '@wordpress/components';
import { Fragment } from '@wordpress/element';

/**
 * The edit function for the Blaze Slideshow block.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {WPElement} Element to render.
 */
export default function Edit({ attributes, setAttributes }) {
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

	const blockProps = useBlockProps({
		className: `blaze-slideshow-editor ${containerClass}`,
	});

	const ALLOWED_BLOCKS = [
		'core/group',
		'core/column',
		'core/columns',
		'core/image',
		'core/heading',
		'core/paragraph',
		'core/button',
		'core/buttons',
		'blaze-commerce/service-features',
		'blaze-commerce/product-description',
		'blaze-commerce/product-detail',
		'blaze-commerce/stock-status',
	];

	const TEMPLATE = [
		['core/group', {
			style: {
				spacing: { padding: '2rem' },
				color: { background: '#f8f9fa' }
			}
		}, [
			['core/heading', { 
				content: __('Slide 1', 'blaze-commerce'),
				level: 3,
				textAlign: 'center'
			}],
			['core/paragraph', { 
				content: __('Add your content here...', 'blaze-commerce'),
				align: 'center'
			}]
		]],
		['core/group', {
			style: {
				spacing: { padding: '2rem' },
				color: { background: '#e9ecef' }
			}
		}, [
			['core/heading', { 
				content: __('Slide 2', 'blaze-commerce'),
				level: 3,
				textAlign: 'center'
			}],
			['core/paragraph', { 
				content: __('Add your content here...', 'blaze-commerce'),
				align: 'center'
			}]
		]],
		['core/group', {
			style: {
				spacing: { padding: '2rem' },
				color: { background: '#dee2e6' }
			}
		}, [
			['core/heading', { 
				content: __('Slide 3', 'blaze-commerce'),
				level: 3,
				textAlign: 'center'
			}],
			['core/paragraph', { 
				content: __('Add your content here...', 'blaze-commerce'),
				align: 'center'
			}]
		]]
	];

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Slideshow Settings', 'blaze-commerce')} initialOpen={true}>
					<RangeControl
						label={__('Slides to Show (Desktop)', 'blaze-commerce')}
						value={slidesToShowDesktop}
						onChange={(value) => setAttributes({ slidesToShowDesktop: value })}
						min={1}
						max={6}
						help={__('Number of slides visible on desktop screens', 'blaze-commerce')}
					/>
					
					<RangeControl
						label={__('Slides to Show (Tablet)', 'blaze-commerce')}
						value={slidesToShowTablet}
						onChange={(value) => setAttributes({ slidesToShowTablet: value })}
						min={1}
						max={4}
						help={__('Number of slides visible on tablet screens', 'blaze-commerce')}
					/>
					
					<RangeControl
						label={__('Slides to Show (Mobile)', 'blaze-commerce')}
						value={slidesToShowMobile}
						onChange={(value) => setAttributes({ slidesToShowMobile: value })}
						min={1}
						max={2}
						help={__('Number of slides visible on mobile screens', 'blaze-commerce')}
					/>

					<Divider />

					<ToggleControl
						label={__('Enable Arrow Navigation', 'blaze-commerce')}
						checked={enableArrows}
						onChange={(value) => setAttributes({ enableArrows: value })}
						help={__('Show previous/next arrow buttons', 'blaze-commerce')}
					/>

					<ToggleControl
						label={__('Enable Dot Navigation', 'blaze-commerce')}
						checked={enableDots}
						onChange={(value) => setAttributes({ enableDots: value })}
						help={__('Show dot indicators below slideshow', 'blaze-commerce')}
					/>

					<ToggleControl
						label={__('Enable Autoplay', 'blaze-commerce')}
						checked={enableAutoplay}
						onChange={(value) => setAttributes({ enableAutoplay: value })}
						help={__('Automatically advance slides', 'blaze-commerce')}
					/>

					{enableAutoplay && (
						<RangeControl
							label={__('Autoplay Speed (ms)', 'blaze-commerce')}
							value={autoplaySpeed}
							onChange={(value) => setAttributes({ autoplaySpeed: value })}
							min={1000}
							max={10000}
							step={500}
							help={__('Time between slide transitions in milliseconds', 'blaze-commerce')}
						/>
					)}
				</PanelBody>

				<PanelBody title={__('Advanced Settings', 'blaze-commerce')} initialOpen={false}>
					<ToggleControl
						label={__('Infinite Loop', 'blaze-commerce')}
						checked={infinite}
						onChange={(value) => setAttributes({ infinite: value })}
						help={__('Enable infinite looping of slides', 'blaze-commerce')}
					/>

					<RangeControl
						label={__('Transition Speed (ms)', 'blaze-commerce')}
						value={speed}
						onChange={(value) => setAttributes({ speed: value })}
						min={100}
						max={2000}
						step={100}
						help={__('Speed of slide transitions in milliseconds', 'blaze-commerce')}
					/>

					<RangeControl
						label={__('Slides to Scroll', 'blaze-commerce')}
						value={slidesToScroll}
						onChange={(value) => setAttributes({ slidesToScroll: value })}
						min={1}
						max={3}
						help={__('Number of slides to scroll at once', 'blaze-commerce')}
					/>

					<Divider />

					<TextControl
						label={__('Container CSS Classes', 'blaze-commerce')}
						value={containerClass}
						onChange={(value) => setAttributes({ containerClass: value })}
						help={__('Additional CSS classes for the slideshow container', 'blaze-commerce')}
					/>
				</PanelBody>

				<PanelBody title={__('Style Settings', 'blaze-commerce')} initialOpen={false}>
					{enableArrows && (
						<div style={{ marginBottom: '20px' }}>
							<label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
								{__('Arrow Color', 'blaze-commerce')}
							</label>
							<ColorPicker
								color={arrowColor}
								onChange={(value) => setAttributes({ arrowColor: value })}
							/>
						</div>
					)}

					{enableDots && (
						<Fragment>
							<div style={{ marginBottom: '20px' }}>
								<label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
									{__('Dot Color', 'blaze-commerce')}
								</label>
								<ColorPicker
									color={dotColor}
									onChange={(value) => setAttributes({ dotColor: value })}
								/>
							</div>

							<div style={{ marginBottom: '20px' }}>
								<label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
									{__('Active Dot Color', 'blaze-commerce')}
								</label>
								<ColorPicker
									color={dotActiveColor}
									onChange={(value) => setAttributes({ dotActiveColor: value })}
								/>
							</div>
						</Fragment>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="blaze-slideshow-preview">
					<div className="slideshow-info" style={{
						background: '#f0f0f0',
						padding: '10px',
						marginBottom: '20px',
						borderRadius: '4px',
						fontSize: '14px'
					}}>
						<strong>{__('Blaze Slideshow Preview', 'blaze-commerce')}</strong>
						<br />
						{__('Desktop:', 'blaze-commerce')} {slidesToShowDesktop} {__('slides', 'blaze-commerce')} | 
						{__('Tablet:', 'blaze-commerce')} {slidesToShowTablet} {__('slides', 'blaze-commerce')} | 
						{__('Mobile:', 'blaze-commerce')} {slidesToShowMobile} {__('slide(s)', 'blaze-commerce')}
						<br />
						{__('Arrows:', 'blaze-commerce')} {enableArrows ? __('Yes', 'blaze-commerce') : __('No', 'blaze-commerce')} | 
						{__('Dots:', 'blaze-commerce')} {enableDots ? __('Yes', 'blaze-commerce') : __('No', 'blaze-commerce')} | 
						{__('Autoplay:', 'blaze-commerce')} {enableAutoplay ? __('Yes', 'blaze-commerce') : __('No', 'blaze-commerce')}
					</div>

					<div className="slideshow-container" style={{
						border: '2px dashed #ccc',
						borderRadius: '8px',
						padding: '20px',
						minHeight: '200px'
					}}>
						<InnerBlocks
							allowedBlocks={ALLOWED_BLOCKS}
							template={TEMPLATE}
							templateLock={false}
							renderAppender={InnerBlocks.ButtonBlockAppender}
						/>
					</div>

					{enableDots && (
						<div className="slideshow-dots-preview" style={{
							textAlign: 'center',
							marginTop: '15px'
						}}>
							<span style={{
								display: 'inline-block',
								width: '12px',
								height: '12px',
								borderRadius: '50%',
								backgroundColor: dotActiveColor,
								margin: '0 4px'
							}}></span>
							<span style={{
								display: 'inline-block',
								width: '12px',
								height: '12px',
								borderRadius: '50%',
								backgroundColor: dotColor,
								margin: '0 4px'
							}}></span>
							<span style={{
								display: 'inline-block',
								width: '12px',
								height: '12px',
								borderRadius: '50%',
								backgroundColor: dotColor,
								margin: '0 4px'
							}}></span>
						</div>
					)}
				</div>
			</div>
		</Fragment>
	);
}
