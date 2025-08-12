(function() {
    'use strict';

    // Debug logging
    console.log('BlazeCommerce: Loading global block config script');

    // Ensure WordPress dependencies are available
    if (typeof wp === 'undefined') {
        console.error('BlazeCommerce: WordPress (wp) object not available');
        return;
    }

    // Check individual dependencies
    const requiredDeps = ['hooks', 'element', 'components', 'blockEditor', 'compose', 'data'];
    const missingDeps = requiredDeps.filter(dep => !wp[dep]);

    if (missingDeps.length > 0) {
        console.error('BlazeCommerce: Missing WordPress dependencies:', missingDeps);
        return;
    }

    console.log('BlazeCommerce: All dependencies available');

    const { addFilter } = wp.hooks;
    const { createElement, Fragment, useState, useEffect } = wp.element;
    const { InspectorAdvancedControls } = wp.blockEditor;
    const { CheckboxControl, Spinner } = wp.components;
    const { createHigherOrderComponent } = wp.compose;
    const { useSelect } = wp.data;

    // Global regions cache to prevent multiple API calls
    let regionsCache = {
        data: null,
        loading: false,
        error: null,
        promise: null
    };

    /**
     * Add region attribute to all blocks
     */
    function addRegionAttribute(settings, name) {
        console.log('BlazeCommerce: Adding attribute to block:', name);

        // Skip core blocks that shouldn't have region settings
        const skipBlocks = [
            'core/freeform',
            'core/html',
            'core/shortcode'
        ];

        if (skipBlocks.includes(name)) {
            console.log('BlazeCommerce: Skipping attribute for block:', name);
            return settings;
        }

        // Add region attribute to block settings
        if (typeof settings.attributes !== 'object') {
            settings.attributes = {};
        }

        settings.attributes.blazeCommerceRegions = {
            type: 'array',
            default: []
        };

        console.log('BlazeCommerce: Added region attribute to:', name);
        return settings;
    }

    /**
     * Fetch available regions from backend with caching
     */
    function fetchAvailableRegions() {
        // Return cached data if available
        if (regionsCache.data !== null) {
            console.log('BlazeCommerce: Using cached regions data');
            return Promise.resolve(regionsCache.data);
        }

        // Return existing promise if already loading
        if (regionsCache.loading && regionsCache.promise) {
            console.log('BlazeCommerce: Using existing regions request');
            return regionsCache.promise;
        }

        // Start new request
        console.log('BlazeCommerce: Fetching regions from API');
        regionsCache.loading = true;
        regionsCache.error = null;

        regionsCache.promise = wp.apiFetch({
            path: '/wp/v2/blaze-commerce/regions',
            method: 'GET'
        }).then(data => {
            console.log('BlazeCommerce: Regions fetched successfully:', data);
            regionsCache.data = data;
            regionsCache.loading = false;
            regionsCache.error = null;
            return data;
        }).catch(error => {
            console.error('BlazeCommerce: Failed to fetch regions:', error);
            regionsCache.loading = false;
            regionsCache.error = error;
            regionsCache.data = [];
            return [];
        });

        return regionsCache.promise;
    }

    /**
     * Clear regions cache (useful for testing or when regions change)
     */
    function clearRegionsCache() {
        console.log('BlazeCommerce: Clearing regions cache');
        regionsCache.data = null;
        regionsCache.loading = false;
        regionsCache.error = null;
        regionsCache.promise = null;
    }

    // Expose cache clearing function globally for debugging
    if (typeof window !== 'undefined') {
        window.blazeCommerceClearRegionsCache = clearRegionsCache;
    }

    /**
     * Add region control to Advanced panel
     */
    const withRegionControl = createHigherOrderComponent((BlockEdit) => {
        return (props) => {
            const { attributes, setAttributes, name } = props;
            const [availableRegions, setAvailableRegions] = useState(regionsCache.data || []);
            const [isLoading, setIsLoading] = useState(regionsCache.data === null);

            console.log('BlazeCommerce: Processing block:', name);

            // Skip core blocks that shouldn't have region settings
            const skipBlocks = [
                'core/freeform',
                'core/html',
                'core/shortcode'
            ];

            if (skipBlocks.includes(name)) {
                console.log('BlazeCommerce: Skipping block:', name);
                return createElement(BlockEdit, props);
            }

            // Fetch regions on component mount only if not already cached
            useEffect(() => {
                if (regionsCache.data === null && !regionsCache.loading) {
                    fetchAvailableRegions().then(regions => {
                        setAvailableRegions(regions);
                        setIsLoading(false);
                    });
                } else if (regionsCache.data !== null) {
                    // Use cached data immediately
                    setAvailableRegions(regionsCache.data);
                    setIsLoading(false);
                } else if (regionsCache.loading) {
                    // Wait for existing request to complete
                    regionsCache.promise.then(regions => {
                        setAvailableRegions(regions);
                        setIsLoading(false);
                    });
                }
            }, []);

            // Get current region values
            const selectedRegions = attributes.blazeCommerceRegions || [];
            console.log('BlazeCommerce: Selected regions for', name, ':', selectedRegions);

            // Handle region toggle
            const toggleRegion = (regionCode) => {
                const newRegions = selectedRegions.includes(regionCode)
                    ? selectedRegions.filter(code => code !== regionCode)
                    : [...selectedRegions, regionCode];

                console.log('BlazeCommerce: Updating regions for', name, 'to:', newRegions);
                setAttributes({ blazeCommerceRegions: newRegions });
            };

            return createElement(
                Fragment,
                {},
                createElement(BlockEdit, props),
                createElement(
                    InspectorAdvancedControls,
                    {},
                    createElement('div', {
                        className: 'blaze-commerce-regions-control'
                    },
                        createElement('label', {
                            className: 'components-base-control__label'
                        }, 'Regions'),

                        isLoading ? createElement(Spinner) :
                        availableRegions.length === 0 ?
                        createElement('p', {
                            className: 'description'
                        }, 'No regions available. Please check Aelia Currency Switcher configuration.') :

                        createElement('div', {
                            className: 'blaze-commerce-regions-checkboxes'
                        },
                            availableRegions.map(region =>
                                createElement(CheckboxControl, {
                                    key: region.code,
                                    label: region.label,
                                    checked: selectedRegions.includes(region.code),
                                    onChange: () => toggleRegion(region.code)
                                })
                            )
                        ),

                        createElement('p', {
                            className: 'description'
                        }, 'Select the regions where this block should be displayed.')
                    )
                )
            );
        };
    }, 'withRegionControl');

    /**
     * Save region data to block metadata
     */
    function addRegionSaveProps(extraProps, blockType, attributes) {
        // blockType parameter required by WordPress filter but not used
        const selectedRegions = attributes.blazeCommerceRegions;

        if (selectedRegions && Array.isArray(selectedRegions) && selectedRegions.length > 0) {
            // Add regions as a data attribute for frontend use
            if (!extraProps) {
                extraProps = {};
            }

            if (!extraProps['data-blaze-regions']) {
                extraProps['data-blaze-regions'] = selectedRegions.join(',');
            }
        }

        return extraProps;
    }

    // Apply filters to extend all blocks
    addFilter(
        'blocks.registerBlockType',
        'blaze-commerce/add-regions-attribute',
        addRegionAttribute
    );

    addFilter(
        'editor.BlockEdit',
        'blaze-commerce/with-regions-control',
        withRegionControl
    );

    addFilter(
        'blocks.getSaveContent.extraProps',
        'blaze-commerce/add-regions-save-props',
        addRegionSaveProps
    );

    // Debug logging
    if (window.console && window.console.log) {
        console.log('BlazeCommerce: Global block region configuration loaded');
    }

})();
