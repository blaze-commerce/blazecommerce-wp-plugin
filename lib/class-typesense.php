<?php

use Symfony\Component\HttpClient\HttplugClient;
use Typesense\Client;

if ( !class_exists( 'Blaze_Wooless_Settings' ) ) {
    class Blaze_Wooless_Typesense
    {
        private static $instance = null;
        private $api_key = null;
        private $host = null;
        private $store_id = null;
        private $client = null;

        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self( bw_get_general_settings() );
            }

            return self::$instance;
        }

        public function __construct( $settings )
        {
            $decoded_api = bw_get_decoded_api_data( $settings['api_key'] );
            $this->api_key = $decoded_api['private_key'];
            $this->store_id = $decoded_api['store_id'];            

            $client = $this->get_client( $this->api_key, $settings['environment']);

            $this->client = $client;

            // ajax endpoints
            // add_action( 'wp_ajax_blaze_wooless_test_connection', array( $this, 'blaze_wooless_test_connection' ) );
        }

        public function debug()
        {
            return array(
                $this->api_key,
                $this->store_id,
                $this->host,
            );
        }

        public function get_client( $api_key, $environment )
        {
            $this->host = 'gq6r7nsikma359hep-1.a1.typesense.net';
            if ( $environment === 'live' ) {
                $this->host = 'dky8huxwbpm16zrcp-1.a1.typesense.net';
            }
            return new Client([
                'api_key' => $api_key,
                'nodes' => [
                    [
                        'host' => $this->host,
                        'port' => '443',
                        'protocol' => 'https',
                    ],
                ],
                'client' => new HttplugClient(),
            ]);
        }

        public function client()
        {
            return $this->client;
        }

        public function site_info()
        {
            $collection_site_info = 'site_info-' . $this->store_id;
            return $this->client->collections[$collection_site_info]->documents;
        }

        public function test_connection( $api_key, $store_id, $environement )
        {
            $client = $this->get_client( $api_key, $environement );
            try {
                $collection_name = 'product-' . $store_id;
                $collections = $client->collections[$collection_name]->retrieve();
                if (!empty($collections)) {
                    return array('status' => 'success', 'message' => 'Typesense is working!', 'collection' => $collections );
                } else {
                    return array('status' => 'error', 'message' => 'No collection found for store ID: ' . $this->store_id );
                }
            } catch (Typesense\Exception\ObjectNotFound $e) {
                return array( 'status' => 'error', 'message' => 'Collection not found: ' . $e->getMessage() );
            } catch (Typesense\Exception\TypesenseClientError $e) {
                return array( 'status' => 'error', 'message' => 'Typesense client error: ' . $e->getMessage() );
            } catch (Exception $e) {
                return array( 'status' => 'error', 'message' => 'There was an error connecting to Typesense: ' . $e->getMessage() );
            }
        }
    }
    Blaze_Wooless_Typesense::get_instance();
}
