<?php

/**
 * @file
 * Contains \Drupal\token\TreeBuilderInterface.
 */

namespace Drupal\token;

interface TreeBuilderInterface {

  /**
   * The maximum depth for token tree recursion.
   */
  const MAX_DEPTH = 9;

  /**
   * Build a tree array of tokens used for themeing or information.
   *
   * @param string $token_type
   *   The token type.
   * @param array $options
   *   (optional) An associative array of additional options, with the following
   *   elements:
   *   - 'flat' (defaults to FALSE): Set to true to generate a flat list of
   *     token information. Otherwise, child tokens will be inside the
   *     'children' parameter of a token.
   *   - 'restricted' (defaults to FALSE): Set to true to how restricted tokens.
   *   - 'depth' (defaults to 4): Maximum number of token levels to recurse.
   *
   * @return array
   *   The token information constructed in a tree or flat list form depending
   *   on $options['flat'].
   */
  public function buildTree($token_type, array $options = []);

  /**
   * Flatten a token tree.
   *
   * @param array $tree
   *   The tree array as returned by TreeBuilderInterface::buildTree().
   *
   * @return array
   *   The flattened version of the tree.
   */
  public function flattenTree(array $tree);
}
