<?php

/**
 * @file
 * Date API elements themes and validation.
 * This file is only included during the edit process to reduce memory usage.
 */

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\date_api\DateGranularity;

/**
 * Implements hook_element_info().
 *
 * Parameters for date form elements, designed to have sane defaults so any
 * or all can be omitted.
 *
 * Fill the element #default_value with a date in datetime format,
 * (YYYY-MM-DD HH:MM:SS), adjusted to the proper local timezone.
 *
 * NOTE - Converting a date stored in the database from UTC to the local zone
 * and converting it back to UTC before storing it is not handled by this
 * element and must be done in pre-form and post-form processing!!
 *
 * The date_select element will create a collection of form elements, with a
 * separate select or textfield for each date part. The whole collection will
 * get reformatted back to a date value of the requested type during validation.
 *
 * The date_timezone element will create a drop-down selector to pick a
 * timezone name.
 *
 * The date_year_range element will create two textfields (for users with
 * JavaScript enabled they will appear as drop-down selectors with an option
 * for custom text entry) to pick a range of years that will be passed to form
 * submit handlers as a single string (e.g., -3:+3).
 *
 * #date_timezone
 *   The local timezone to be used to create this date.
 *
 * #date_format
 *   A format string that describes the format and order of date parts to
 *   display in the edit form for this element. This makes it possible
 *   to show date parts in a custom order, or to leave some of them out.
 *   Be sure to add 'A' or 'a' to get an am/pm selector. Defaults to the
 *   short site default format.
 *
 * #date_label_position
 *   Handling option for date part labels, like 'Year', 'Month', and 'Day',
 *   can be 'above' the date part, 'within' it, or 'none', default is 'above' .
 *   The 'within' option shows the label as the first option in a select list
 *   or the default value for an empty textfield, taking up less screen space.
 *
 * #date_increment
 *   Increment minutes and seconds by this amount, default is 1.
 *
 * #date_year_range
 *   The number of years to go back and forward in a year selector,
 *   default is -3:+3 (3 back and 3 forward).
 *
 * #date_text_parts
 *   Array of date parts that should use textfields instead of selects
 *   i.e. array('year') will format the year as a textfield and other
 *   date parts as drop-down selects.
 */
function _date_api_element_info() {
  $date_base = array(
    '#input' => TRUE, '#tree' => TRUE,
    '#date_timezone' => '',
    '#date_flexible' => 0,
    '#date_text_parts' => array(),
    '#date_increment' => 1,
    '#date_year_range' => '-3:+3',
    '#date_label_position' => 'above',
  );
  if (module_exists('ctools')) {
    $date_base['#pre_render'] = array('ctools_dependent_pre_render');
  }
  $type['date_timezone'] = array(
    '#input' => TRUE, '#tree' => TRUE,
    '#process' => array('date_timezone_element_process'),
    '#theme_wrappers' => array('date_timezone'),
    '#value_callback' => 'date_timezone_element_value_callback',
  );
  $type['date_year_range'] = array(
    '#input' => TRUE,
    '#process' => array('date_year_range_element_process'),
    '#value_callback' => 'date_year_range_element_value_callback',
    '#element_validate' => array('date_year_range_validate'),
  );
  $types['datelist'] = array(
    '#input' => TRUE,
    '#element_validate' => array('datelist_validate'),
    '#process' => array('form_process_datelist'),
    '#value_callback' => 'datelist_value_callback',
    '#theme' => 'datelist',
    '#theme_wrappers' => array('form_element'),
    '#date_date_format' => variable_get('date_format_short', 'm/d/Y - H:i'),
    '#date_year_range' => '1900:2050',
    '#date_increment' => 1,
    '#date_calendar' => 'gregorian',
  );
  return $type;
}

/**
 * Process callback which creates a date_year_range form element.
 */
