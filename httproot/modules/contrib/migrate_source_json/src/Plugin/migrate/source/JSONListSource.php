<?php

/**
 * @file
 * Contains Drupal\migrate_source_json\Plugin\migrate\source\JSONListSource.
 *
 * Has the functionality of JSON Source, but assumes the original path will return a list
 * of ids, requiring a second query for the specific content of that id.
 */

namespace Drupal\migrate_source_json\Plugin\migrate\source;

use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateException;

/**
 * Source for JSON data that contains a list of ids that require a second request.
 *
 * @MigrateSource(
 *   id = "json_listsource"
 * )
 */
class JSONListSource extends JSONSource {

  /**
   * The path to load the individual asset, using ':id' as a placeholder for where the id should go.
   *
   * @var string
   */
  protected $itemPath = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, array $namespaces = array()) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    if (empty($configuration['itemPath'])) {
      // Throw Exception
      throw new MigrateException('The itemPath must be provided in the configuration.');
    }
    else {
      $this->itemPath = (array) $configuration['itemPath'];
    }

  }

  /**
   * Get an individual item by its id.
   */
  public function getItemByID($id) {
    return parent::getSourceFields(str_replace(':id', $id, $this->itemPath));
  }

  /**
   * Return an array of all available source records.
   *
   * @return array
   *   The available source records.
   */
  public function getSourceFields($url) {
    $items = array();
    $ids = parent::getSourceFields($url);
    foreach ($ids as $item) {
      $items[] = getItemByID($item[$this->identifier]);
    }
    return $items;
  }

}
