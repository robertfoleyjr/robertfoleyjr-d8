<?php
/**
 * @file
 * Date forms and form themes and validation.
 *
 * All code used in form editing and processing is in this file,
 * included only during form editing.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\date_api\DateGranularity;

/**
 * Create local date object.
 *
 * Create a date object set to local time from the field and
 * widget settings and item values. Default values for new entities
 * are set by the default value callback, so don't need to be accounted for here.
 */
function date_local_date($item, $timezone, $field, $instance, $part = 'value') {

  $value = $item[$part];

  // If the value is empty, don't try to create a date object because it will
  // end up being the current day.
  if (empty($value)) {
    return NULL;
  }

  // @TODO Figure out how to replace date_fuzzy_datetime() function.
  // Special case for ISO dates to create a valid date object for formatting.
  // Is this still needed?
  /*
  if ($field['type'] == DATE_ISO) {
    $value = date_fuzzy_datetime($value);
  }
  else {
    $db_timezone = date_get_timezone_db($field['settings']['tz_handling']);
    $value = date_convert($value, $field['type'], DATE_ISO, $db_timezone);
  }
  */

  $date = new DrupalDateTime($value, date_get_timezone_db($field['settings']['tz_handling']));
  if (empty($date)) {
    return NULL;
  }
  date_timezone_set($date, timezone_open($timezone));

  return $date;
}

/**
 * Process an individual date element.
 */
