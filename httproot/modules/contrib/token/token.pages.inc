<?php

/**
 * @file
 * User page callbacks for the token module.
 */
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;

/**
 * Theme a link to a token tree shown as a dialog.
 */
function template_preprocess_token_tree_link(&$variables) {
  if (empty($variables['text'])) {
    $variables['text'] = t('Browse available tokens.');
  }

  $variables['#attached']['library'][] = 'core/drupal.dialog.ajax';
  $variables['#attached']['library'][] = 'token/token';
  $variables['options']['attributes']['class'][] = 'token-dialog';
  $variables['options']['attributes']['class'][] = 'use-ajax';

  $info = token_theme();
  $tree_variables = array_intersect_key($variables, $info['token_tree']['variables']);
  $tree_variables = DiffArray::diffAssocRecursive($tree_variables, $info['token_tree']['variables']);
  if (!isset($variables['options']['query']['options'])) {
    $variables['options']['query']['options'] = array();
  }
  $variables['options']['query']['options'] += $tree_variables;

  // Because PHP converts query strings with arrays into a different syntax on
  // the next request, the options have to be encoded with JSON in the query
  // string so that we can reliably decode it for token comparison.
  $variables['options']['query']['options'] = Json::encode($variables['options']['query']['options']);

  // Set the token tree to open in a separate window.
  $variables['options']['attributes'] += [
    'data-dialog-type' => 'dialog',
    'data-dialog-options' => json_encode([
      'width' => 600,
      'height' => 400,
      'position' => ['my' => 'right bottom', 'at' => 'right bottom'],
      'draggable' => TRUE,
      'autoResize' => FALSE,
    ]),
   ];

  $variables['link'] = Link::createFromRoute($variables['text'], 'token.tree', [], $variables['options'])->toRenderable();
  $variables['url'] = new Url('token.tree', [], $variables['options']);
  $variables['attributes'] = $variables['options']['attributes'];
}

/**
 * Theme a tree table.
 *
 * @ingroup themeable
 */
function theme_tree_table($variables) {
  foreach ($variables['rows'] as &$row) {
    $row += array('class' => array());
    if (!empty($row['parent'])) {
      $row['class'][] = 'child-of-' . $row['parent'];
      unset($row['parent']);
    }
  }
  $output = [];
  $output['#attached']['library'][] = 'token/token';
  if (!empty($variables['rows'])) {
    $output['#attached']['library'][] = 'token/jquery.treeTable';
  }
  $output['#type'] = 'table';
  foreach ($variables as $key => $value) {
    $output['#' . $key] = $value;
  }
  /** @var \Drupal\Core\Render\RendererInterface $renderer */
  $renderer = \Drupal::service('renderer');
  return $renderer->render($output);
}

/**
 * Provide a 'tree' display of nested tokens.
 *
 * @ingroup themeable
 */
function theme_token_tree($variables) {
  /** @var \Drupal\token\Token $token_service */
  $token_service = \Drupal::token();
  $token_types = $variables['token_types'];
  $info = $token_service->getInfo();

  if ($token_types == 'all') {
    $token_types = array_keys($info['types']);
  }
  elseif ($variables['global_types']) {
    $token_types = array_merge($token_types, $token_service->getGlobalTokenTypes());
  }

  $element = array(
    /*'#cache' => array(
      'cid' => 'tree-rendered:' . hash('sha256', serialize(array('token_types' => $token_types, 'global_types' => NULL) + $variables)),
      'tags' => array(Token::TOKEN_INFO_CACHE_TAG),
    ),*/
  );

  // @todo Find a way to use the render cache for this.
  /*if ($cached_output = token_render_cache_get($element)) {
    return $cached_output;
  }*/

  $options = array(
    'flat' => TRUE,
    'restricted' => $variables['show_restricted'],
    'depth' => $variables['recursion_limit'],
  );
  $multiple_token_types = (count($token_types) > 1);
  $rows = array();

  foreach ($info['types'] as $type => $type_info) {
    if (!in_array($type, $token_types)) {
      continue;
    }

    if ($multiple_token_types) {
      $row = _token_token_tree_format_row($type, $type_info, TRUE);
      unset($row['data']['value']);
      $rows[] = $row;
    }

    $tree = \Drupal::service('token.tree_builder')->buildTree($type, $options);
    foreach ($tree as $token => $token_info) {
      if (!empty($token_info['restricted']) && empty($variables['show_restricted'])) {
        continue;
      }
      if ($multiple_token_types && !isset($token_info['parent'])) {
        $token_info['parent'] = $type;
      }
      $row = _token_token_tree_format_row($token, $token_info);
      unset($row['data']['value']);
      $rows[] = $row;
    }
  }

  $element += array(
    '#theme' => 'tree_table',
    '#header' => array(
      t('Name'),
      t('Token'),
      t('Description'),
    ),
    '#rows' => $rows,
    '#attributes' => array('class' => array('token-tree')),
    '#empty' => t('No tokens available'),
  );

  if ($variables['click_insert']) {
    $element['#caption'] = t('Click a token to insert it into the field you\'ve last clicked.');
    $element['#attributes']['class'][] = 'token-click-insert';
  }

  $output = drupal_render($element);
  //token_render_cache_set($output, $element);
  return $output;
}

/**
 * Build a row in the token tree.
 */
function _token_token_tree_format_row($token, array $token_info, $is_group = FALSE) {
  // Build a statically cached array of default values. This is around four
  // times as efficient as building the base array from scratch each time this
  // function is called.
  static $defaults = array(
    'id' => '',
    'class' => array(),
    'data' => array(
      'name' => '',
      'token' => '',
      'value' => '',
      'description' => '',
    ),
  );

  $row = $defaults;
  $row['id'] = _token_clean_css_identifier($token);
  $row['data']['name'] = $token_info['name'];
  $row['data']['description'] = isset($token_info['description']) ? $token_info['description'] : '';

  if ($is_group) {
    // This is a token type/group.
    $row['class'][] = 'token-group';
  }
  else {
    // This is a token.
    $row['data']['token']['data'] = $token;
    $row['data']['token']['class'][] = 'token-key';
    if (isset($token_info['value'])) {
      $row['data']['value'] = $token_info['value'];
    }
    if (!empty($token_info['parent'])) {
      $row['parent'] = _token_clean_css_identifier($token_info['parent']);
    }
  }

  return $row;
}

/**
 * Wrapper function for drupal_clean_css_identifier() for use with tokens.
 *
 * This trims any brackets from the token and also cleans the colon character
 * to a hyphen.
 *
 * @see drupal_clean_css_identifier()
 */
function _token_clean_css_identifier($id) {
  static $replacements = array(' ' => '-', '_' => '-', '/' => '-', '[' => '-', ']' => '', ':' => '--', '?' => '', '<' => '-', '>' => '-');
  return 'token-' . rtrim(strtr(trim($id, '[]'), $replacements), '-');
}
