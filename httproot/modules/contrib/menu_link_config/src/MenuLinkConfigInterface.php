<?php

/**
 * @file
 * Contains \Drupal\menu_link_config\MenuLinkConfigInterface.
 */

namespace Drupal\menu_link_config;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

interface MenuLinkConfigInterface extends ConfigEntityInterface {

  public function getPluginDefinition();

  /**
   * @return bool
   */
  public function isEnabled();

}

