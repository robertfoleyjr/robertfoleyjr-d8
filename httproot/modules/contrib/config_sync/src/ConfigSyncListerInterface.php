<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncListerInterface.
 */

namespace Drupal\config_sync;

/**
 * Provides methods related to config listing.
 */
interface ConfigSyncListerInterface {

  /**
   * Returns a change list for all installed extensions.
   *
   * @param boolean $safe_only
   *   Whether to return only changes considered safe to make. Defaults to
   *   TRUE. What is considered safe is up to the implementing class.
   *
   * @return array
   *   Associative array of configuration changes keyed by extension type
   *   (module or theme) in which values are arrays keyed by extension name.
   */
  public function getFullChangelist($safe_only = TRUE);

  /**
   * Returns a change list for a given module or theme.
   *
   * @param string $type
   *   The type of extension (module or theme).
   * @param string $name
   *   The machine name of the extension.
   * @param boolean $safe_only
   *   Whether to return only changes considered safe to make. Defaults to
   *   TRUE. What is considered safe is up to the implementing class.
   *
   * @return array
   *   Associative array of configuration changes keyed by the type of change,
   *   with valid types being create and update.
   */
  public function getExtensionChangelist($type, $name, $safe_only = TRUE);

}
