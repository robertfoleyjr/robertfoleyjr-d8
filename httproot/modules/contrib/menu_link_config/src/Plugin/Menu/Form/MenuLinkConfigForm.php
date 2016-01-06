<?php

/**
 * @file
 * Contains \Drupal\menu_link_config\Plugin\Menu\Form\MenuLinkConfigForm.
 */

namespace Drupal\menu_link_config\Plugin\Menu\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\Form\MenuLinkFormInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Core\Routing\MatchingRouteNotFoundException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MenuLinkConfigForm extends EntityForm implements MenuLinkFormInterface {

  /**
   * The edited menu link.
   *
   * @var \Drupal\menu_link_config\Entity\MenuLinkConfig
   */
  protected $entity;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The menu parent form selector.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuParentSelector;

  /**
   * Constructs a new MenuLinkConfigForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link manager.
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_form_selector
   *   The menu parent form selector.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   */
  public function __construct(EntityManagerInterface $entity_manager, MenuLinkManagerInterface $menu_link_manager, MenuParentFormSelectorInterface $menu_parent_form_selector, AccessManagerInterface $access_manager, AccountInterface $account, AliasManagerInterface $alias_manager, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation) {
    $this->entityManager = $entity_manager;
    $this->menuLinkManager = $menu_link_manager;
    $this->menuParentSelector = $menu_parent_form_selector;
    $this->accessManager = $access_manager;
    $this->account = $account;
    $this->pathAliasManager = $alias_manager;
    $this->setModuleHandler($module_handler);
    $this->setStringTranslation($string_translation);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.parent_form_selector'),
      $container->get('access_manager'),
      $container->get('current_user'),
      $container->get('path.alias_manager'),
      $container->get('module_handler'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setMenuLinkInstance(MenuLinkInterface $menu_link) {
    // Load the entity for the entity form. Loading by entity ID is much faster
    // than loading by UUID, so use that ID if we have it.
    $metadata = $menu_link->getMetaData();
    if (!empty($metadata['entity_id'])) {
      $this->entity = $this->entityManager->getStorage('menu_link_config')->load($metadata['entity_id']);
    }
    else {
      // Fallback to the loading by UUID.
      $links = $this->entityManager->getStorage('menu_link_config')->loadByProperties(['uuid' => $menu_link->getDerivativeId()]);
      $this->entity = reset($links);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $this->setOperation('default');
    $this->init($form_state);

    return $this->form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->doValidate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    parent::form($form, $form_state);
    $form['#title'] = $this->t('Edit menu link %title', ['%title' => $this->entity->getTitle()]);

    // Put the title field first.
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $this->entity->getTitle(),
      '#weight' => -10,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#maxlength' => 128,
      '#machine_name' => [
        'source' => ['title'],
        'exists' => '\Drupal\menu_link_config\Controller\MenuController::getMenuLink',
      ],
      '#disabled' => !$this->entity->isNew(),
      '#weight' => -9,
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Shown when hovering over the menu link.'),
      '#default_value' => $this->entity->getDescription(),
      '#weight' => -5,
    ];

    $link = [
      '#type' => 'link',
      '#title' => $this->entity->getTitle(),
    ] + $this->entity->getUrlObject()->toRenderArray();
    $form['info'] = [
      'link' => $link,
      '#type' => 'item',
      '#title' => $this->t('Link'),
    ];

    // We always show the internal path here.
    /** @var \Drupal\Core\Url $url */
    $url = $this->entity->getUrlObject();
    if ($url->isExternal()) {
      $default_value = $url->toString();
    }
    elseif ($url->getRouteName() == '<front>') {
      // The default route for new entities is <front>, but we just want an
      // empty form field.
      $default_value = '';
    }
    else {
      // @todo Url::getInternalPath() calls UrlGenerator::getPathFromRoute()
      // which need a replacement since it is deprecated.
      // https://www.drupal.org/node/2307061
      try {
        $default_value = $url->getInternalPath();
      }
      catch (\Exception $e) {
        $default_value = 'broken path';
      }
      // @todo Add a helper method to Url to render just the query string and
      // fragment. https://www.drupal.org/node/2305013
      $options = $url->getOptions();
      if (isset($options['query'])) {
        $default_value .= $options['query'] ? ('?' . UrlHelper::buildQuery($options['query'])) : '';
      }
      if (isset($options['fragment']) && $options['fragment'] !== '') {
        $default_value .= '#' . $options['fragment'];
      }
    }
    $form['url'] = [
      '#title' => $this->t('Link path'),
      '#type' => 'textfield',
      '#description' => $this->t('The path for this menu link. This can be an internal Drupal path such as %add-node or an external URL such as %drupal. Enter %front to link to the front page.', ['%front' => '<front>', '%add-node' => '/node/add', '%drupal' => 'http://drupal.org']),
      '#default_value' => $default_value,
      '#required' => TRUE,
      '#weight' => -2,
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable menu link'),
      '#description' => $this->t('Menu links that are not enabled will not be listed in any menu.'),
      '#default_value' => $this->entity->status(),
    ];

    $form['expanded'] = [
      '#type' => 'checkbox',
      '#title' => t('Show as expanded'),
      '#description' => $this->t('If selected and this menu link has children, the menu will always appear expanded.'),
      '#default_value' => $this->entity->isExpanded(),
    ];

    $menu_parent = $this->entity->getMenuName() . ':' . $this->entity->getParent();
    $form['menu_parent'] = $this->menuParentSelector->parentSelectElement($menu_parent, $this->entity->getPluginId());
    $form['menu_parent']['#title'] = $this->t('Parent link');
    $form['menu_parent']['#description'] = $this->t('The maximum depth for a link and all its children is fixed. Some menu links may not be available as parents if selecting them would exceed this limit.');
    $form['menu_parent']['#attributes']['class'][] = 'menu-title-select';

    $delta = max(abs($this->entity->getWeight()), 50);
    $form['weight'] = [
      '#type' => 'number',
      '#min' => -$delta,
      '#max' => $delta,
      '#default_value' => $this->entity->getWeight(),
      '#title' => $this->t('Weight'),
      '#description' => $this->t('Link weight among links in the same menu at the same depth. In the menu, the links with high weight will sink and links with a low weight will be positioned nearer the top.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\menu_link_config\Entity\MenuLinkConfig $entity */
    $entity = parent::buildEntity($form, $form_state);
    $new_definition = $this->extractFormValues($form, $form_state);

    $entity->id = $new_definition['metadata']['entity_id'];
    $entity->parent = $new_definition['parent'];
    $entity->menu_name = $new_definition['menu_name'];
    $entity->setStatus(!$new_definition['hidden']);
    $entity->expanded = $new_definition['expanded'];
    $entity->weight = $new_definition['weight'];

    $entity->url = $new_definition['url'];
    $entity->route_name= $new_definition['route_name'];
    $entity->route_parameters = $new_definition['route_parameters'];
    $entity->options = $new_definition['options'];

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    $this->doValidate($form, $form_state);

    parent::validate($form, $form_state);
  }
  /**
   * Validates the form, both on the menu link edit and content menu link form.
   *
   * $form is not currently used, but passed here to match the normal form
   * validation method signature.
   *
   * @param array $form
   *   A nested array form elements comprising the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function doValidate(array $form, FormStateInterface $form_state) {
    $extracted = $this->extractUrl($form_state->getValue('url'));

    // If both URL and route_name are empty, the entered value is not valid.
    $valid = FALSE;
    if ($extracted['url']) {
      // This is an external link.
      $valid = TRUE;
    }
    elseif ($extracted['route_name']) {
      // Users are not allowed to add a link to a page they cannot access.
      $valid = $this->accessManager->checkNamedRoute($extracted['route_name'], $extracted['route_parameters'], $this->account);
    }
    if (!$valid) {
      $form_state->setErrorByName('url', $this->t("The path '@link_path' is either invalid or you do not have access to it.", ['@link_path' => $form_state->getValue('url')]));
    }
    elseif ($extracted['route_name']) {
      // The user entered a Drupal path.
      $normal_path = $this->pathAliasManager->getPathByAlias($extracted['path']);
      if ($extracted['path'] != $normal_path) {
        drupal_set_message($this->t('The menu system stores system paths only, but will use the URL alias for display. %link_path has been stored as %normal_path', [
              '%link_path' => $extracted['path'],
              '%normal_path' => $normal_path,
            ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // The entity is rebuilt in parent::submit().
    $menu_link = $this->entity;
    $saved = $menu_link->save();

    if ($saved) {
      drupal_set_message($this->t('The menu link has been saved.'));
      $form_state->setRedirect(
        'entity.menu.edit_form',
        array('menu' => $menu_link->getMenuName())
      );
    }
    else {
      drupal_set_message($this->t('There was an error saving the menu link.'), 'error');
      $form_state['rebuild'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $new_definition = $this->extractFormValues($form, $form_state);

    return $this->menuLinkManager->updateDefinition($this->entity->getPluginId(), $new_definition);
  }

  /**
   * Breaks up a user-entered URL or path into all the relevant parts.
   *
   * @param string $url
   *   The user-entered URL or path.
   *
   * @return array
   *   The extracted parts.
   */
  protected function extractUrl($url) {
    $extracted = UrlHelper::parse($url);
    $external = UrlHelper::isExternal($url);
    if ($external) {
      $extracted['url'] = $extracted['path'];
      $extracted['route_name'] = NULL;
      $extracted['route_parameters'] = [];
    }
    else {
      $extracted['url'] = '';
      // If the path doesn't match a Drupal path, the route should end up empty.
      $extracted['route_name'] = NULL;
      $extracted['route_parameters'] = [];
      try {
        // Find the route_name.
        $url_obj = \Drupal::pathValidator()->getUrlIfValid($extracted['path']);
        if ($url_obj) {
          $extracted['route_name'] = $url_obj->getRouteName();
          $extracted['route_parameters'] = $url_obj->getRouteParameters();
        }
      }
      catch (MatchingRouteNotFoundException $e) {
        // The path doesn't match a Drupal path.
      }
      catch (ParamNotConvertedException $e) {
        // A path like node/99 matched a route, but the route parameter was
        // invalid (e.g. node with ID 99 does not exist).
      }
    }
    return $extracted;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(array &$form, FormStateInterface $form_state) {
    $new_definition = [];
    $new_definition['title'] = $form_state->getValue('title');

    $extracted = $this->extractUrl($form_state->getValue('url'));
    $new_definition['url'] = $extracted['url'];
    $new_definition['route_name'] = $extracted['route_name'];
    $new_definition['route_parameters'] = $extracted['route_parameters'];
    $new_definition['options'] = [];
    if ($extracted['query']) {
      $new_definition['options']['query'] = $extracted['query'];
    }
    if ($extracted['fragment']) {
      $new_definition['options']['fragment'] = $extracted['fragment'];
    }

    $new_definition['description'] = $form_state->getValue('description');
    $new_definition['hidden'] = !$form_state->getValue('enabled');
    $new_definition['weight'] = (int) $form_state->getValue('weight');
    $new_definition['expanded'] = (bool) $form_state->getValue('expanded');
    list($menu_name, $parent) = explode(':', $form_state->getValue('menu_parent'), 2);
    if (!empty($menu_name)) {
      $new_definition['menu_name'] = $menu_name;
    }
    if (isset($parent)) {
      $new_definition['parent'] = $parent;
    }
    $new_definition['metadata']['entity_id'] = $form_state->getValue('id');
    return $new_definition;
  }

}
