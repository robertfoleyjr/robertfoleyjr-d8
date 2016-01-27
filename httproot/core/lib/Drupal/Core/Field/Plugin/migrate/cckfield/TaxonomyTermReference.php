<?php

/**
 * @file
 * Contains \Drupal\Core\Field\Plugin\migrate\cckfield\TaxonomyTermReference.
 */

namespace Drupal\Core\Field\Plugin\migrate\cckfield;

use Drupal\migrate\Row;

/**
 * @MigrateCckField(
 *   id = "taxonomy_term_reference",
 *   type_map = {
 *     "taxonomy_term_reference" = "entity_reference"
 *   }
 * )
 */
class TaxonomyTermReference extends ReferenceBase {

  /**
   * {@inheritdoc}
   */
  protected function entityId() {
    return 'tid';
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'taxonomy_term_reference_select' => 'options_select',
      'taxonomy_term_reference_buttons' => 'options_buttons',
      'taxonomy_term_reference_autocomplete' => 'entity_reference_autocomplete'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldStorageSettings(Row $row) {
    $settings['target_type'] = 'taxonomy_term';
    return $settings;
  }

}
