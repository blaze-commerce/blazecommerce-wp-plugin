<?php
/**
 * Test Plugin Integration URL Manager
 * 
 * This test validates that the PluginIntegrationUrlManager properly handles
 * plugin API communication scenarios in headless WordPress setups.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../../' );
}

// Mock WordPress functions for testing
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() { return false; }
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url( $path = '', $scheme = null ) {
		return 'https://cart.example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '', $scheme = null ) {
		return 'https://example.com' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		$options = array(
			'siteurl' => 'https://cart.example.com',
			'home' => 'https://example.com',
		);
		return isset( $options[ $option ] ) ? $options[ $option ] : $default;
	}
}

if ( ! function_exists( 'bw_get_general_settings' ) ) {
	function bw_get_general_settings( $key ) {
		$settings = array(
			'enable_plugin_url_override' => '1',
			'custom_plugin_contexts' => "custom-plugin\nmy-integration\npayment-gateway",
		);
		return isset( $settings[ $key ] ) ? $settings[ $key ] : '';
	}
}

// Load the class
require_once __DIR__ . '/../app/Features/PluginIntegrationUrlManager.php';

class PluginIntegrationUrlManagerTest {
	private $manager;
	
	public function __construct() {
		$this->manager = new \BlazeWooless\Features\PluginIntegrationUrlManager();
	}
	
	/**
	 * Test headless setup detection
	 */
	public function test_headless_setup_detection() {
		$reflection = new ReflectionClass( $this->manager );
		$method = $reflection->getMethod( 'is_headless_setup' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->manager );
		
		echo "✅ Headless setup detection: " . ( $result ? 'PASS' : 'FAIL' ) . "\n";
		return $result;
	}
	
	/**
	 * Test settings integration
	 */
	public function test_settings_integration() {
		$reflection = new ReflectionClass( $this->manager );
		$method = $reflection->getMethod( 'is_plugin_url_override_enabled' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->manager );
		
		echo "✅ Settings integration: " . ( $result ? 'PASS' : 'FAIL' ) . "\n";
		return $result;
	}
	
	/**
	 * Test custom contexts functionality
	 */
	public function test_custom_contexts() {
		$reflection = new ReflectionClass( $this->manager );
		$method = $reflection->getMethod( 'get_custom_plugin_contexts' );
		$method->setAccessible( true );
		
		$contexts = $method->invoke( $this->manager );
		$expected = array( 'custom-plugin', 'my-integration', 'payment-gateway' );
		
		$result = $contexts === $expected;
		
		echo "✅ Custom contexts: " . ( $result ? 'PASS' : 'FAIL' ) . "\n";
		if ( ! $result ) {
			echo "   Expected: " . print_r( $expected, true );
			echo "   Got: " . print_r( $contexts, true );
		}
		return $result;
	}
	
	/**
	 * Test API URL scenarios
	 */
	public function test_plugin_api_scenarios() {
		$test_cases = array(
			// Payment gateway scenarios
			array(
				'description' => 'PayPal IPN callback',
				'url' => 'https://example.com/wc-api/paypal_ipn',
				'expected_host' => 'cart.example.com',
			),
			array(
				'description' => 'Stripe webhook',
				'url' => 'https://example.com/wp-json/stripe/webhook',
				'expected_host' => 'cart.example.com',
			),
			array(
				'description' => 'WooCommerce REST API',
				'url' => 'https://example.com/wp-json/wc/v3/products',
				'expected_host' => 'cart.example.com',
			),
			array(
				'description' => 'WordPress REST API',
				'url' => 'https://example.com/wp-json/wp/v2/posts',
				'expected_host' => 'cart.example.com',
			),
		);
		
		$reflection = new ReflectionClass( $this->manager );
		$method = $reflection->getMethod( 'safely_replace_url_host' );
		$method->setAccessible( true );
		
		$all_passed = true;
		
		foreach ( $test_cases as $case ) {
			$result_url = $method->invoke( $this->manager, $case['url'] );
			$result_host = parse_url( $result_url, PHP_URL_HOST );
			
			$passed = $result_host === $case['expected_host'];
			$all_passed = $all_passed && $passed;
			
			echo ( $passed ? "✅" : "❌" ) . " {$case['description']}: " . ( $passed ? 'PASS' : 'FAIL' ) . "\n";
			if ( ! $passed ) {
				echo "   Expected host: {$case['expected_host']}\n";
				echo "   Got host: {$result_host}\n";
				echo "   Result URL: {$result_url}\n";
			}
		}
		
		return $all_passed;
	}
	
	/**
	 * Test URL security and validation
	 */
	public function test_url_security() {
		$reflection = new ReflectionClass( $this->manager );
		$method = $reflection->getMethod( 'safely_replace_url_host' );
		$method->setAccessible( true );
		
		$test_cases = array(
			array(
				'description' => 'Malformed URL',
				'url' => 'not-a-url',
				'expected_unchanged' => true,
			),
			array(
				'description' => 'Empty URL',
				'url' => '',
				'expected_unchanged' => true,
			),
			array(
				'description' => 'URL without host',
				'url' => '/path/only',
				'expected_unchanged' => true,
			),
		);
		
		$all_passed = true;
		
		foreach ( $test_cases as $case ) {
			$result_url = $method->invoke( $this->manager, $case['url'] );
			$unchanged = $result_url === $case['url'];
			
			$passed = $case['expected_unchanged'] ? $unchanged : ! $unchanged;
			$all_passed = $all_passed && $passed;
			
			echo ( $passed ? "✅" : "❌" ) . " Security test - {$case['description']}: " . ( $passed ? 'PASS' : 'FAIL' ) . "\n";
		}
		
		return $all_passed;
	}
	
	/**
	 * Run all tests
	 */
	public function run_all_tests() {
		echo "🧪 Testing Plugin Integration URL Manager\n";
		echo "==========================================\n\n";
		
		$tests = array(
			'test_headless_setup_detection',
			'test_settings_integration', 
			'test_custom_contexts',
			'test_plugin_api_scenarios',
			'test_url_security',
		);
		
		$passed = 0;
		$total = count( $tests );
		
		foreach ( $tests as $test ) {
			if ( $this->$test() ) {
				$passed++;
			}
			echo "\n";
		}
		
		echo "==========================================\n";
		echo "Results: {$passed}/{$total} tests passed\n";
		
		if ( $passed === $total ) {
			echo "🎉 All tests passed! Plugin API communication should work correctly.\n";
		} else {
			echo "⚠️  Some tests failed. Please review the implementation.\n";
		}
		
		return $passed === $total;
	}
}

// Mock server variables for testing
$_SERVER['REQUEST_URI'] = '/wp-json/wc/v3/products';
$_SERVER['HTTP_HOST'] = 'cart.example.com';

// Run the tests
$tester = new PluginIntegrationUrlManagerTest();
$success = $tester->run_all_tests();

// Exit with appropriate code
exit( $success ? 0 : 1 );