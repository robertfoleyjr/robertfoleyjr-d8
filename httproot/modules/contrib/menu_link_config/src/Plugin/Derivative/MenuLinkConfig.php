<?php

/**
 * @file
 * Contains \Drupal\menu_link_config\Plugin\Derivative\MenuLinkConfig.
 */

namespace Drupal\menu_link_config\Plugin\Derivative;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MenuLinkConfig implements ContainerDeriverInterface {

  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->getDerivativeDefinitions($base_plugin_definition);
    }
    if (isset($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $links = [];
    // Get all menu link config entities.
    $entities = $this->entityManager->getStorage('menu_link_config')->loadMultiple(NULL);
    foreach ($entities as $id => $menu_link_config) {
      /** @var \Drupal\menu_link_config\MenuLinkConfigInterface $menu_link_config */
      $links[$id] = $menu_link_config->getPluginDefinition() + $base_plugin_definition;
    }

    return $links;
  }

}
