<?php

/**
 * @file
 * Contains \Drupal\menu_link_config\Controller\MenuController.
 */

namespace Drupal\menu_link_config\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\system\MenuInterface;

class MenuController extends ControllerBase {

  /**
   * Provides the config menu link creation form.
   *
   * @param \Drupal\system\MenuInterface $menu
   *   An entity representing a custom menu.
   *
   * @return array
   *   Returns the menu link creation form.
   */
  public function addLink(MenuInterface $menu) {
    $menu_link = $this->entityManager()->getStorage('menu_link_config')->create([
        'id' => '',
        'parent' => '',
        'menu_name' => $menu->id(),
        'bundle' => 'menu_link_config',
      ]);
    return $this->entityFormBuilder()->getForm($menu_link);
  }

  public static function getMenuLink($id) {
    return (bool) \Drupal::entityManager()->getStorage('menu_link_config')->load($id);
  }

}
