<?php

/**
 * @file
 * Contains Drupal\migrate_source_json\Plugin\migrate\JSONReaderInterface.
 *
 */

namespace Drupal\migrate_source_json\Plugin\migrate;

/**
 * Provides an interface for the JSON reader.
 */
interface JSONReaderInterface {

  /**
   * Set the HttpClient that will handle requests.
   *
   * @param resource $client
   *   The client object.
   */
  public function setClient();

  /**
   * Get the HttpClient that will handle requests.
   */
  public function getClient();

  /**
   * Create array of source fields from the source data.
   *
   * Do whatever work is needed to massage the JSON source data into
   * an array of fields and values.
   *
   * @param $url
   *   The url that contains the JSON source data.
   *
   * @return array
   *   An array of source fields and values.
   */
  public function getSourceFields( $url );

  /**
   * Construct the source fields iterator.
   *
   * @param $url
   *   The url that contains the JSON source data.
   *
   * @return resource
   *   A SPL Iterator containing the source fields to pass to the Migrate module.
   */
  public function getSourceFieldsIterator( $url );

}
