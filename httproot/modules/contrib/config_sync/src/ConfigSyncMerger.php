<?php

/**
 * @file
 * Contains \Drupal\config_sync\ConfigSyncMerger.
 */

namespace Drupal\config_sync;

/**
 * Provides helper functions for merging configuration items.
 */
class ConfigSyncMerger {

  /**
   * Merges changes to a configuration item into the active storage.
   *
   * @param $previous
   *   The configuration item as previously provided (from snapshot).
   * @param $current
   *   The configuration item as currently provided by an extension.
   * @param $active
   *   The configuration item as present in the active storage.
   */
  public static function mergeConfigItemStates(array $previous, array $current, array $active) {
    // We are merging into the active configuration state.
    $result = $active;

    $states = [
      $previous,
      $current,
      $active,
    ];

    $is_associative = FALSE;

    foreach ($states as $array) {
      // Analyze the array to determine if we should preserve integer keys.
      // Use the same logic as when dumping into Yaml.
      // See \Symfony\Component\Yaml\Inline::dumpArray().
      $keys = array_keys($array);
      $keys_count = count($keys);
      if ((1 === $keys_count && '0' == $keys[0])
          || ($keys_count > 1 && array_reduce($keys, function ($v, $w) { return (int) $v + $w; }, 0) === $keys_count * ($keys_count - 1) / 2)
      ) {
        continue;
      }
      else {
        // If any of the states is associative, treat the item as associative.
        $is_associative = TRUE;
        break;
      }
    }

    // Process associative arrays.
    // Find any differences between previous and current states.
    if ($is_associative) {
      // Detect and process removals.
      $removed = array_diff_key($previous, $current);
      foreach ($removed as $key => $value) {
        // Remove only if unchanged in the active state.
        if (isset($active[$key]) && $active[$key] === $previous[$key]) {
          unset($result[$key]);
        }
      }

      // Detect and handle additions.
      $added = array_diff_key($current, $previous);
      foreach ($added as $key => $value) {
        // Add only if the key hasn't already been set.
        if (!isset($active[$key])) {
          $result[$key] = $value;
        }
      }

      // Detect and process changes.
      foreach ($current as $key => $value) {
        if (isset($previous[$key]) && $previous[$key] !== $value) {
          // If we have an array, recurse.
          if (is_array($value) && is_array($previous[$key]) && isset($active[$key]) && is_array($active[$key])) {
            $result[$key] = self::mergeConfigItemStates($previous[$key], $value, $active[$key]);
          }
          else {
            // Accept the new value only if the item hasn't been customized.
            if (isset($active[$key]) && $active[$key] === $previous[$key]) {
              $result[$key] = $value;
            }
          }
        }
      }
    }
    // Process indexed arrays.
    else {
      // Detect and process removals.
      $removed = array_diff($previous, $current);
      foreach ($removed as $value) {
        // If there is an unchanged value in the active configuration, remove
        // it.
        if (($key = array_search($value, $active, TRUE)) !== FALSE) {
          unset($result[$key]);
        }
      }
      // Detect and process additions.
      $added = array_diff($current, $previous);
      foreach ($added as $value) {
        if (!in_array($value, $result)) {
          $result[] = $value;
        }
      }
      // Keep non-associative.
      $result = array_values($result);
    }

    return $result;
  }

}