function date_year_range_element_process($element, &$form_state, $form) {
  // Year range is stored in the -3:+3 format, but collected as two separate
  // textfields.
  $element['years_back'] = array(
    '#type' => 'textfield',
    '#title' => t('Starting year'),
    '#default_value' => $element['#value']['years_back'],
    '#size' => 10,
    '#maxsize' => 10,
    '#attributes' => array('class' => array('select-list-with-custom-option', 'back')),
    '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
  );
  $element['years_forward'] = array(
    '#type' => 'textfield',
    '#title' => t('Ending year'),
    '#default_value' => $element['#value']['years_forward'],
    '#size' => 10,
    '#maxsize' => 10,
    '#attributes' => array('class' => array('select-list-with-custom-option', 'forward')),
    '#description' => t('Enter a relative value (-9, +9) or an absolute year such as 2015.'),
  );

  $element['#tree'] = TRUE;
  $element['#attached']['js'][] = drupal_get_path('module', 'date_api') . '/date_year_range.js';

  $context = array(
   'form' => $form,
  );
  drupal_alter('date_year_range_process', $element, $form_state, $context);

  return $element;
}

/**
 * Element value callback for the date_year_range form element.
 */
function date_year_range_element_value_callback($element, $input = FALSE, &$form_state = array()) {
  // Convert the element's default value from a string to an array (to match
  // what we will get from the two textfields when the form is submitted).
  if ($input === FALSE) {
    list($years_back, $years_forward) = explode(':', $element['#default_value']);
    return array(
      'years_back' => $years_back,
      'years_forward' => $years_forward,
    );
  }
}

/**
 * Element validation function for the date_year_range form element.
 */
function date_year_range_validate(&$element, &$form_state) {
  // Recombine the two submitted form values into the -3:+3 format we will
  // validate and save.
  $year_range_submitted = drupal_array_get_nested_value($form_state['values'], $element['#parents']);
  $year_range = $year_range_submitted['years_back'] . ':' . $year_range_submitted['years_forward'];
  drupal_array_set_nested_value($form_state['values'], $element['#parents'], $year_range);
  if (!date_range_valid($year_range)) {
    form_error($element['years_back'], t('Starting year must be in the format -9, or an absolute year such as 1980.'));
    form_error($element['years_forward'], t('Ending year must be in the format +9, or an absolute year such as 2030.'));
  }
}

/**
 * Element value callback for date_timezone element.
 */
function date_timezone_element_value_callback($element, $input = FALSE, &$form_state = array()) {
  $return = '';
  if ($input !== FALSE) {
    $return = $input;
  }
  elseif (!empty($element['#default_value'])) {
    $return = array('timezone' => $element['#default_value']);
  }
  return $return;
}

/**
 * Creates a timezone form element.
 *
 * @param array $element
 *   The timezone form element.
 *
 * @return array
 *   the timezone form element
 */
function date_timezone_element_process($element, &$form_state, $form) {
  if (date_hidden_element($element)) {
    return $element;
  }

  $element['#tree'] = TRUE;
  $label = theme('date_part_label_timezone', array('part_type' => 'select', 'element' => $element));
  $element['timezone'] = array(
    '#type' => 'select',
    '#title' => $element['#date_label_position'] == 'above' ? $label : '',
    '#options' => system_time_zones($element['#required']),
    '#value' => $element['#value'],
    '#weight' => $element['#weight'],
    '#required' => $element['#required'],
    '#theme' => 'date_select_element',
    '#theme_wrappers' => array('form_element'),
  );
  if (isset($element['#element_validate'])) {
    array_push($element['#element_validate'], 'date_timezone_validate');
  }
  else {
    $element['#element_validate'] = array('date_timezone_validate');
  }

  $context = array(
   'form' => $form,
  );
  drupal_alter('date_timezone_process', $element, $form_state, $context);

  return $element;
}

/**
 *  Validation for timezone input
 *
 *  Move the timezone value from the nested field back to the original field.
 */
