<?php

/**
 * @file
 * Contains Drupal\migrate_source_json\Plugin\migrate\source\JSONMultiSource.
 *
 * Has the functionality of JSON Source, but treats the source path as an array of values,
 * allowing either passing in an array of paths to the constructor, or trying to detect
 * additional paths for paged data.
 *
 * The detection for paging is designed to work with two common patterns:
 * - The JSON file follows the JSONapi recommendations, which will return results
 *   formated as an array of links and an array of data, where the links will
 *   contain a 'next' item that identifies the next url if more pages are available.
 * - The JSON file contains a Link header with a rel="next" item that contains
 *   the next url if more pages are available.
 *
 * In either of the above cases, the sourceURLs array is expanded to include those
 * additional paths. Then the paths are processed one after the other, to create
 * an array of all source items on all those paths.
 */

namespace Drupal\migrate_source_json\Plugin\migrate\source;

use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Entity\MigrationInterface;

/**
 * Source for multiple JSON files.
 *
 * @MigrateSource(
 *   id = "json_multisource"
 * )
 */
class JSONMultiSource extends JSONSource {

  /**
   * The source URLs to load JSON from.
   *
   * @var array
   */
  protected $sourceUrls = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, array $namespaces = array()) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    // Pass in array of source urls. If there is just one, store it as an array.
    $this->sourceUrls = (array) $configuration['path'];

    // See if this is a paged response with next links. If so, add to the source_urls array.
    foreach ( (array) $configuration['path'] as $url) {
      $this->sourceUrls += $this->getNextLinks($url);
    }

    $this->sourceUrls = (array) $this->path;

  }

  /**
   * Collect an array of next links from a paged response.
   */
  protected function getNextLinks($url) {
    $urls = array();
    $more = TRUE;
    while ($more == TRUE) {
      $response = $this->reader->getClient()->getResponse($url);
      if ($url = $this->getNextFromHeaders($response)) {
        $urls[] = $url;
      }
      elseif ($url = $this->getNextFromLinks($response)) {
        $urls[] = $url;
      }
      else {
        $more = FALSE;
      }
    }
    return $urls;
  }

  /**
   * See if the next link is in a 'links' group in the response.
   */
  protected function getNextFromLinks($response) {
    $body = json_decode($response->getBody(), TRUE);
    if (!empty($body['links']) && array_key_exists('next', $body['links'])) {
      return $body['links']['next'];
    }
    return FALSE;
  }

  /**
   * See if the next link is in the header.
   */
  protected function getNextFromHeaders($response) {
    $headers = $response->getHeader('Link');
    foreach ($headers as $header) {
      $matches = array();
      preg_match('/^<(.*)>; rel="next"$/', $header, $matches);
      if (!empty($matches) && !empty($matches[1])) {
        return $matches[1];
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFields($url) {
    $items = array();
    foreach ($this->sourceUrls as $url) {
      $items += parent::getSourceFields($url);
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function count() {
    $count = 0;
    foreach ($this->sourceUrls as $url) {
      $count += parent::_count($url);
    }
    return $count;
  }

}
