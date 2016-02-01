<?php

/**
 * @file
 * Contains cckfield date datestamp definition.
 */

namespace Drupal\datestamp\Plugin\migrate\cckfield;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @PluginId("datestamp")
 */
class DatestampField extends CckFieldPluginBase {
  
  /**
   * {@inheritdoc}
   */
  public function processField(MigrationInterface $migration) {
    $process[0]['map'][$this->pluginId][$this->pluginId] = 'datetime';
    $migration->mergeProcessOfProperty('type', $process);
  }
  
  /**
   * {@inheritdoc}
   */
  public function processFieldWidget(MigrationInterface $migration) {
    $process['type']['map'][$this->pluginId] = 'datetime_default';
    $migration->mergeProcessOfProperty('options/type', $process);
  }
  
  public function getFieldFormatterMap() {
    return [
      'default' => 'datetime',
    ];
  }

  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'datestamp_field',
      'source' => [
        $field_name,
        $field_name . '_title',
        $field_name . 'attributes',
      ],
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

}