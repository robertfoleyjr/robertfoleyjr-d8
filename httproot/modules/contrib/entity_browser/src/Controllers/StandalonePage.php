<?php

/**
 * @file
 * Contains \Drupal\entity_browser\Controllers\StandalonePage.
 */

namespace Drupal\entity_browser\Controllers;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Standalone entity browser page.
 */
class StandalonePage extends ControllerBase {

  /**
   * Current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The browser storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $browserStorage;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs StandalonePage route controller.
   *
   * @param RouteMatchInterface $route_match
   *   Current route match service.
   * @param EntityManagerInterface $entity_manager
   *   Entity manager service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request.
   */
  public function __construct(RouteMatchInterface $route_match, EntityManagerInterface $entity_manager, Request $request) {
    $this->currentRouteMatch = $route_match;
    $this->browserStorage = $entity_manager->getStorage('entity_browser');
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match'),
      $container->get('entity.manager'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * Test implementation of standalone entity browser page.
   */
  public function page() {
    $browser = $this->loadBrowser();

    // The original path is sometimes needed: ie for views arguments.
    if ($original_path = $this->request->get('original_path')) {
      $browser->addAdditionalWidgetParameters(['path_parts' => explode('/', $original_path)]);
    }

    return $this->entityFormBuilder()->getForm($browser, 'entity_browser');
  }

  /**
   * Standalone entity browser title callback.
   */
  public function title() {
    $browser = $this->loadBrowser();
    return Xss::filter($browser->label());
  }

  /**
   * Loads entity browser object for this page.
   *
   * @return \Drupal\entity_browser\EntityBrowserInterface
   *   Loads the entity browser object
   */
  protected function loadBrowser() {
    /** @var $route \Symfony\Component\Routing\Route */
    $route = $this->currentRouteMatch->getRouteObject();
    /** @var $browser \Drupal\entity_browser\EntityBrowserInterface */
    return $this->browserStorage->load($route->getDefault('entity_browser_id'));
  }

}
