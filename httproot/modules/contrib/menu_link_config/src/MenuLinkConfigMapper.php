<?php
/**
 * @file
 * Contains \Drupal\menu_link_config\MenuLinkConfigMapper.
 */

namespace Drupal\menu_link_config;

use Drupal\config_translation\ConfigEntityMapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Provides configuration mapper for configuration menu links.
 */
class MenuLinkConfigMapper extends ConfigEntityMapper {

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    return ['menu_link_plugin' => 'menu_link_config:' . $this->entity->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function populateFromRequest(Request $request) {
    // We cannot call the parent implementation because the request does not
    // have a 'menu_link_config' attribute, so we have to duplicate
    // ConfigNamesMapper::populateFromRequest() here.
    if ($request->attributes->has('langcode')) {
      $this->langcode = $request->attributes->get('langcode');
    }
    else {
      $this->langcode = NULL;
    }

    /** @var \Drupal\menu_link_config\Plugin\Menu\MenuLinkConfig $plugin */
    $plugin = $request->attributes->get('menu_link_plugin');
    $this->setEntity($plugin->getEntity());
  }


  /**
   * {@inheritdoc}
   */
  protected function processRoute(Route $route) {
    // Add entity upcasting information.
    $parameters = $route->getOption('parameters') ?: array();
    $parameters += array(
      'menu_link_plugin' => array(
        'type' => 'menu_link_plugin',
      )
    );
    $route->setOption('parameters', $parameters);
  }

}