function date_timezone_validate($element, &$form_state) {
  if (date_hidden_element($element)) {
    return;
  }

  form_set_value($element, $element['#value']['timezone'], $form_state);
}

/**
 * Expands a date element into an array of individual elements (i.e.
 * year, month, day). All form elements are designed to have sane
 * defaults so any or all can be omitted.
 *
 * Required settings:
 *   #default_value
 *     The default value should be expressed in ISO datetime format,
 *     using the format used by DrupalDateTime's __toString() function.
 *     Provide a date with or without a timzone name appended to it,
 *     adjusted to the proper local timezone, i.e. '2012-01-31 10:30:00
 *     America/Chicago'. NOTE - Converting a date stored in the database
 *     from UTC to the local zone and converting it back to UTC before
 *     storing it is not handled here. This element simply explodes and
 *     displays the string date that is provided to it and converts the
 *     user input back into a string value on submission.
 * Optional properties in the $form may include:
 *   #date_date_format
 *     A date format string that describes the sub-elements that should
 *     be displayed to the end user and their order. Punctuation and
 *     other values in the format string will be ignored.
 *     Defaults to the site 'short' date format.
 *   #date_year_range
 *     A description of the range of years to display, like '1900:2050',
 *     '-3:+3' or '2000:+3', where the first value describes the earliest
 *     year and the second the latest year in the range. A year
 *     in either position means that specific year. A +/- value
 *     describes a dynamic value that is that many years earlier or
 *     later than the current year at the time the form is displayed.
 *     Defaults to '1900:2050'.
 *   #date_increment
 *     The increment to use for minutes and seconds, i.e. '15' would show
 *     only :00, :15, :30 and :45.
 *     Defaults to 1 to show every minute and second.
 *   #date_calendar
 *     The calendar system to use for displaying possible date parts.
 *     Depends on the calendar plugins available on this site.
 *     Defaults to 'gregorian'.
 *
 * Example:
 *   $form = array(
 *     '#type' => 'date',
 *     '#default_value' => '2000-01-01 00:00:00',
 *     '#date_input_format' => 'd.m.Y H:i',
 *     '#date_year_range' => '2010:2020',
 *     '#date_increment' => 15,
 *   );
 */
function form_process_datelist($element, &$form_state) {

  // Load translated date part labels from the appropriate calendar plugin.
  $calendar = system_calendar($element['#date_calendar']);

  // If empty, this will default to the current date.
  $date = new \Drupal\Core\Datetime\DrupalDateTime($element['#value']);

  $element['#tree'] = TRUE;

  $hours_format  = strpos(strtolower($element['#date_date_format']), 'a') ? 'g': 'G';
  $month_function  = strpos($element['#date_date_format'], 'F') !== FALSE ? 'month_names' : 'month_names_abbr';

  // Determine the order of day, month, year in the site's chosen date format.
  $format = $element['#date_date_format'];
  $order = date_format_order($format);
  $has_time = FALSE;

  // Output multi-selector for date.
  foreach ($order as $part) {
    switch ($part) {
      case 'day':
        $options = $calendar->days($element['#required']);
        $format = 'j';
        $title = t('Day');
        break;
      case 'month':
        $options = $calendar->$month_function($element['#required']);
        $format = 'n';
        $title = t('Month');
        break;
      case 'year':
        $range = date_range_years($element['#date_year_range'], $date);
        $options = $calendar->years($range[0], $range[1], $element['#required']);
        $format = 'Y';
        $title = t('Year');
        break;
      case 'hour':
        $format = $hours_format;
        $options = $calendar->hours($hours_format, $element['#required']);
        $has_time = TRUE;
        $title = t('Hour');
        break;
      case 'minute':
        $format = 'i';
        $options = $calendar->minutes('i', $element['#required'], $element['#date_increment']);
        $has_time = TRUE;
        $title = t('Minute');
        break;
      case 'second':
        $format = 's';
        $options = $calendar->seconds('s', $element['#required'], $element['#date_increment']);
        $has_time = TRUE;
        $title = t('Second');
        break;
    }

    $default = !empty($element['#value'][$part]) ? $element['#value'][$part] : '';
    $value = $date instanceOf \Drupal\Core\Datetime\DrupalDateTime && !$date->hasErrors() ? intval($date->format($format)) : $default;

    $element['#attributes']['title'] = $title;
    $element[$part] = array(
      '#type' => 'select',
      '#title' => $title,
      '#title_display' => 'invisible',
      '#value' => $value,
      '#attributes' => $element['#attributes'],
      '#options' => $options,
      '#required' => $element['#required'],
    );
  }

  // Add the am/pm element if the format requires it.
  if (($hours_format == 'g' || $hours_format == 'h') && $has_time) {
    $default = !empty($element['#value']['ampm']) ? $element['#value']['ampm'] : 'am';
    $value = $date instanceOf \Drupal\Core\Datetime\DrupalDateTime && !$date->hasErrors() ? ($date->format('G') >= 12 ? 'pm' : 'am') : $default;
    $element['ampm'] = array(
      '#type' => 'select',
      '#title' => t('AM/PM'),
      '#title_display' => 'invisible',
      '#theme' => 'date_select_element',
      '#default_value' => $value,
      '#options' => $calendar->ampm($element['#required']),
      '#required' => $element['#required'],
      '#weight' => 10,
    );
  }

  return $element;
}

