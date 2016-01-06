<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncSnapshotterInterface.
 */

namespace Drupal\config_sync;

/**
 * The ConfigSyncSnapshotter provides helper functions for taking snapshots of
 * extension-provided configuration.
 */
interface ConfigSyncSnapshotterInterface {

  /**
   * Creates a snapshot of the configuration provided by a given extension.
   *
   * @param string $type
   *   The type of extension (module or theme).
   * @param string $name
   *   The machine name of the extension.
   */
  public function createExtensionSnapshot($type, $name);

  /**
   * Creates a snapshot of the configuration provided by a list of
   * extensions.
   *
   * @param string $type
   *   The type of extension (module or theme).
   * @param string[] $extension_names
   *   The machine names of the extensions.
   */
  public function createExtensionSnapshotMultiple($type, array $extension_names);

  /**
   * Creates a snapshot of all enabled modules and themes.
   */
  public function createFullSnapshot();

  /**
   * Deletes all records from the snapshot.
   */
  public function deleteSnapshot();

}
