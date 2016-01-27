<?php

/**
 * @file
 * Contains \Drupal\file\Plugin\migrate\cckfield\FileField.
 */

namespace Drupal\file\Plugin\migrate\cckfield;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;

/**
 * @MigrateCckField(
 *   id = "filefield"
 * )
 */
class FileField extends CckFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'filefield_widget' => 'file_generic',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return [
      'default' => 'file_default',
      'url_plain' => 'file_url_plain',
      'path_plain' => 'file_url_plain',
      'image_plain' => 'image',
      'image_nodelink' => 'image',
      'image_imagelink' => 'image',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = [
      'plugin' => 'd6_cck_file',
      'source' => $field_name,
    ];
    $migration->mergeProcessOfProperty($field_name, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldType(Row $row) {
    return $row->getSourceProperty('widget_type') == 'imagefield_widget' ? 'image' : 'file';
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldInstanceSettings(Row $row) {
    $widget_type = $row->getSourceProperty('widget_type');
    $widget_settings = $row->getSourceProperty('widget_settings');
    $field_settings = $row->getSourceProperty('global_settings');
    $settings = [];

    switch ($widget_type) {
      case 'filefield_widget':
        $settings['file_extensions'] = $widget_settings['file_extensions'];
        $settings['file_directory'] = $widget_settings['file_path'];
        $settings['description_field'] = $field_settings['description_field'];
        $settings['max_filesize'] = $this->convertSizeUnit($widget_settings['max_filesize_per_file']);
        break;

      case 'imagefield_widget':
        $settings['file_extensions'] = $widget_settings['file_extensions'];
        $settings['file_directory'] = 'public://';
        $settings['max_filesize'] = $this->convertSizeUnit($widget_settings['max_filesize_per_file']);
        $settings['alt_field'] = $widget_settings['alt'];
        $settings['alt_field_required'] = $widget_settings['custom_alt'];
        $settings['title_field'] = $widget_settings['title'];
        $settings['title_field_required'] = $widget_settings['custom_title'];
        $settings['max_resolution'] = $widget_settings['max_resolution'];
        $settings['min_resolution'] = $widget_settings['min_resolution'];
        break;
    }

    return $settings;
  }

  /**
   * Convert file size strings into their D8 format.
   *
   * D6 stores file size using a "K" for kilobytes and "M" for megabytes where
   * as D8 uses "KB" and "MB" respectively.
   *
   * @param string $size_string
   *   The size string, e.g. 10M
   *
   * @return string
   *   The D8 version of the size string.
   */
  protected function convertSizeUnit($size_string) {
    $size_unit = substr($size_string, strlen($size_string) - 1);
    if ($size_unit == "M" || $size_unit == "K") {
      return $size_string . "B";
    }
    return $size_string;
  }

}
