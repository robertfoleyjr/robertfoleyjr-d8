<?php

/**
 * @file
 * Contains \Drupal\link\Plugin\migrate\cckfield\LinkField.
 */

namespace Drupal\link\Plugin\migrate\cckfield;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @MigrateCckField(
 *   id = "link"
 * )
 */
class LinkField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    // See d6_field_formatter_settings.yml and CckFieldPluginBase
    // processFieldFormatter().
    return [
      'default' => 'link',
      'plain' => 'link',
      'absolute' => 'link',
      'title_plain' => 'link',
      'url' => 'link',
      'short' => 'link',
      'label' => 'link',
      'separate' => 'link_separate',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'd6_cck_link',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldInstanceSettings(Row $row) {
    $field_settings = $row->getSourceProperty('global_settings');
    // D6 has optional, required, value and none. D8 only has disabled (0)
    // optional (1) and required (2).
    $map = array('disabled' => 0, 'optional' => 1, 'required' => 2);
    $settings['title'] = $map[$field_settings['title']];
    return $settings;
  }

}
