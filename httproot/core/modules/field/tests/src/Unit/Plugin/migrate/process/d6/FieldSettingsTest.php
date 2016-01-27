<?php

/**
 * @file
 * Contains \Drupal\Tests\field\Unit\Plugin\migrate\process\d6\FieldSettingsTest.
 */

namespace Drupal\Tests\field\Unit\Plugin\migrate\process\d6;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\field\Plugin\migrate\process\d6\FieldSettings;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Row;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\field\Plugin\migrate\process\d6\FieldSettings
 * @group field
 */
class FieldSettingsTest extends UnitTestCase {

  /**
   * @covers ::getSettings
   *
   * @dataProvider getSettingsProvider
   */
  public function testGetSettings($field_type, $field_settings, $source_field_type, $allowed_values) {
    $cck_plugin_manager = $this->getMockBuilder(MigratePluginManager::class)
      ->disableOriginalConstructor()
      ->getMock();

    $cck_plugin_manager->method('createInstance')
      ->willThrowException(new PluginNotFoundException($source_field_type));

    $plugin = new FieldSettings([], 'd6_field_settings', [], $cck_plugin_manager);

    $executable = $this->getMock(MigrateExecutableInterface::class);
    $row = $this->getMockBuilder(Row::class)
      ->disableOriginalConstructor()
      ->getMock();

    $result = $plugin->transform([$field_type, $field_settings, $source_field_type], $executable, $row, 'foo');
    $this->assertSame($allowed_values, $result['allowed_values']);
  }

  /**
   * Provides field settings for testGetSettings().
   */
  public function getSettingsProvider() {
    return array(
      array(
        'list_integer',
        array('allowed_values' => "1|One\n2|Two\n3"),
        'list_integer',
        array(
          '1' => 'One',
          '2' => 'Two',
          '3' => '3',
        ),
      ),
      array(
        'list_string',
        array('allowed_values' => NULL),
        'list_string',
        array(),
      ),
      array(
        'list_float',
        array('allowed_values' => ""),
        'list_float',
        array(),
      ),
      array(
        'boolean',
        array(),
        'boolean',
        array(),
      ),
    );
  }

}
