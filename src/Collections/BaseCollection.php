<?php

namespace BlazeWooless\Collections;

use BlazeWooless\TypesenseClient;

class BaseCollection
{
    protected $typesense;
    public $collection_name; 

    public function __construct()
    {
        $this->typesense = TypesenseClient::get_instance();
    }

    public function collection()
    {
        return $this->client()->collections[ $this->collection_name() ];
    }

    public function client()
    {
        return $this->typesense->client();
    }

    public function store_id()
    {
        return $this->typesense->store_id;
    }

    public function collection_name()
    {
        return $this->collection_name . '-' . $this->typesense->store_id;
    }

    public function create_collection( $args )
    {
        return $this->client()->collections->create( $args );
    }

    public function retrieve()
    {
        return $this->collection()->retrieve();
    }

    public function drop_collection()
    {
        return $this->collection()->delete();
    }

    public function import( $batch )
    {
        return $this->collection()->documents->import( $batch );
    }

    public function create( $args )
    {
        return $this->collection()->documents->create( $args );
    }

    public function update( $id, $document_data )
    {
        return $this->collection()->documents[ $id ]->update( $document_data );
    }
}
