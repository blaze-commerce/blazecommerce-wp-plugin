<?php

namespace BlazeWooless\Features;

class RedeployDiagnostics {
    private static $instance = null;

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_blaze_test_redeploy', array( $this, 'ajax_test_redeploy' ) );
        add_action( 'admin_notices', array( $this, 'show_redeploy_diagnostics_notice' ) );
        
        // Add WP-CLI commands if WP-CLI is available
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'blaze redeploy test', array( $this, 'cli_test_redeploy' ) );
            \WP_CLI::add_command( 'blaze redeploy diagnose', array( $this, 'cli_diagnose_redeploy' ) );
        }
    }

    /**
     * Run comprehensive redeploy diagnostics
     */
    public function diagnose_redeploy_functionality() {
        $results = array(
            'api_key_present' => false,
            'store_id_present' => false,
            'headers_valid' => false,
            'external_api_reachable' => false,
            'deployment_check_works' => false,
            'redeploy_trigger_works' => false,
            'issues_found' => array(),
            'recommendations' => array()
        );

        // Check API key
        $api_key = bw_get_general_settings( 'typesense_api_key' );
        $results['api_key_present'] = ! empty( $api_key );
        
        if ( ! $results['api_key_present'] ) {
            $results['issues_found'][] = 'Typesense API key is missing or empty';
            $results['recommendations'][] = 'Set the Typesense API key in BlazeCommerce > General Settings';
        }

        // Check store ID
        $store_id = bw_get_general_settings( 'store_id' );
        $results['store_id_present'] = ! empty( $store_id );
        
        if ( ! $results['store_id_present'] ) {
            $results['issues_found'][] = 'Store ID is missing or empty';
            $results['recommendations'][] = 'Set the Store ID in BlazeCommerce > General Settings';
        }

        // Check headers generation
        if ( $results['api_key_present'] && $results['store_id_present'] ) {
            $ajax_instance = \BlazeWooless\Ajax::get_instance();
            $headers = $ajax_instance->get_headers();
            $results['headers_valid'] = ! empty( $headers ) && is_array( $headers );
            
            if ( ! $results['headers_valid'] ) {
                $results['issues_found'][] = 'Failed to generate valid headers for API requests';
            }
        }

        // Test external API connectivity
        if ( $results['headers_valid'] ) {
            $api_test = $this->test_external_api_connectivity();
            $results['external_api_reachable'] = $api_test['success'];
            
            if ( ! $results['external_api_reachable'] ) {
                $results['issues_found'][] = 'Cannot reach external deployment API: ' . $api_test['error'];
                $results['recommendations'][] = 'Check network connectivity and firewall settings';
            }
        }

        // Test deployment check
        if ( $results['external_api_reachable'] ) {
            $check_test = $this->test_deployment_check();
            $results['deployment_check_works'] = $check_test['success'];
            
            if ( ! $results['deployment_check_works'] ) {
                $results['issues_found'][] = 'Deployment check failed: ' . $check_test['error'];
            }
        }

        // Test redeploy trigger
        if ( $results['external_api_reachable'] ) {
            $redeploy_test = $this->test_redeploy_trigger();
            $results['redeploy_trigger_works'] = $redeploy_test['success'];
            
            if ( ! $results['redeploy_trigger_works'] ) {
                $results['issues_found'][] = 'Redeploy trigger failed: ' . $redeploy_test['error'];
            }
        }

        return $results;
    }

    /**
     * Test external API connectivity
     */
    private function test_external_api_connectivity() {
        try {
            $ajax_instance = \BlazeWooless\Ajax::get_instance();
            $headers = $ajax_instance->get_headers();
            
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_URL => 'https://my-wooless-admin-portal.vercel.app/api/deployments?checkDeployment=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30, // Increased timeout for testing
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_VERBOSE => false
            ) );
            
            $response = curl_exec( $curl );
            $http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
            $curl_error = curl_error( $curl );
            curl_close( $curl );
            
            if ( $curl_error ) {
                return array( 'success' => false, 'error' => 'cURL Error: ' . $curl_error );
            }
            
            if ( $http_code !== 200 ) {
                return array( 'success' => false, 'error' => "HTTP Error: $http_code. Response: " . substr( $response, 0, 200 ) );
            }
            
            $decoded_response = json_decode( $response, true );
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                return array( 'success' => false, 'error' => 'Invalid JSON response: ' . json_last_error_msg() );
            }
            
            return array( 'success' => true, 'response' => $decoded_response );
            
        } catch ( \Exception $e ) {
            return array( 'success' => false, 'error' => 'Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Test deployment check functionality
     */
    private function test_deployment_check() {
        try {
            ob_start();
            $ajax_instance = \BlazeWooless\Ajax::get_instance();
            $ajax_instance->check_deployment();
            $output = ob_get_clean();
            
            // Since check_deployment uses wp_send_json, we need to capture the output
            if ( empty( $output ) ) {
                return array( 'success' => false, 'error' => 'No response from check_deployment method' );
            }
            
            return array( 'success' => true, 'response' => $output );
            
        } catch ( \Exception $e ) {
            ob_end_clean();
            return array( 'success' => false, 'error' => 'Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Test redeploy trigger functionality
     */
    private function test_redeploy_trigger() {
        try {
            ob_start();
            $ajax_instance = \BlazeWooless\Ajax::get_instance();
            $ajax_instance->redeploy_store_front();
            $output = ob_get_clean();
            
            // Since redeploy_store_front uses wp_send_json, we need to capture the output
            if ( empty( $output ) ) {
                return array( 'success' => false, 'error' => 'No response from redeploy_store_front method' );
            }
            
            return array( 'success' => true, 'response' => $output );
            
        } catch ( \Exception $e ) {
            ob_end_clean();
            return array( 'success' => false, 'error' => 'Exception: ' . $e->getMessage() );
        }
    }

    /**
     * Show admin notice about redeploy diagnostics
     */
    public function show_redeploy_diagnostics_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show on BlazeCommerce admin pages
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'blaze' ) === false ) {
            return;
        }

        // Check if redeploy functionality might be broken
        $api_key = bw_get_general_settings( 'typesense_api_key' );
        $store_id = bw_get_general_settings( 'store_id' );
        
        if ( empty( $api_key ) || empty( $store_id ) ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>BlazeCommerce Redeploy Issue:</strong> Redeploy functionality may not work due to missing API credentials.</p>';
            echo '<p><button type="button" class="button button-secondary" onclick="blazeTestRedeploy()">Test Redeploy Functionality</button></p>';
            echo '</div>';

            // Add JavaScript for the test button
            ?>
            <script>
            function blazeTestRedeploy() {
                jQuery.post(ajaxurl, {
                    action: 'blaze_test_redeploy',
                    nonce: '<?php echo wp_create_nonce( 'blaze_test_redeploy' ); ?>'
                }, function(response) {
                    if (response.success) {
                        var results = response.data;
                        var message = 'Redeploy Diagnostics Results:\n\n';
                        message += 'API Key Present: ' + (results.api_key_present ? 'YES' : 'NO') + '\n';
                        message += 'Store ID Present: ' + (results.store_id_present ? 'YES' : 'NO') + '\n';
                        message += 'Headers Valid: ' + (results.headers_valid ? 'YES' : 'NO') + '\n';
                        message += 'External API Reachable: ' + (results.external_api_reachable ? 'YES' : 'NO') + '\n';
                        message += 'Deployment Check Works: ' + (results.deployment_check_works ? 'YES' : 'NO') + '\n';
                        message += 'Redeploy Trigger Works: ' + (results.redeploy_trigger_works ? 'YES' : 'NO') + '\n';
                        
                        if (results.issues_found.length > 0) {
                            message += '\nIssues Found:\n';
                            results.issues_found.forEach(function(issue) {
                                message += '- ' + issue + '\n';
                            });
                        }
                        
                        if (results.recommendations.length > 0) {
                            message += '\nRecommendations:\n';
                            results.recommendations.forEach(function(rec) {
                                message += '- ' + rec + '\n';
                            });
                        }
                        
                        alert(message);
                        console.log('Redeploy Diagnostics Results:', results);
                    } else {
                        alert('Error testing redeploy: ' + response.data);
                    }
                });
            }
            </script>
            <?php
        }
    }

    /**
     * AJAX handler for testing redeploy
     */
    public function ajax_test_redeploy() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_ajax_referer( 'blaze_test_redeploy', 'nonce' );

        $results = $this->diagnose_redeploy_functionality();
        wp_send_json_success( $results );
    }

    /**
     * WP-CLI command to test redeploy
     */
    public function cli_test_redeploy( $args, $assoc_args ) {
        \WP_CLI::log( 'Testing redeploy functionality...' );
        
        $api_test = $this->test_external_api_connectivity();
        
        if ( $api_test['success'] ) {
            \WP_CLI::success( 'External API is reachable' );
            \WP_CLI::log( 'Response: ' . json_encode( $api_test['response'], JSON_PRETTY_PRINT ) );
        } else {
            \WP_CLI::error( 'External API test failed: ' . $api_test['error'] );
        }
    }

    /**
     * WP-CLI command to diagnose redeploy
     */
    public function cli_diagnose_redeploy( $args, $assoc_args ) {
        \WP_CLI::log( 'Running comprehensive redeploy diagnostics...' );
        
        $results = $this->diagnose_redeploy_functionality();

        \WP_CLI::log( 'Redeploy Diagnostics Results:' );
        \WP_CLI::log( '=============================' );
        
        \WP_CLI::log( 'API Key Present: ' . ( $results['api_key_present'] ? 'YES' : 'NO' ) );
        \WP_CLI::log( 'Store ID Present: ' . ( $results['store_id_present'] ? 'YES' : 'NO' ) );
        \WP_CLI::log( 'Headers Valid: ' . ( $results['headers_valid'] ? 'YES' : 'NO' ) );
        \WP_CLI::log( 'External API Reachable: ' . ( $results['external_api_reachable'] ? 'YES' : 'NO' ) );
        \WP_CLI::log( 'Deployment Check Works: ' . ( $results['deployment_check_works'] ? 'YES' : 'NO' ) );
        \WP_CLI::log( 'Redeploy Trigger Works: ' . ( $results['redeploy_trigger_works'] ? 'YES' : 'NO' ) );

        if ( ! empty( $results['issues_found'] ) ) {
            \WP_CLI::warning( 'Issues Found:' );
            foreach ( $results['issues_found'] as $issue ) {
                \WP_CLI::log( '  ✗ ' . $issue );
            }
        }

        if ( ! empty( $results['recommendations'] ) ) {
            \WP_CLI::log( '' );
            \WP_CLI::log( 'Recommendations:' );
            foreach ( $results['recommendations'] as $rec ) {
                \WP_CLI::log( '  → ' . $rec );
            }
        }

        if ( empty( $results['issues_found'] ) ) {
            \WP_CLI::success( 'All redeploy functionality tests passed!' );
        }
    }
}
