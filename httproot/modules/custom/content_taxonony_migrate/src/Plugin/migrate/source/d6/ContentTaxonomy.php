<?php
/** 
 * @file
 * Contains \Drupal\content_taxonomy_migrate\Plugin\migrate\source\d6\ContentTaxonomy
 */

namespace Drupal\content_taxonomy_migrate\source;

use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;

/**
 * Source for content taxonomy fields.
 * 
 * @Migratesource(
 *    id = "ContentTaxonomy"
 * )
 */
class ContentTaxonomy extends SourcePluginBase {
  
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    
  }

  protected function initializeIterator() {
    
  }

  public function __toString() {
    
  }

  public function fields() {
    
  }

  public function getIds() {
    
  }

}