<?php

/**
 * @file
 * Contains \Drupal\menu_link_config\Entity\MenuLinkConfig.
 */

namespace Drupal\menu_link_config\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Url;
use Drupal\menu_link_config\MenuLinkConfigInterface;

/**
 * Defines the menu link config entity.
 *
 * @ConfigEntityType(
 *   id = "menu_link_config",
 *   label = @Translation("Menu link config"),
 *   handlers = {
 *     "access" = "\Drupal\Core\Entity\EntityAccessControlHandler",
 *     "form" = {
 *       "default" = "\Drupal\menu_link_config\Plugin\Menu\Form\MenuLinkConfigForm"
 *     }
 *   },
 *   admin_permission = "administer menu link config",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "status" = "status"
 *   },
 * )
 */
class MenuLinkConfig extends ConfigEntityBase implements MenuLinkConfigInterface {

  public $id;
  public $title;
  public $url;
  public $route_name;
  public $route_parameters;
  public $options;
  public $expanded;
  public $menu_name;
  public $parent;
  public $weight;
  public $description;
  public $enabled;

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteName() {
    return $this->route_name ?: '<front>';
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters() {
    return $this->route_parameters ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOptions() {
    return $this->options ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlObject() {
    return new Url($this->getRouteName(), $this->getRouteParameters(), $this->getOptions());
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $plugin_definition = [];
    $plugin_definition['title'] = $this->getTitle();
    $plugin_definition['route_name'] = $this->getRouteName();
    $plugin_definition['route_parameters'] = $this->getRouteParameters();
    $plugin_definition['options'] = $this->getOptions();
    $plugin_definition['menu_name'] = $this->getMenuName();
    $plugin_definition['parent'] = $this->getParent();
    $plugin_definition['enabled'] = $this->isEnabled() ? 1 : 0;
    $plugin_definition['weight'] = $this->getWeight();
    $plugin_definition['metadata']['entity_id'] = $this->id();
    $plugin_definition['class'] = 'Drupal\menu_link_config\Plugin\Menu\MenuLinkConfig';
    $plugin_definition['form_class'] = 'Drupal\menu_link_config\Plugin\Menu\Form\MenuLinkConfigForm';

    return $plugin_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function isExpanded() {
    return $this->expanded;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuName() {
    return $this->menu_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getParent() {
    return $this->parent;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return 'menu_link_config:' . $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->enabled;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Convert route parameters that are entity IDs to UUIDs.
    $entity_manager = $this->entityManager();
    $this->processEntityRouteParameters($this, function ($entity_type_id, $value) use ($entity_manager) {
      $entity = $entity_manager->getStorage($entity_type_id)->load($value);
      // Entity validation should have ensured that this entity in fact exists
      // but we try to avoid incomprehensible fatals at all costs.
      if ($entity instanceof EntityInterface) {
        return $entity->uuid();
      }
    });
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Re-convert entity UUID route parameters back into IDs. This is important
    // if the entity is used later in the same request.
    /** @see static::preSave() */
    $entity_manager = $this->entityManager();
    $this->processEntityRouteParameters($this, function ($entity_type_id, $value) use ($entity_manager) {
      $entity = $entity_manager->loadEntityByUuid($entity_type_id, $value);
      // Entity validation should have ensured that this entity in fact exists
      // but we try to avoid incomprehensible fatals at all costs.
      if ($entity instanceof EntityInterface) {
        return $entity->id();
      }
    });

    parent::postSave($storage, $update);

    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager */
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

    // The menu link can just be updated if there is already an menu link entry
    // on both entity and menu link plugin level.
    if ($update && $menu_link_manager->getDefinition($this->getPluginId())) {
      $menu_link_manager->updateDefinition($this->getPluginId(), $this->getPluginDefinition(), FALSE);
    }
    else {
      $menu_link_manager->addDefinition($this->getPluginId(), $this->getPluginDefinition());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postLoad(EntityStorageInterface $storage, array &$entities) {
    // Re-convert entity UUID route parameters back into IDs.
    /** @see static::preSave() */
    $entity_manager = \Drupal::entityManager();
    foreach ($entities as $entity) {
      static::processEntityRouteParameters($entity, function ($entity_type_id, $value) use ($entity_manager) {
        $entity = $entity_manager->loadEntityByUuid($entity_type_id, $value);
        // Entity validation should have ensured that this entity in fact exists
        // but we try to avoid incomprehensible fatals at all costs.
        if ($entity instanceof EntityInterface) {
          return $entity->id();
        }
      });
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    // Re-convert entity UUID route parameters back into IDs.
    /** @see static::preSave() */
    $entity_manager = $this->entityManager();
    $this->processEntityRouteParameters($this, function ($entity_type_id, $value) use ($entity_manager) {
      $entity = $entity_manager->loadEntityByUuid($entity_type_id, $value);
      // Entity validation should have ensured that this entity in fact exists
      // but we try to avoid incomprehensible fatals at all costs.
      if ($entity instanceof EntityInterface) {
        return $entity->id();
      }
    });
  }


  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    return ['menu_link_plugin' => 'menu_link_config:' . $this->id()];
  }

  /**
   * Processes entity route parameters for a given menu link.
   *
   * @param \Drupal\menu_link_config\MenuLinkConfigInterface|\Drupal\Core\Menu\MenuLinkInterface $menu_link
   *   The menu link to process. This is being passed in to support
   *   static::postLoad()
   * @param callable $processor
   *   An entity route parameter processor that gets the entity type ID and the
   *   current route parameter value as arguments and can return the processed
   *   route parameter value or NULL if it does not want to alter the value.
   */
  public static function processEntityRouteParameters($menu_link, $processor) {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = \Drupal::service('router.route_provider')->getRouteByName($menu_link->getRouteName());
    $route_parameters = $menu_link->getRouteParameters();
    $changed = FALSE;
    foreach ($route_parameters as $name => $value) {
      $parameter_info = $route->getOption('parameters');
      // Ignore route parameters that are not entity IDs.
      if (isset($parameter_info[$name]['type']) && (strpos($parameter_info[$name]['type'], 'entity:') === 0)) {
        $entity_type_id = substr($parameter_info[$name]['type'], 7);
        $new_value = $processor($entity_type_id, $value);
        if (isset($new_value)) {
          $route_parameters[$name] = $new_value;
          $changed = TRUE;
        }
      }
    }

    if ($changed) {
      $menu_link->set('route_parameters', $route_parameters);
    }
  }

}
