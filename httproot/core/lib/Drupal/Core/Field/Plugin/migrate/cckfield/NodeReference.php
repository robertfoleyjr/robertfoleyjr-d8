<?php

/**
 * @file
 * Contains \Drupal\Core\Field\Plugin\migrate\cckfield\NodeReference.
 */

namespace Drupal\Core\Field\Plugin\migrate\cckfield;

use Drupal\migrate\Entity\Migration;
use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Row;

/**
 * @MigrateCckField(
 *   id = "nodereference",
 *   type_map = {
 *     "nodereference" = "entity_reference"
 *   }
 * )
 */
class NodeReference extends ReferenceBase {

  /**
   * @var string
   */
  protected $nodeTypeMigration = 'd6_node_type';

  /**
   * {@inheritdoc}
   */
  protected function entityId() {
    return 'nid';
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldWidgetMap() {
    return [
      'nodereference_select' => 'options_select',
      'nodereference_buttons' => 'options_buttons',
      'nodereference_autocomplete' => 'entity_reference_autocomplete'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldStorageSettings(Row $row) {
    $settings['target_type'] = 'node';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function processFieldInstance(MigrationInterface $migration) {
    parent::processFieldInstance($migration);

    $migration_dependencies = $migration->get('migration_dependencies');
    $migration_dependencies['required'][] = $this->nodeTypeMigration;
    $migration->set('migration_dependencies', $migration_dependencies);
  }

  /**
   * {@inheritdoc}
   */
  public function transformFieldInstanceSettings(Row $row) {
    $source_settings = $row->getSourceProperty('global_settings');
    $settings['handler'] = 'default:node';
    $settings['handler_settings']['target_bundles'] = [];

    $node_types = array_filter($source_settings['referenceable_types']);
    if (!empty($node_types)) {
      $settings['handler_settings']['target_bundles'] = $this->migrateNodeTypes($node_types);
    }
    return $settings;
  }

  /**
   * Look up migrated role IDs from the d6_node_type migration.
   *
   * @param $source_node_types
   *   The source role IDs.
   *
   * @return array
   *   The migrated role IDs.
   */
  protected function migrateNodeTypes($source_node_types) {
    // Configure the migration process plugin to look up migrated IDs from
    // the d6_user_role migration.
    $migration_plugin_configuration = [
      'migration' => $this->nodeTypeMigration,
    ];

    $migration = Migration::create();
    $executable = new MigrateExecutable($migration, new MigrateMessage());
    $row = new Row([], []);
    $migrationPlugin = $this->migratePluginManager
      ->createInstance('migration', $migration_plugin_configuration, $migration);

    $roles = [];
    foreach ($source_node_types as $role) {
      $roles[] = $migrationPlugin->transform($role, $executable, $row, NULL);
    }
    return array_combine($roles, $roles);
  }

}