function date_combo_element_process($element, &$form_state, $form) {
    
  if (date_hidden_element($element)) {
    // A hidden value for a new entity that had its end date set to blank
    // will not get processed later to populate the end date, so set it here.
    if (isset($element['#value']['value2']) && empty($element['#value']['value2'])) {
      $element['#value']['value2'] = $element['#value']['value'];
    }
    return $element;
  }

  $field_name = $element['#field_name'];
  $delta = $element['#delta'];
  $bundle = $element['#bundle'];
  $entity_type = $element['#entity_type'];
  $langcode = $element['#language'];
  $date_is_default = $element['#date_is_default'];

  $field = field_widget_field($element, $form_state);
  $instance = field_widget_instance($element, $form_state);

  // Figure out how many items are in the form, including new ones added by ajax.
  $field_state = field_form_get_state($element['#field_parents'], $field_name, $element['#language'], $form_state);
  $items_count = $field_state['items_count'];

  $columns = $element['#columns'];
  if (isset($columns['rrule'])) {
    unset($columns['rrule']);
  }
  $from_field = 'value';
  $to_field = 'value2';
  $tz_field = 'timezone';
  $offset_field = 'offset';
  $offset_field2 = 'offset2';

  // Convert UTC dates to their local values in DATETIME format,
  // and adjust the default values as specified in the field settings.

  // It would seem to make sense to do this conversion when the data
  // is loaded instead of when the form is created, but the loaded
  // field data is cached and we can't cache dates that have been converted
  // to the timezone of an individual user, so we cache the UTC values
  // instead and do our conversion to local dates in the form and
  // in the formatters.
  $keys = date_available_values($field, $instance);
  foreach ($keys as $key) {
    if (!isset($element['#default_value'][$key])) {
      $element['#default_value'][$key] = '';
    }
    $date = date_local_date($element['#default_value'], $element['#date_timezone'], $field, $instance, $key);
    $element['#default_value'][$key] = $date instanceOf DrupalDateTime ? date_format($date, DATE_FORMAT_DATETIME) : '';
  }

  // Blank out the end date for optional end dates that match the start date,
  // except when this is a new node that has default values that should be honored.
  if (!$date_is_default && $field['settings']['todate'] != 'required'
  && !empty($element['#default_value'][$to_field])
  && $element['#default_value'][$to_field] == $element['#default_value'][$from_field]) {
    unset($element['#default_value'][$to_field]);
  }

  $show_todate = !empty($form_state['values']['show_todate']) || !empty($element['#default_value'][$to_field]) || $field['settings']['todate'] == 'required';
  $element['show_todate'] = array(
    '#title' => t('Show End Date'),
    '#type' => 'checkbox',
    '#default_value' => $show_todate,
    '#weight' => -20,
    '#access' => $field['settings']['todate'] == 'optional',
    '#prefix' => '<div class="date-float">',
    '#suffix' => '</div>',
  );

  $parents = $element['#parents'];
  $first_parent = array_shift($parents);
  $show_id = $first_parent . '[' . implode('][', $parents) . '][show_todate]';

  $element[$from_field] = array(
    '#field'         => $field,
    '#instance'      => $instance,
    '#weight'        => $instance['widget']['weight'],
    '#required'      => ($instance['required'] && $delta == 0) ? 1 : 0,
    '#default_value' => isset($element['#default_value'][$from_field]) ? $element['#default_value'][$from_field] : '',
    '#delta'         => $delta,
    '#date_timezone' => $element['#date_timezone'],
    '#date_date_format'=> date_get_format($instance, 'date'),
    '#date_increment'   => $instance['widget']['settings']['increment'],
    '#date_year_range'  => $instance['widget']['settings']['year_range'],
    '#date_label_position' => $instance['widget']['settings']['label_position'],
    '#date_time_callbacks' => array('date_all_day_toggle_callback'),
    );

    $element[$from_field]['all_day'] = array(
      '#title' => t('All Day'),
      '#type' => !empty($instance['settings']['all_day_toggle']) ? 'checkbox' : 'hidden',
      '#default_value' => isset($items[$delta][$from_field]['all_day']) ? $items[$delta][$from_field]['all_day'] : NULL,
    );


  $description =  !empty($instance['description']) ? t($instance['description']) : '';

  // Give this element the right type, using a Date API
  // or a Date Popup element type.
  //$element[$from_field]['#attributes'] = array('class' => array('date-clear'));
  //$element[$from_field]['#wrapper_attributes'] = array('class' => array());
  //$element[$from_field]['#wrapper_attributes']['class'][] = 'date-no-float';

  switch ($instance['widget']['type']) {
    case 'date_select':
      $element[$from_field]['#type'] = 'datelist';
      $element[$from_field]['#date_text_parts'] = (array) $instance['widget']['settings']['text_parts'];
      //$element[$from_field]['#theme_wrappers'] = array('datelist');
      $element['#attached']['js'][] = drupal_get_path('module', 'date') . '/date.js';
      $element[$from_field]['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;
      break;
    case 'date_popup':
      $element[$from_field]['#type'] = 'datetime';
      $element[$from_field]['#date_time_format'] = date_get_format($instance, 'time');
      $element[$from_field]['#date_date_element'] = 'date';
      $element[$from_field]['#date_time_element'] = 'time';
      $element[$from_field]['#date_date_callbacks'] = array('datetime_jquery_datepicker');
      $element[$from_field]['#date_time_callbacks'] = array('date_all_day_toggle_callback');
      //$element[$from_field]['#theme_wrappers'] = array('datetime');
      //$element[$from_field]['#ajax'] = !empty($element['#ajax']) ? $element['#ajax'] : FALSE;
      break;
  }

  // If this field uses the 'End', add matching element
  // for the 'End' date, and adapt titles to make it clear which
  // is the 'Start' and which is the 'End' .

  if (!empty($field['settings']['todate'])) {
    $element[$from_field]['#title'] = '';
    $element[$to_field] = $element[$from_field];
    $element[$to_field]['#title'] = t('to:');
    //$element[$from_field]['#wrapper_attributes']['class'][] = 'start-date-wrapper';
    //$element[$to_field]['#wrapper_attributes']['class'][] = 'end-date-wrapper';
    $element[$to_field]['#default_value'] = isset($element['#default_value'][$to_field]) ? $element['#default_value'][$to_field] : '';
    $element[$to_field]['#required'] = ($element[$from_field]['#required'] && $field['settings']['todate'] == 'required');
    $element[$to_field]['#weight'] += .2;
    $element[$to_field]['#prefix'] = '';
    // Users with JS enabled will never see initially blank values for the end
    // date (see Drupal.date.EndDateHandler()), so hide the message for them.
    $description .= '<span class="js-hide"> ' . t("Empty 'End date' values will use the 'Start date' values.") . '</span>';
    $element['#fieldset_description'] = $description;
    if ($field['settings']['todate'] == 'optional') {
      $element[$to_field]['#states'] = array(
        'visible' => array(
          'input[name="' . $show_id . '"]' => array('checked' => TRUE),
      ));
    }
  }
  else {
    $element[$from_field]['#description'] = $description;
  }

  // Create label for error messages that make sense in multiple values
  // and when the title field is left blank.
  if ($field['cardinality'] <> 1 && $items_count > 1) {
    $element[$from_field]['#date_title'] = t('@field_name Start date value #@delta', array('@field_name' => $instance['label'], '@delta' => $delta + 1));
    if (!empty($field['settings']['todate'])) {
      $element[$to_field]['#date_title'] = t('@field_name End date value #@delta', array('@field_name' => $instance['label'], '@delta' => $delta + 1));
    }
  }
  elseif (!empty($field['settings']['todate'])) {
    $element[$from_field]['#date_title'] = t('@field_name Start date', array('@field_name' => $instance['label']));
    $element[$to_field]['#date_title'] = t('@field_name End date', array('@field_name' => $instance['label']));
  }
  else {
    $element[$from_field]['#date_title'] = $instance['label'];
  }

  $context = array(
   'field' => $field,
   'instance' => $instance,
   'form' => $form,
  );
  drupal_alter('date_combo_process', $element, $form_state, $context);

  return $element;
}

function date_element_empty($element, $item, &$form_state) {
  $item['value'] = NULL;
  $item['value2']   = NULL;
  $item['timezone']   = NULL;
  $item['offset'] = NULL;
  $item['offset2'] = NULL;
  $item['rrule'] = NULL;
  form_set_value($element, $item, $form_state);
  return $item;
}

/**
 * Validate and update a combo element.
 * Don't try this if there were errors before reaching this point.
 */
function date_combo_validate($element, &$form_state) {

  // Disabled and hidden elements won't have any input and don't need validation,
  // we just need to re-save the original values, from before they were processed into
  // widget arrays and timezone-adjusted.
  if (date_hidden_element($element) || !empty($element['#disabled'])) {
    form_set_value($element, $element['#date_items'], $form_state);
    return;
  }

  $field_name = $element['#field_name'];
  $delta = $element['#delta'];
  $langcode = $element['#language'];

  $form_values = drupal_array_get_nested_value($form_state['values'], $element['#field_parents']);
  $form_input = drupal_array_get_nested_value($form_state['input'], $element['#field_parents']);

  // If the whole field is empty and that's OK, stop now.
  if (empty($form_input[$field_name]) && !$element['#required']) {
    return;
  }
  
  // @TODO In latest field code there is no $delta in these arrays.
  // This is only in Date, so it's a problem in this code.
  $item = $form_values[$field_name][$langcode][$delta];
  $posted = $form_input[$field_name][$langcode][$delta];

  $field = field_widget_field($element, $form_state);
  $instance = field_widget_instance($element, $form_state);
  $format = date_get_format($instance);

  $context = array(
    'field' => $field,
    'instance' => $instance,
    'item' => $item,
  );

  drupal_alter('date_combo_pre_validate', $element, $form_state, $context);

  $from_field = 'value';
  $to_field = 'value2';
  $tz_field = 'timezone';
  $offset_field = 'offset';
  $offset_field2 = 'offset2';

  // Check for empty 'Start date', which could either be an empty
  // value or an array of empty values, depending on the widget.
  $empty = TRUE;
  if (!empty($item[$from_field])) {
    if (!is_array($item[$from_field])) {
      $empty = FALSE;
    }
    else {
      foreach ($item[$from_field] as $key => $value) {
        if (!empty($value)) {
          $empty = FALSE;
          break;
        }
      }
    }
  }

  // An 'End' date without a 'Start' date is a validation error.
  if ($empty && !empty($item[$to_field])) {
    if (!is_array($item[$to_field])) {
      form_error($element, t("A 'Start date' date is required if an 'end date' is supplied for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])));
      $empty = FALSE;
    }
    else {
      foreach ($item[$to_field] as $key => $value) {
        if (!empty($value)) {
          form_error($element, t("A 'Start date' date is required if an 'End date' is supplied for field %field #%delta.", array('%delta' => $field['cardinality'] ? intval($delta + 1) : '', '%field' => $instance['label'])));
          $empty = FALSE;
          break;
        }
      }
    }
  }

  // If the user chose the option to not show the end date, just swap in the
  // start date as that value so the start and end dates are the same.
  if ($field['settings']['todate'] == 'optional' && empty($item['show_todate'])) {
    $item[$to_field] = $item[$from_field];
    $posted[$to_field] = $posted[$from_field];
  }

  if ($empty) {
    $item = date_element_empty($element, $item, $form_state);
    if (!$element['#required']) {
      return;
    }
  }
  // Don't look for further errors if errors are already flagged
  // because otherwise we'll show errors on the nested elements
  // more than once.
  elseif (!form_get_errors()) {

    $timezone = !empty($item[$tz_field]) ? $item[$tz_field] : $element['#date_timezone'];
    $timezone_db = date_get_timezone_db($field['settings']['tz_handling']);
    $element[$from_field]['#date_timezone'] = $timezone;
    $from_date = date_input_date($field, $instance, $element[$from_field], $posted[$from_field]);

    if (!empty($field['settings']['todate'])) {
      $element[$to_field]['#date_timezone'] = $timezone;
      $to_date = date_input_date($field, $instance, $element[$to_field], $posted[$to_field]);
    }
    else {
      $to_date = $from_date;
    }

    // Neither the start date nor the end date should be empty at this point
    // unless they held values that couldn't be evaluated.

    if (!$instance['required'] && ($from_date->hasErrors() || $to_date->hasErrors())) {
      $item = date_element_empty($element, $item, $form_state);
      $errors[] = t('The dates are invalid.');
    }
    elseif (!empty($field['settings']['todate']) && $from_date > $to_date) {
      form_set_value($element[$to_field], $to_date, $form_state);
      $errors[] = t('The End date must be greater than the Start date.');
    }
    else {
      // Convert input dates back to their UTC values and re-format to ISO
      // or UNIX instead of the DATETIME format used in element processing.
      $item[$tz_field] = $timezone;

      // Update the context for changes in the $item, and allow other modules to
      // alter the computed local dates.
      $context['item'] = $item;
      // We can only pass two additional values to drupal_alter, so $element
      // needs to be included in $context.
      $context['element'] = $element;
      drupal_alter('date_combo_validate_date_start', $from_date, $form_state, $context);
      drupal_alter('date_combo_validate_date_end', $to_date, $form_state, $context);

      $item[$offset_field] = date_offset_get($from_date);

      $test_from = date_format($from_date, 'r');
      $test_to = date_format($to_date, 'r');

      $item[$offset_field2] = date_offset_get($to_date);
      date_timezone_set($from_date, timezone_open($timezone_db));
      date_timezone_set($to_date, timezone_open($timezone_db));
      $item[$from_field] = date_format($from_date, date_type_format($field['type']));
      $item[$to_field] = date_format($to_date, date_type_format($field['type']));
      if (isset($form_values[$field_name]['rrule'])) {
        $item['rrule'] = $form_values[$field['field_name']]['rrule'];
      }

      // If the db timezone is not the same as the display timezone
      // and we are using a date with time granularity,
      // test a roundtrip back to the original timezone to catch
      // invalid dates, like 2AM on the day that spring daylight savings
      // time begins in the US.
      $granularity = date_format_order($format);
      if ($timezone != $timezone_db && DateGranularity::hasTime($granularity)) {
        date_timezone_set($from_date, timezone_open($timezone));
        date_timezone_set($to_date, timezone_open($timezone));

        if ($test_from != date_format($from_date, 'r')) {
          $errors[] = t('The Start date is invalid.');
        }
        if ($test_to != date_format($to_date, 'r')) {
          $errors[] = t('The End date is invalid.');
        }
      }
      if (empty($errors)) {
        form_set_value($element, $item, $form_state);
      }
    }
  }
  if (!empty($errors)) {
    if ($field['cardinality']) {
      form_error($element, t('There are errors in @field_name value #@delta:', array('@field_name' => $instance['label'], '@delta' => $delta + 1)) . theme('item_list', array('items' => $errors)));
    }
    else {
      form_error($element, t('There are errors in @field_name:', array('@field_name' => $instance['label'])) . theme('item_list', array('items' => $errors)));
    }
  }
}

/**
 * Determine the input format for this element.
 */
function date_input_format($element, $field, $instance) {
  if (!empty($instance['widget']['settings']['input_format_custom'])) {
    return $instance['widget']['settings']['input_format_custom'];
  }
  elseif (!empty($instance['widget']['settings']['input_format']) && $instance['widget']['settings']['input_format'] != 'site-wide') {
    return $instance['widget']['settings']['input_format'];
  }
  return variable_get('date_format_short', 'm/d/Y - H:i');
}


/**
 * Implements hook_date_select_pre_validate_alter().
 */
function date_date_select_pre_validate_alter(&$element, &$form_state, &$input) {
  date_empty_end_date($element, $form_state, $input);
}

/**
 * Implements hook_date_popup_pre_validate_alter().
 */
function date_date_popup_pre_validate_alter(&$element, &$form_state, &$input) {
  date_empty_end_date($element, $form_state, $input);
}

/**
 * Helper function to clear out end date when not being used.
 */
function date_empty_end_date(&$element, &$form_state, &$input) {
  // If this is the end date and the option to show an end date has not been selected,
  // empty the end date to surpress validation errors and stop further processing.
  $parents = $element['#parents'];
  $parent = array_pop($parents);
  if ($parent == 'value2') {
    $parent_values = drupal_array_get_nested_value($form_state['values'], $parents);
    if (isset($parent_values['show_todate']) && $parent_values['show_todate'] != 1) {
      $input = array();
      form_set_value($element, NULL, $form_state);
    }
  }
}