/**
 * Element value callback for datelist element.
 */
function datelist_value_callback($element, $input = FALSE, &$form_state = array()) {
  $parts = date_format_order($element['#date_date_format']);
  $return = array_fill_keys($parts, '');
  $date = NULL;
  if ($input !== FALSE) {
    $date = datelist_get_input_date($element, $input);
  }
  elseif (!empty($element['#default_value'])) {
    $date = new \Drupal\Core\Datetime\DrupalDateTime($element['#default_value']);
  }
  if ($date instanceOf \Drupal\Core\Datetime\DrupalDateTime && !$date->hasErrors()) {
    $parse = date_parse($date);
    foreach ($parts as $part) {
      $return[$part] = $parse[$part];
    }
  }
  return $return;
}

/**
 * Helper function to turn the user input for a date list back into
 * a qualified date object.
 */
function datelist_get_input_date($element, $input) {

  // Was anything entered? If not, we have no date.
  if (empty($input) || !is_array($input)) {
    return NULL;
  }
  $test_values = array_values(array_filter($input));
  if (empty($test_values)) {
    return NULL;
  }

  if (isset($input['ampm'])) {
    if ($input['ampm'] == 'pm' && $input['hour'] < 12) {
      $input['hour'] += 12;
    }
    elseif ($input['ampm'] == 'am' && $input['hour'] == 12) {
      $input['hour'] -= 12;
    }
    unset($input['ampm']);
  }
  return new \Drupal\Core\Datetime\DrupalDateTime($input);
}

/**
 * Validates the date type to adjust 12 hour time and prevent invalid
 * dates (e.g., February 30, 2006).
 *
 * If the date is valid, the date is set in the form as a string
 * using the format designated in __toString().
 */
function datelist_validate($element, &$form_state) {
  $input_exists = FALSE;
  $input = drupal_array_get_nested_value($form_state['values'], $element['#parents'], $input_exists);
  if ($input_exists) {
    $date = datelist_get_input_date($element, $input);
    if ($date instanceOf \Drupal\Core\Datetime\DrupalDateTime && !$date->hasErrors()) {
      form_set_value($element, $date->__toString(), $form_state);
    }
    elseif (!$element['#required']) {
      form_set_value($element, NULL, $form_state);
    }
    else {
      form_error($element, t('The specified date is invalid.'));
    }
  }
}