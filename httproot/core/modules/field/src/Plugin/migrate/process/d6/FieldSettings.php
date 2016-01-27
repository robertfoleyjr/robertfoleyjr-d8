<?php

/**
 * @file
 * Contains \Drupal\field\Plugin\migrate\process\d6\FieldSettings.
 */

namespace Drupal\field\Plugin\migrate\process\d6;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get the field settings.
 *
 * @MigrateProcessPlugin(
 *   id = "field_settings"
 * )
 */
class FieldSettings extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The cckfield plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $cckPluginManager;

  /**
   * Constructs a FieldSettings plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $cck_plugin_manager
   *   The cckfield plugin manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PluginManagerInterface $cck_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->cckPluginManager = $cck_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate.cckfield')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    list($field_type, $global_settings, $source_field_type) = $value;

    try {
      return $this->cckPluginManager->createInstance($source_field_type)
        ->transformFieldStorageSettings($row);
    }
    catch (PluginNotFoundException $e) {
      return $this->getSettings($field_type, $global_settings);
    }
  }

  /**
   * Merge the default D8 and specified D6 settings.
   *
   * @param string $field_type
   *   The field type.
   * @param array $global_settings
   *   The field settings.
   *
   * @return array
   *   A valid array of settings.
   */
  public function getSettings($field_type, $global_settings) {
    $allowed_values = [];
    if (isset($global_settings['allowed_values'])) {
      $list = explode("\n", $global_settings['allowed_values']);
      $list = array_map('trim', $list);
      $list = array_filter($list, 'strlen');
      switch ($field_type) {
        case 'list_string':
        case 'list_integer':
        case 'list_float':
          foreach ($list as $value) {
            $value = explode("|", $value);
            $allowed_values[$value[0]] = isset($value[1]) ? $value[1] : $value[0];
          }
          break;

        default:
          $allowed_values = $list;
      }
    }

    $settings = array(
      'datetime' => array('datetime_type' => 'datetime'),
      'list_string' => array(
        'allowed_values' => $allowed_values,
      ),
      'list_integer' => array(
        'allowed_values' => $allowed_values,
      ),
      'list_float' => array(
        'allowed_values' => $allowed_values,
      ),
      'boolean' => array(
        'allowed_values' => $allowed_values,
      ),
    );

    return isset($settings[$field_type]) ? $settings[$field_type] : array();
  }

}
