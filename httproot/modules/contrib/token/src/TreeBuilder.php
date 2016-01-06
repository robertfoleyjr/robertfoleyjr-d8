<?php

/**
 * @file
 * Contains \Drupal\token\TreeBuilder.
 */

namespace Drupal\token;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;

class TreeBuilder implements TreeBuilderInterface {

  /**
   * @var \Drupal\token\Token
   */
  protected $tokenService;

  /**
   * @var \Drupal\token\TokenEntityMapperInterface
   */
  protected $entityMapper;

  /**
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Cache already built trees.
   *
   * @var array
   */
  protected $builtTrees;

  public function __construct(TokenInterface $token_service, TokenEntityMapperInterface $entity_mapper, CacheBackendInterface $cache_backend, LanguageManagerInterface $language_manager) {
    $this->tokenService = $token_service;
    $this->entityMapper = $entity_mapper;
    $this->cacheBackend = $cache_backend;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function buildTree($token_type, array $options = []) {
    $options += [
      'restricted' => FALSE,
      'depth' => 4,
      'data' => [],
      'values' => FALSE,
      'flat' => FALSE,
    ];

    // Do not allow past the maximum token information depth.
    $options['depth'] = min($options['depth'], static::MAX_DEPTH);

    // If $token_type is an entity, make sure we are using the actual token type.
    if ($entity_token_type = $this->entityMapper->getTokenTypeForEntityType($token_type)) {
      $token_type = $entity_token_type;
    }

    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $tree_cid = "token_tree:{$token_type}:{$langcode}:{$options['depth']}";

    // If we do not have this base tree in the static cache, check the cache
    // otherwise generate and store it in the cache.
    if (!isset($this->builtTrees[$tree_cid])) {
      if ($cache = $this->cacheBackend->get($tree_cid)) {
        $this->builtTrees[$tree_cid] = $cache->data;
      }
      else {
        $options['parents'] = [];
        $this->builtTrees[$tree_cid] = $this->getTokenData($token_type, $options);
        $this->cacheBackend->set($tree_cid, $this->builtTrees[$tree_cid], Cache::PERMANENT, [Token::TOKEN_INFO_CACHE_TAG]);
      }
    }

    $tree = $this->builtTrees[$tree_cid];

    // If the user has requested a flat tree, convert it.
    if (!empty($options['flat'])) {
      $tree = $this->flattenTree($tree);
    }

    // Fill in token values.
    if (!empty($options['values'])) {
      $token_values = [];
      foreach ($tree as $token => $token_info) {
        if (!empty($token_info['dynamic']) || !empty($token_info['restricted'])) {
          continue;
        }
        elseif (!isset($token_info['value'])) {
          $token_values[$token_info['token']] = $token;
        }
      }
      if (!empty($token_values)) {
        $token_values = $this->tokenService->generate($token_type, $token_values, $options['data'], [], new BubbleableMetadata());
        foreach ($token_values as $token => $replacement) {
          $tree[$token]['value'] = $replacement;
        }
      }
    }

    return $tree;
  }

  /**
   * {@inheritdoc}
   */
  public function flattenTree(array $tree) {
    $result = [];
    foreach ($tree as $token => $token_info) {
      $result[$token] = $token_info;
      if (isset($token_info['children']) && is_array($token_info['children'])) {
        $result += $this->flattenTree($token_info['children']);
      }
    }
    return $result;
  }

  /**
   * Generate a token tree.
   *
   * @param string $token_type
   *   The token type.
   * @param array $options
   *   An associative array of additional options. See documentation for
   *   TreeBuilderInterface::buildTree() for more information.
   *
   * @return array
   *   The token data for the specified $token_type.
   *
   * @internal
   */
  protected function getTokenData($token_type, array $options) {
    $options += [
      'parents' => [],
    ];

    $info = $this->tokenService->getInfo();
    if ($options['depth'] <= 0 || !isset($info['types'][$token_type]) || !isset($info['tokens'][$token_type])) {
      return [];
    }

    $tree = [];
    foreach ($info['tokens'][$token_type] as $token => $token_info) {
      // Build the raw token string.
      $token_parents = $options['parents'];
      if (empty($token_parents)) {
        // If the parents array is currently empty, assume the token type is its
        // parent.
        $token_parents[] = $token_type;
      }
      elseif (in_array($token, array_slice($token_parents, 1), TRUE)) {
        // Prevent duplicate recursive tokens. For example, this will prevent
        // the tree from generating the following tokens or deeper:
        // [comment:parent:parent]
        // [comment:parent:root:parent]
        continue;
      }

      $token_parents[] = $token;
      if (!empty($token_info['dynamic'])) {
        $token_parents[] = '?';
      }
      $raw_token = '[' . implode(':', $token_parents) . ']';
      $tree[$raw_token] = $token_info;
      $tree[$raw_token]['raw token'] = $raw_token;

      // Add the token's real name (leave out the base token type).
      $tree[$raw_token]['token'] = implode(':', array_slice($token_parents, 1));

      // Add the token's parent as its raw token value.
      if (!empty($options['parents'])) {
        $tree[$raw_token]['parent'] = '[' . implode(':', $options['parents']) . ']';
      }

      // Fetch the child tokens.
      if (!empty($token_info['type'])) {
        $child_options = $options;
        $child_options['depth']--;
        $child_options['parents'] = $token_parents;
        $tree[$raw_token]['children'] = $this->getTokenData($token_info['type'], $child_options);
      }
    }

    return $tree;
  }
}
