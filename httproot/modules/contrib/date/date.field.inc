<?php

/**
 * @file
 * Field hooks to implement a date field.
 */

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Implements hook_field_is_empty().
 */
function date_field_is_empty($item, $field) {
  // Sometimes a $item is a date object.
  // Coming from repeating dates. Why??
  if (!is_array($item)) {
    return FALSE;
  }
  if (empty($item['value'])) {
    return TRUE;
  }
  elseif ($field['settings']['todate'] == 'required' && empty($item['value2'])) {
    return TRUE;
  }
  return FALSE;
}

/**
 * Implements hook_field_info().
 */
function date_field_info() {
  return array(
    'date' => array(
      'label' => 'Date Range',
      'description' => t('Store a start and optional end date in the database as ISO dates.'),
      'settings' => array(
        'todate' => '',
        'granularity' => drupal_map_assoc(array('year', 'month', 'day', 'hour', 'minute')),
        'tz_handling' => 'site',
        'timezone_db' => 'UTC',
      ),
      'instance_settings' => array(
        'default_value' => 'now',
        'default_value_code' => '',
        'default_value2' => 'same',
        'default_value_code2' => '',
      ),
      'default_widget' => 'date_select',
      'default_formatter' => 'date_default',
      'default_token_formatter' => 'date_plain',
      'field item class' => '\Drupal\date\Type\DateItem',
    ),
  );
}

/**
 * Implements hook_field_load().
 */
function date_field_load($entity_type, $entities, $field, $instances, $langcode, &$items, $age) {
  $timezone_db = date_get_timezone_db($field['settings']['tz_handling']);
  $db_format = date_type_format($field['type']);
  $keys = date_available_values($field);
  foreach ($entities as $id => $entity) {
    foreach ($items[$id] as $delta => &$item) {
      // If the file does not exist, mark the entire item as empty.
      if (is_array($item)) {
        $item['data'] = isset($item['data']) ? unserialize($item['data']) : array();
        $item['timezone_db'] = $timezone_db;
        $item['date_type'] = $field['type'];
        if (!empty($field['settings']['cache_enabled']) && ($delta < $field['settings']['cache_count'] || $field['settings']['cache_count'] == 0)) {
          foreach ($keys as $key) {
            if (!empty($item[$key])) {
              $item['db'][$key] = $date;
            }
          }
        }
      }
    }
  }
}

/**
 * Implements hook_field_validate().
 */
function date_field_validate($entity_type, $entity, $field, $instance, $langcode, $items, &$errors) {
  $field_name = $field['field_name'];
  $flexible = 0;

  // @TODO Figure out what we need here now.
  return;

  // Don't try to validate if there were any errors before this point
  // since the element won't have been munged back into a date.
  if (!form_get_errors()) {
    foreach ($items as $delta => $item) {
      if (is_array($item) && isset($item['value'])) {
        $keys = date_available_values($field, $instance);
        $date1 = new DrupalDateTime($item['value'], $item['timezone'], date_type_format($field['type']));
        if (count($keys) == 1 || (empty($item['value2']) && $item['value2'] !== 0)) {
          $date2 = clone($date1);
        }
        else {
          $date2 = new DrupalDateTime($item['value2'], $item['timezone'], date_type_format($field['type']));
        }
/*
        $valid1 = $date1->validGranularity($field['settings']['granularity'], $flexible);
        $valid2 = $date2->validGranularity($field['settings']['granularity'], $flexible);

        foreach ($keys as $key) {
          if ($ == 'value' && $field['settings']['todate'] && !$valid1 && $valid2) {
            $errors[$field['field_name']][$langcode][$delta][] = array(
              'error' => 'value',
              'message' => t("A 'Start date' date is required for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])),
            );
          }
          if ($key == 'value2' && $field['settings']['todate'] == 'required' && ($instance['required'] && $valid1 && !$valid2)) {
            $errors[$field['field_name']][$langcode][$delta][] = array(
              'error' => 'value2',
              'message' => t("An 'End date' is required for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])),
            );
          }
        }
*/
      }
    }
  }
}

/**
 * Implements hook_field_insert().
 */
function date_field_presave($entity_type, $entity, $field, $instance, $langcode, &$items) {
  $field_name = $field['field_name'];

  if (empty($items)) {
    return;
  }
  // Add some information needed to interpret token values.
  $values = $items;
  foreach ($values as $delta => $item) {
    $timezone = isset($item['timezone']) ? $item['timezone'] : '';
    if (is_array($item)) {
      $items[$delta]['timezone'] = date_get_timezone($field['settings']['tz_handling'], $timezone);
      $items[$delta]['timezone_db'] = date_get_timezone_db($field['settings']['tz_handling']);
      $items[$delta]['date_type'] = $field['type'];
      $data = array();
      foreach (date_data_keys() as $key) {
        if (isset($item[$key])) {
          $data[$delta][$key] = $item[$key];
          unset($items[$delta][$key]);
        }
      }
      $items[$delta]['data'] = serialize($data);
    }
  }
  $entity->{$field['field_name']}[$langcode] = $items;
}

/**
 * Implements hook_field_insert().
 */
function date_field_insert($entity_type, $entity, $field, $instance, $langcode, &$items) {
  $context = array(
    'entity_type' => $entity_type,
    'entity' => $entity,
    'field' => $field,
    'instance' => $instance,
    'langcode' => $langcode,
  );
  drupal_alter('date_field_insert', $items, $context);
}

/**
 * Implements hook_field_update().
 */
function date_field_update($entity_type, $entity, $field, $instance, $langcode, &$items) {
  $context = array(
    'entity_type' => $entity_type,
    'entity' => $entity,
    'field' => $field,
    'instance' => $instance,
    'langcode' => $langcode,
  );
  drupal_alter('date_field_update', $items, $context);
}

/**
 * Implements hook_field_instance_settings_form().
 *
 * Wrapper functions for date administration, included only when processing
 * field settings.
 */
function date_field_instance_settings_form($field, $instance) {
  module_load_include('inc', 'date', 'date_admin');
  return _date_field_instance_settings_form($field, $instance);
}

/**
 * Implements hook_field_settings_form().
 */
function date_field_settings_form($field, $instance, $has_data) {
  module_load_include('inc', 'date', 'date_admin');
  return _date_field_settings_form($field, $instance, $has_data);
}

