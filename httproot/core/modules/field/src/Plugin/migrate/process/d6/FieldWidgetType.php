<?php

/**
 * @file
 * Contains \Drupal\field\Plugin\migrate\process\d6\FieldWidgetType.
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
 * Get the field instance widget type.
 *
 * @MigrateProcessPlugin(
 *   id = "field_instance_widget_type"
 * )
 */
class FieldWidgetType extends ProcessPluginBase implements ContainerFactoryPluginInterface {

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
    $source_widget_type = $row->getSourceProperty('widget_type');
    $source_field_type = $row->getSourceProperty('type');

    try {
      return $this->cckPluginManager->createInstance($source_field_type)
        ->transformWidgetType($row);
    }
    catch (PluginNotFoundException $e) {
      return $this->getWidget($source_widget_type);
    }
  }

  /**
   * Returns widget for a given source widget type.
   *
   * @param $source_widget_type
   *
   * @return string|null
   */
  protected function getWidget($source_widget_type) {
    $map = [
      'number' => 'number',
      'email_textfield' => 'email_default',
      'date_select' => 'datetime_default',
      'date_text' => 'datetime_default',
      'imagefield_widget' => 'image_image',
      'phone_textfield' => 'telephone_default',
      'optionwidgets_onoff' => 'boolean_checkbox',
      'optionwidgets_buttons' => 'options_buttons',
      'optionwidgets_select' => 'options_select',
    ];
    return isset($map[$source_widget_type]) ? $map[$source_widget_type] : NULL;
  }

}
