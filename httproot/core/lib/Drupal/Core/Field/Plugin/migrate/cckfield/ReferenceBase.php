<?php

/**
 * @file
 * Contains \Drupal\Core\Field\Plugin\migrate\cckfield\ReferenceBase.
 */

namespace Drupal\Core\Field\Plugin\migrate\cckfield;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate_drupal\Plugin\migrate\cckfield\CckFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for entity reference fields.
 */
abstract class ReferenceBase extends CckFieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The migrate plugin manager, configured for lookups in d6_user_roles.
   *
   * @var \Drupal\migrate\Plugin\MigratePluginManager
   */
  protected $migratePluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigratePluginManager $migration_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->migratePluginManager = $migration_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate.process')
    );
  }

  /**
   * Gets the name of the field property which holds the entity ID.
   *
   * @return string
   */
  abstract protected function entityId();

  /**
   * {@inheritdoc}
   */
  public function getFieldFormatterMap() {
    return array();
  }

  /**
   * {@inheritdoc}
   */
  public function processCckFieldValues(MigrationInterface $migration, $field_name, $data) {
    $process = array(
      'plugin' => 'iterator',
      'source' => $field_name,
      'process' => array(
        'target_id' => $this->entityId(),
      ),
    );
    $migration->setProcessOfProperty($field_name, $process);
  }

}
