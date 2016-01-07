<?php

/**
 * @file
 * Contains Drupal\migrate_source_json\Plugin\migrate\JSONClientInterface.
 *
 */

namespace Drupal\migrate_source_json\Plugin\migrate;

/**
 * Provides an interface for the JSON client.
 */
interface JSONClientInterface {

  /**
   * Set the client headers.
   *
   * @param $headers
   *   An array of the headers to set on the HTTP request.
   */
  public function setRequestHeaders( array $headers );

  /**
   * Get the currently set request headers.
   */
  public function getRequestHeaders();

  /**
   * Return content.
   *
   * @return
   *   Content at the given url.
   */
  public function getResponseContent( $url );

}