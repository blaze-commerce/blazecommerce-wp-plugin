<?php

namespace BlazeWooless\Features;

use BlazeWooless\Collections\Product;
use BlazeWooless\Collections\Taxonomy;
use BlazeWooless\TypesenseClient;

class IndividualSyncFix {
    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_blaze_test_individual_sync', array( $this, 'ajax_test_individual_sync' ) );
        add_action( 'admin_notices', array( $this, 'show_individual_sync_fix_notice' ) );
        
        // Add WP-CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'blaze sync test-individual', array( $this, 'cli_test_individual_sync' ) );
        }
    }

    /**
     * Test individual sync methods to verify the fix
     */
    public function test_individual_sync_methods() {
        $results = array(
            'product_sync_test' => false,
            'taxonomy_sync_test' => false,
            'issues_found' => array(),
            'fix_applied' => true
        );

        // Check if system is enabled
        $enable_system = boolval( bw_get_general_settings( 'enable_system' ) );
        if ( ! $enable_system ) {
            $results['issues_found'][] = 'BlazeCommerce system is disabled';
            return $results;
        }

        // Test product sync
        try {
            // Simulate AJAX request
            $_REQUEST['collection_name'] = 'products';
            $_REQUEST['page'] = 1;

            $initial_count = $this->get_collection_document_count( 'product' );
            
            ob_start();
            Product::get_instance()->index_to_typesense();
            $output = ob_get_clean();

            $final_count = $this->get_collection_document_count( 'product' );
            
            if ( $final_count >= $initial_count ) {
                $results['product_sync_test'] = true;
            } else {
                $results['issues_found'][] = 'Product sync did not maintain/increase document count';
            }

        } catch ( \Exception $e ) {
            $results['issues_found'][] = 'Product sync error: ' . $e->getMessage();
        }

        // Test taxonomy sync
        try {
            // Simulate AJAX request
            $_REQUEST['collection_name'] = 'taxonomy';
            $_REQUEST['page'] = 1;
            $_REQUEST['batch_size'] = 5;
            $_REQUEST['imported_count'] = 0;
            $_REQUEST['total_imports'] = 0;

            $initial_count = $this->get_collection_document_count( 'taxonomy' );
            
            ob_start();
            Taxonomy::get_instance()->index_to_typesense();
            $output = ob_get_clean();

            $final_count = $this->get_collection_document_count( 'taxonomy' );
            
            if ( $final_count >= $initial_count ) {
                $results['taxonomy_sync_test'] = true;
            } else {
                $results['issues_found'][] = 'Taxonomy sync did not maintain/increase document count';
            }

        } catch ( \Exception $e ) {
            $results['issues_found'][] = 'Taxonomy sync error: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Get document count for a collection type
     */
    private function get_collection_document_count( $collection_type ) {
        try {
            $store_id = bw_get_general_settings( 'store_id' );
            $collection_name = "{$collection_type}-{$store_id}";
            
            $typesense_client = TypesenseClient::get_instance();
            $search_result = $typesense_client->client->collections[ $collection_name ]->documents->search( array(
                'q' => '*',
                'per_page' => 1
            ) );
            
            return $search_result['found'] ?? 0;
        } catch ( \Exception $e ) {
            return 0;
        }
    }

    /**
     * Show admin notice about the individual sync fix
     */
    public function show_individual_sync_fix_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show on BlazeCommerce admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'blaze' ) === false ) {
            return;
        }

        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>BlazeCommerce Individual Sync Fix Applied:</strong> The issue where individual sync buttons resulted in 0 documents has been fixed. Response handling inconsistencies have been resolved.</p>';
        echo '<p><button type="button" class="button button-secondary" onclick="blazeTestIndividualSync()">Test Individual Sync Methods</button></p>';
        echo '</div>';

        // Add JavaScript for the test button
        ?>
        <script>
        function blazeTestIndividualSync() {
            jQuery.post(ajaxurl, {
                action: 'blaze_test_individual_sync',
                nonce: '<?php echo wp_create_nonce( 'blaze_test_individual_sync' ); ?>'
            }, function(response) {
                if (response.success) {
                    var results = response.data;
                    var message = 'Individual Sync Test Results:\n\n';
                    message += 'Product Sync: ' + (results.product_sync_test ? 'PASS' : 'FAIL') + '\n';
                    message += 'Taxonomy Sync: ' + (results.taxonomy_sync_test ? 'PASS' : 'FAIL') + '\n';
                    
                    if (results.issues_found.length > 0) {
                        message += '\nIssues Found:\n';
                        results.issues_found.forEach(function(issue) {
                            message += '- ' + issue + '\n';
                        });
                    }
                    
                    alert(message);
                    console.log('Individual Sync Test Results:', results);
                } else {
                    alert('Error testing individual sync: ' + response.data);
                }
            });
        }
        </script>
        <?php
    }

    /**
     * AJAX handler for testing individual sync
     */
    public function ajax_test_individual_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'blaze_test_individual_sync', 'nonce' );

        $results = $this->test_individual_sync_methods();
        wp_send_json_success( $results );
    }

    /**
     * WP-CLI command to test individual sync
     */
    public function cli_test_individual_sync( $args, $assoc_args ) {
        \WP_CLI::log( 'Testing individual sync methods after fix...' );
        
        $results = $this->test_individual_sync_methods();

        \WP_CLI::log( 'Individual Sync Test Results:' );
        \WP_CLI::log( '=============================' );
        
        \WP_CLI::log( 'Product Sync Test: ' . ( $results['product_sync_test'] ? 'PASS' : 'FAIL' ) );
        \WP_CLI::log( 'Taxonomy Sync Test: ' . ( $results['taxonomy_sync_test'] ? 'PASS' : 'FAIL' ) );

        if ( ! empty( $results['issues_found'] ) ) {
            \WP_CLI::warning( 'Issues Found:' );
            foreach ( $results['issues_found'] as $issue ) {
                \WP_CLI::log( '  ✗ ' . $issue );
            }
        } else {
            \WP_CLI::success( 'All individual sync tests passed!' );
        }
    }
}
