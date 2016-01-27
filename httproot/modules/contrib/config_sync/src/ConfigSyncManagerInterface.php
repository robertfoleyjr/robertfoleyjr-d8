<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncManagerInterface.
 */

namespace Drupal\config_sync;

/**
 * Provides methods for updating site configuration from extensions.
 */
interface ConfigSyncManagerInterface {

  /**
   * Applies a set of changes, creating and updating items in the active site
   * configuration based on changes available in all enabled extensions.
   *
   * @param boolean $safe_only
   *   Whether to apply only changes considered safe to make. Defaults to
   *   TRUE.
   */
  public function updateAll($safe_only = TRUE);

  /**
   * Applies a set of changes, creating and updating items in the active site
   * configuration based on changes available in the original providing
   * extension.
   *
   * @param string $type
   *   The type of extension (module or theme).
   * @param string $name
   *   The machine name of the extension.
   * @param array $changelist
   *   Associative array of configuration changes keyed by the type of change,
   *   with valid types being create and update.
   * @param boolean $safe_only
   *   Whether to apply only changes considered safe to make. Defaults to
   *   TRUE. Used only if $changelist is not specified.
   */
  public function updateExtension($type, $name, array $changelist = array(), $safe_only = TRUE);

}
