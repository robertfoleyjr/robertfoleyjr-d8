<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncManager.
 */

namespace Drupal\config_sync;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\config_sync\ConfigSyncListerInterface;
use Drupal\config_sync\ConfigSyncSnapshotterInterface;
use Drupal\config_update\ConfigRevertInterface;

/**
 * Provides methods for updating site configuration from extensions.
 *
 * @todo: Use batch operations for applying updates.
 */
class ConfigSyncManager implements ConfigSyncManagerInterface {

  /**
   * The config reverter.
   *
   * @var \Drupal\config_update\ConfigRevertInterface
   */
  protected $configRevert;

  /**
   * The config sync lister.
   *
   * @var \Drupal\config_sync\ConfigSyncListerInterface
   */
  protected $configSyncLister;

  /**
   * The config sync snapshotter.
   *
   * @var \Drupal\config_sync\ConfigSyncSnapshotterInterface
   */
  protected $configSyncSnapshotter;

  /**
   * The configuration manager.
   *
   * @var \Drupal\Core\Config\ConfigManagerInterface
   */
  protected $configManager;

  /**
   * Constructs a ConfigSyncManager object.
   *
   * @param \Drupal\config_update\ConfigRevertInterface $config_revert
   *   The config reverter.
   * @param \Drupal\config_update\ConfigSyncListerInterface $config_sync_lister
   *   The config lister.
   * @param \Drupal\config_update\ConfigSyncSnapshotterInterface $config_sync_snapshotter
   *   The config lister.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   */
  public function __construct(ConfigRevertInterface $config_revert, ConfigSyncListerInterface $config_sync_lister, ConfigSyncSnapshotterInterface $config_sync_snapshotter, ConfigManagerInterface $config_manager) {
    $this->configRevert = $config_revert;
    $this->configSyncLister = $config_sync_lister;
    $this->configSyncSnapshotter = $config_sync_snapshotter;
    $this->configManager = $config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function updateAll($safe_only = TRUE) {
    $config_list = $this->configSyncLister->getFullChangelist($safe_only);
    foreach ($config_list as $type => $extensions) {
      foreach ($extensions as $name => $changelist) {
        $this->updateExtension($type, $name, $changelist);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateExtension($type, $name, array $changelist = array(), $safe_only = TRUE) {
    // If no change list was passed, load one.
    if (empty($changelist)) {
      $changelist = $this->configSyncLister->getExtensionChangelist($type, $name, $safe_only);
    }
    // Process create changes.
    if (!empty($changelist['create'])) {
      foreach ($changelist['create'] as $item_name) {
        if (!$entity_type = $this->configManager->getEntityTypeIdByName($name)) {
          $entity_type = 'system.simple';
        }
        $this->configRevert->import($entity_type, $item_name);
      }
    }
    // Process update changes.
    if (!empty($changelist['update'])) {
      foreach ($changelist['update'] as $item_name) {
        if (!$entity_type = $this->configManager->getEntityTypeIdByName($name)) {
          $entity_type = 'system.simple';
        }
        $this->configRevert->revert($entity_type, $item_name);
      }
    }
    // Refresh the configuration snapshot.
    $this->configSyncSnapshotter->createExtensionSnapshot($type, $name);
  }
}
