<?php

/**
 * @file
 * Contains \Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase.
 */

namespace Drupal\migrate_drupal\Plugin\migrate\cckfield;

use Drupal\Core\Plugin\PluginBase;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\MigrateCckFieldInterface;

/**
 * The base class for all cck field plugins.
 *
 * @see \Drupal\migrate_drupal\Plugin\MigratePluginManager
 * @see \Drupal\migrate_drupal\Annotation\MigrateCckField
 * @see \Drupal\migrate_drupal\Plugin\MigrateCckFieldInterface
 * @see plugin_api
 *
 * @ingroup migration
 */
abstract class CckFieldPluginBase extends PluginBase implements MigrateCckFieldInterface  {

  /**
   * {@inheritdoc}
   */
  public function processField(MigrationInterface $migration) {
    $process[0]['map'][$this->pluginId][$this->pluginId] = $this->pluginId;
    $migration->mergeProcessOfProperty('type', $process);
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldInstance(MigrationInterface $migration) {
    // Nothing to do by default with field instances.
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldWidget(MigrationInterface $migration) {
    // Nothing to do by default with field widgets.
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    // By default, use the plugin ID for the widget types.
    return [
      $this->pluginId => $this->pluginId . '_default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldFormatter(MigrationInterface $migration) {
    $process = [];
    foreach ($this->getFieldFormatterMap() as $source_format => $destination_format) {
      $process[0]['map'][$this->pluginId][$source_format] = $destination_format;
    }
    $migration->mergeProcessOfProperty('options/type', $process);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldType(Row $row) {
    $field_type = $row->getSourceProperty('type');

    if (isset($this->pluginDefinition['type_map'][$field_type])) {
      return $this->pluginDefinition['type_map'][$field_type];
    }
    else {
      return $field_type;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldStorageSettings(Row $row) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldInstanceSettings(Row $row) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function transformWidgetType(Row $row) {
    $source_widget_type = $row->getSourceProperty('widget_type');
    $map = $this->getFieldWidgetMap();
    return isset($map[$source_widget_type]) ? $map[$source_widget_type] : NULL;
  }

}
