<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncStorageComparer.
 */

namespace Drupal\config_sync;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\config_update\ConfigDiffInterface;

/**
 * Defines a config storage comparer.
 */
class ConfigSyncStorageComparer extends StorageComparer {

  /**
   * The config differ.
   *
   * @var \Drupal\config_update\ConfigDiffInterface
   */
  protected $configDiff;

  /**
   * Constructs the Configuration Sync storage comparer.
   *
   * @param \Drupal\Core\Config\StorageInterface $source_storage
   *   Storage object used to read configuration.
   * @param \Drupal\Core\Config\StorageInterface $target_storage
   *   Storage object used to write configuration.
   * @param \Drupal\Core\Config\ConfigManagerInterface $config_manager
   *   The configuration manager.
   * @param \Drupal\config_update\ConfigDiffInterface $config_diff
   *   The config differ.
   */
  public function __construct(StorageInterface $source_storage, StorageInterface $target_storage, ConfigManagerInterface $config_manager, ConfigDiffInterface $config_diff) {
    parent::__construct($source_storage, $target_storage, $config_manager);
    $this->configDiff = $config_diff;
  }

  /**
   * Overrides \Drupal\Core\Config\StorageComparer::addChangelistUpdate() to
   * use the comparison provided by \Drupal\config_update\ConfigDiffInterface
   * to determine available updates.
   *
   * \Drupal\config_update\ConfigDiffInterface::same() includes normalization
   * that may reduce false positives resulting from either expected differences
   * between provided and installed configuration (for example, the presence or
   * absence of a UUID value) or incidental ordering differences.
   *
   * The list of updates is sorted so that dependencies are created before
   * configuration entities that depend on them. For example, field storages
   * should be updated before fields.
   *
   * @param string $collection
   *   The storage collection to operate on.
   */
  protected function addChangelistUpdate($collection) {
    foreach (array_intersect($this->sourceNames[$collection], $this->targetNames[$collection]) as $name) {
      $source_data = $this->getSourceStorage($collection)->read($name);
      $target_data = $this->getTargetStorage($collection)->read($name);
      if (!$this->configDiff->same($source_data, $target_data)) {
        $this->addChangeList($collection, 'update', array($name));
      }
    }
  }

}
