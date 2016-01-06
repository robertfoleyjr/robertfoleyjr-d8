<?php

/**
 * @file
 * Contains \Drupal\token\Controller\TokenDevelController.
 */

namespace Drupal\token\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\token\TokenEntityMapperInterface;
use Drupal\token\TreeBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Devel integration for tokens.
 */
class TokenDevelController extends ControllerBase {

  /**
   * @var \Drupal\token\TreeBuilderInterface
   */
  protected $treeBuilder;

  /**
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $entityMapper;

  public function __construct(TreeBuilderInterface $tree_builder, TokenEntityMapperInterface $entity_mapper) {
    $this->treeBuilder = $tree_builder;
    $this->entityMapper = $entity_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token.tree_builder'),
      $container->get('token.entity_mapper')
    );
  }

  /**
   * Prints the loaded structure of the current entity.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *    A RouteMatch object.
   *
   * @return array
   *    Array of page elements to render.
   */
  public function entityTokens(RouteMatchInterface $route_match) {
    $output = [];

    $parameter_name = $route_match->getRouteObject()->getOption('_token_entity_type_id');
    $entity = $route_match->getParameter($parameter_name);

    if ($entity && $entity instanceof EntityInterface) {
      $output = $this->renderTokenTree($entity);
    }

    return $output;
  }

  /**
   * Render the token tree for the specified entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which the token tree should be rendered.
   *
   * @return array
   *   Render array of the token tree for the $entity.
   *
   * @see static::entityLoad
   */
  protected function renderTokenTree(EntityInterface $entity) {
    $this->moduleHandler()->loadInclude('token', 'pages.inc');
    $entity_type = $entity->getEntityTypeId();

    $header = [
      $this->t('Token'),
      $this->t('Value'),
    ];
    $rows = [];

    $token_type = $this->entityMapper->getTokenTypeForEntityType($entity_type);
    $options = [
      'flat' => TRUE,
      'values' => TRUE,
      'data' => [$token_type => $entity],
    ];

    $tree = $this->treeBuilder->buildTree($token_type, $options);
    foreach ($tree as $token => $token_info) {
      if (!empty($token_info['restricted'])) {
        continue;
      }
      if (!isset($token_info['value']) && !empty($token_info['parent']) && !isset($tree[$token_info['parent']]['value'])) {
        continue;
      }
      $row = _token_token_tree_format_row($token, $token_info);
      unset($row['data']['description']);
      unset($row['data']['name']);
      $rows[] = $row;
    }

    $build['tokens'] = [
      '#theme' => 'tree_table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['class' => ['token-tree']],
      '#empty' => $this->t('No tokens available.'),
      '#attached' => [
        'library' => ['token/token'],
      ],
    ];

    return $build;
  }
}
