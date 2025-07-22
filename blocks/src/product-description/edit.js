import { __ } from '@wordpress/i18n';
import { 
	useBlockProps, 
	InspectorControls
} from '@wordpress/block-editor';
import { 
	PanelBody, 
	SelectControl,
	Placeholder,
	Spinner
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
	const { productId } = attributes;

	const [productDescription, setProductDescription] = useState('');
	const [isLoading, setIsLoading] = useState(false);
	const [products, setProducts] = useState([]);

	// Get current post ID if we're editing a product
	const currentPostId = useSelect((select) => {
		return select('core/editor')?.getCurrentPostId();
	}, []);

	// Load products for selection
	useEffect(() => {
		const loadProducts = async () => {
			try {
				const response = await apiFetch({
					path: '/wp/v2/product?per_page=100&status=publish',
				});
				setProducts(response);
			} catch (error) {
				console.error('Error loading products:', error);
				// Fallback: try to get products from WooCommerce REST API
				try {
					const wcResponse = await apiFetch({
						path: '/wc/v3/products?per_page=100&status=publish',
					});
					setProducts(wcResponse);
				} catch (wcError) {
					console.error('Error loading products from WC API:', wcError);
				}
			}
		};

		loadProducts();
	}, []);

	// Load product description when productId changes
	useEffect(() => {
		const loadProductDescription = async () => {
			if (!productId) {
				setProductDescription('');
				return;
			}

			setIsLoading(true);
			try {
				const response = await apiFetch({
					path: `/wp/v2/product/${productId}`,
				});
				setProductDescription(response.content?.rendered || "");
			} catch (error) {
				console.error("Error loading product description:", error);
				// Fallback: try WooCommerce API
				try {
					const wcResponse = await apiFetch({
						path: `/wc/v3/products/${productId}`,
					});
					setProductDescription(wcResponse.description || "");
				} catch (wcError) {
					console.error("Error loading product description from WC API:", wcError);
					setProductDescription("");
				}
			} finally {
				setIsLoading(false);
			}
		};

		loadProductDescription();
	}, [productId]);

	// Auto-select current product if we're editing a product post
	useEffect(() => {
		if (currentPostId && !productId && products.length > 0) {
			const currentProduct = products.find(product => product.id === currentPostId);
			if (currentProduct) {
				setAttributes({ productId: currentPostId });
			}
		}
	}, [currentPostId, productId, products, setAttributes]);

	const blockProps = useBlockProps();

	const productOptions = [
		{ label: __('Select a product', 'blaze-commerce'), value: 0 },
		...products.map(product => ({
			label: product.name || product.title?.rendered,
			value: product.id
		}))
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Product Selection', 'blaze-commerce')} initialOpen={true}>
					<SelectControl
						label={__('Select Product', 'blaze-commerce')}
						value={productId}
						options={productOptions}
						onChange={(value) => setAttributes({ productId: parseInt(value) })}
						help={__('Choose which product description to display', 'blaze-commerce')}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{!productId ? (
					<Placeholder
						icon="text"
						label={__('Product Description', 'blaze-commerce')}
						instructions={__('Select a product from the sidebar to display its description.', 'blaze-commerce')}
					/>
				) : isLoading ? (
					<Placeholder
						icon="text"
						label={__('Product Description', 'blaze-commerce')}>
						<Spinner />
					</Placeholder>
				) : (
					<div className="blaze-product-description">
						{productDescription ? (
							<div 
								dangerouslySetInnerHTML={{ __html: productDescription }}
							/>
						) : (
							<p style={{ color: '#999', fontStyle: 'italic' }}>
								{__('No description available for this product.', 'blaze-commerce')}
							</p>
						)}
					</div>
				)}
			</div>
		</>
	);
}
