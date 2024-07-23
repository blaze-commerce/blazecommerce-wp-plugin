<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;

class BaseCollection {
	protected $typesense;
	public $collection_name;

	public function __construct() {
		$this->typesense = TypesenseClient::get_instance();
	}

	public function collection() {
		return $this->client()->collections[ $this->collection_name()];
	}

	public function client() {
		return $this->typesense->client();
	}

	public function store_id() {
		return $this->typesense->store_id;
	}

	public function collection_name() {
		return $this->collection_name . '-' . $this->typesense->store_id;
	}

	public function create_collection( $args ) {
		return $this->client()->collections->create( $args );
	}

	public function retrieve() {
		return $this->collection()->retrieve();
	}

	public function drop_collection() {
		try {
			return $this->collection()->delete();
		} catch (\Exception $e) {
			return $e;
		}
	}

	public function import( $batch ) {
		$batch_files = array_map( function ($data) {
			return json_encode( $data );
		}, $batch );
		$to_jsonl    = implode( PHP_EOL, $batch_files );

		$curl = curl_init();

		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'https://' . $this->typesense->get_host() . '/collections/' . $this->collection_name() . '/documents/import?action=upsert',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $to_jsonl,
			CURLOPT_HTTPHEADER => array(
				'X-TYPESENSE-API-KEY: ' . $this->typesense->get_api_key(),
				'Content-Type: text/plain'
			),
		) );

		$response = curl_exec( $curl );

		curl_close( $curl );

		$response_from_jsonl = explode( PHP_EOL, $response );

		$mapped_response = array_map( function ($resp) {
			return json_decode( $resp, true );
		}, $response_from_jsonl );

		return $mapped_response;
	}

	public function create( $args ) {
		return $this->collection()->documents->create( $args );
	}

	public function update( $id, $document_data ) {
		return $this->collection()->documents[ $id ]->update( $document_data );
	}

	public function upsert( $document_data ) {
		return $this->collection()->documents->upsert( $document_data );
	}
}
