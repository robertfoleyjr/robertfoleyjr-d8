<?php

/**
 * @file
 * Contains Drupal\migrate_source_json\Plugin\migrate\JSONClient.
 *
 * Uses the Guzzle HTTP Client library, which is wrapped by \Drupal::httpClient.
 *
 * @see http://docs.guzzlephp.org/
 */

namespace Drupal\migrate_source_json\Plugin\migrate;

use Drupal\migrate\MigrateException;
use GuzzleHttp\Exception\RequestException;

/**
 * Object to retrieve and iterate over JSON data.
 */
class JSONClient implements JSONClientInterface {

  /**
   * The HTTP Client
   *
   * @var resource
   */
  protected $http_client;

  /**
   * The request headers.
   *
   * @var array
   */
  protected $headers = [];

  public function __construct() {
    $this->http_client = \Drupal::httpClient();
  }

  /**
   * Set the client headers.
   */
  public function setRequestHeaders( array $headers ) {
    $this->headers = $headers;
  }

  /**
   * Get the currently set headers.
   */
  public function getRequestHeaders() {
    return !empty($this->headers) ? $this->headers : array();
  }

  /**
   * Return Http Response object for a given url.
   */
  public function getResponse($url) {
    try {
      $response = $this->http_client->get($url, array(
        'headers' => $this->getRequestHeaders(),
        // Uncomment the following to debug the request.
        //'debug' => true,
      ));
      if (empty($response)) {
        throw new MigrateException('No response at ' . $this->getPath() . '.');
      }
    } catch (RequestException $e) {
        throw new MigrateException('Error message: ' . $e->getMessage() . ' at ' . $url .'.');
    }
    return $response;
  }

  /**
   * Return content for a given url.
   */
  public function getResponseContent($url) {
    $response = $this->getResponse($url);
    return $response->getBody();
  }


}
