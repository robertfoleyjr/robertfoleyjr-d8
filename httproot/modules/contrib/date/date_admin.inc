<?php

/**
 * @file
 * Date administration code.
 */

use Drupal\date_api\DateGranularity;

/**
 * Helper function for date_field_instance_settings_form().
 *
 * @see date_field_instance_settings_form_validate()
 */
function _date_field_instance_settings_form($field, $instance) {
  $widget = $instance['widget'];
  $settings = $instance['settings'];
  $widget_settings = $instance['widget']['settings'];

  $form['default_value'] = array(
    '#type' => 'select',
    '#title' => t('Default date'),
    '#default_value' => $settings['default_value'],
    '#options' => array('blank' => t('No default value'), 'now' => t('Now'), 'strtotime' => t('Relative')),
    '#weight' => 1,
    '#fieldset' => 'default_values',
  );

  $description = t("Describe a time by reference to the current day, like '+90 days' (90 days from the day the field is created) or '+1 Saturday' (the next Saturday). See !strtotime for more details.", array('!strtotime' => l(t('strtotime'), 'http://www.php.net/manual/en/function.strtotime.php')));
  $form['default_value_code'] = array(
    '#type' => 'textfield',
    '#title' => t('Relative default value'),
    '#description' => $description,
    '#default_value' => $settings['default_value_code'],
    '#states' => array(
      'visible' => array(
        ':input[name="instance[settings][default_value]"]' => array('value' => 'strtotime')),
      ),
    '#weight' => 1.1,
    '#fieldset' => 'default_values',
  );
  $form['default_value2'] = array(
    '#type' => !empty($field['settings']['todate']) ? 'select' : 'hidden',
    '#title' => t('Default end date'),
    '#default_value' => $settings['default_value2'],
    '#options' => array('same' => t('Same as Default date'), 'blank' => t('No default value'), 'now' => t('Now'), 'strtotime' => t('Relative')),
    '#weight' => 2,
    '#fieldset' => 'default_values',
  );
  $form['default_value_code2'] = array(
    '#type' => !empty($field['settings']['todate']) ? 'textfield' : 'hidden',
    '#title' => t('Relative default value for end date'),
    '#description' => $description,
    '#default_value' => $settings['default_value_code2'],
    '#states' => array(
      'visible' => array(
        ':input[name="instance[settings][default_value2]"]' => array('value' => 'strtotime')),
      ),
    '#weight' => 2.1,
    '#fieldset' => 'default_values',
  );

  $form['all_day_toggle'] = array(
    '#type' => 'select',
    '#title' => t('All day toggle'),
    '#description' => t("Add an 'All day' checkbox to the form to allow the user to hide or show the time."),
    '#default_value' => $settings['all_day_toggle'],
    '#options' => array(0 => t('No'), 1 => t('Yes')),
    '#weight' => 2,
  );


  $form['#element_validate'] = array('date_field_instance_settings_form_validate');

  $context = array(
    'field' => $field,
    'instance' => $instance,
  );
  drupal_alter('date_field_instance_settings_form', $form, $context);

  return $form;
}

/**
 * Form validation handler for _date_field_instance_settings_form().
 */
function date_field_instance_settings_form_validate(&$form, &$form_state) {
  $settings = $form_state['values']['instance']['settings'];

  if ($settings['default_value'] == 'strtotime') {
    $is_strtotime = @strtotime($settings['default_value_code']);
    if (!$is_strtotime) {
      form_set_error('instance][settings][default_value_code', t('The Strtotime default value is invalid.'));
    }
  }
  if (isset($settings['default_value2']) && $settings['default_value2'] == 'strtotime') {
    $is_strtotime = @strtotime($settings['default_value_code2']);
    if (!$is_strtotime) {
      form_set_error('instance][settings][default_value_code2', t('The Strtotime default value for the End Date is invalid.'));
    }
  }
}

/**
 * Helper function for date_field_settings_form().
 *
 * @see date_field_settings_validate()
 */
function _date_field_settings_form($field, $instance, $has_data) {
  $settings = $field['settings'];

  $form = array(
    '#element_validate' => array('date_field_settings_validate'),
  );

  // Make sure granularity is in the right format and has no empty values.
  if (!empty($settings['granularity']) && is_array($settings['granularity'])) {
    $granularity = array_filter($settings['granularity']);
  }
  $tz_handling = $settings['tz_handling'];

  $description = t('Select the date attributes to collect and store.');
  $options = DateGranularity::granularityNames();
  $checkbox_year = array(
    '#type' => 'checkbox',
    '#title' => check_plain($options['year']),
    '#value' => 'year',
    '#return_value' => 'year',
    '#disabled' => TRUE,
  );
  unset($options['year']);
  $form['granularity'] = array(
    '#type' => 'checkboxes',
    '#title' => t('Date attributes to collect'),
    '#default_value' => $granularity,
    '#options' => $options,
    '#attributes' => array('class' => array('container-inline')),
    '#description' => $description,
    '#disabled' => $has_data,
    'year' => $checkbox_year,
  );

  $description = t('End dates are used to collect duration. E.g., allow an event to start on September 15, and end on September 16.');
  $form['enddate_get'] = array(
    '#type' => 'checkbox',
    '#title' => t('Collect an end date'),
    '#description' => $description,
    '#default_value' => (empty($settings['todate']) ? FALSE : TRUE),
    '#disabled' => $has_data,
  );
  $form['enddate_required'] = array(
    '#type' => 'checkbox',
    '#title' => t('Required'),
    '#default_value' => ((isset($settings['todate']) && $settings['todate'] === 'required') ? TRUE : FALSE),
    '#disabled' => $has_data,
    '#states' => array(
      'invisible' => array(
        'input[name="field[settings][enddate_get]"]' => array('checked' => FALSE),
      ),
    ),
  );
  $description = t('Select the timezone handling method for this date field.');
  $form['tz_handling'] = array(
    '#type' => 'select',
    '#title' => t('Time zone handling'),
    '#default_value' => $tz_handling,
    '#options' => date_timezone_handling_options(),
    '#description' => $description,
    '#disabled' => $has_data,
    '#attached' => array(
      'js' => array(drupal_get_path('module', 'date') . '/date_admin.js'),
    ),
  );
  // Force this value to hidden because we don't want to allow it to be changed
  // right now, but allow it to be a variable if needed.
  $form['timezone_db'] = array(
    '#type' => 'hidden',
    '#value' => date_get_timezone_db($tz_handling),
  );

  $form['cache_enabled'] = array(
    '#type' => 'checkbox',
    '#title' => t('Cache dates'),
    '#description' => t('Date objects can be created and cached as date fields are loaded rather than when they are displayed to improve performance.'),
    '#default_value' => !empty($settings['cache_enabled']),
    '#weight' => 10,
  );
  $form['cache_count'] = array(
    '#type' => 'textfield',
    '#title' => t('Maximum dates per field'),
    '#default_value' => (isset($settings['cache_count'])) ? $settings['cache_count'] : 4,
    '#description' => t("If set to '0', all date values on every entity will be cached. Note that caching every date on fields that may have a large number of multiple or repeating values may create a significant performance penalty when the cache is cleared. The suggested setting for multiple value and repeating fields is no more than 4 values per field."),
    '#size' => 3,
    '#weight' => 11,
    '#states' => array(
      'visible' => array(
        'input[name="field[settings][cache_enabled]"]' => array('checked' => TRUE),
      ),
    ),
  );

  $context = array(
    'field' => $field,
    'instance' => $instance,
    'has_data' => $has_data,
  );
  drupal_alter('date_field_settings_form', $form, $context);

  return $form;
}

/**
 * Form validation handler for _date_field_settings_form().
 */
function date_field_settings_validate(&$form, &$form_state) {
  $field = &$form_state['values']['field'];

  if ($field['settings']['tz_handling'] == 'none') {
    form_set_value($form['timezone_db'], '', $form_state);
  }
  else {
    form_set_value($form['timezone_db'], date_get_timezone_db($field['settings']['tz_handling']), $form_state);
  }

  if ($field['settings']['tz_handling'] != 'none' && !in_array('hour', array_filter($field['settings']['granularity']))) {
    form_set_error('field[settings][tz_handling]', t('Dates without hours granularity must not use any timezone handling.'));
  }

  // Extract the correct 'todate' value out of the two end date checkboxes.
  if ($field['settings']['enddate_get']) {
    if ($field['settings']['enddate_required']) {
      $field['settings']['todate'] = 'required';
    }
    else {
      $field['settings']['todate'] = 'optional';
    }
  }
  else {
    $field['settings']['todate'] = '';
  }

  // Don't save the pseudo values created in the UI.
  unset($field['settings']['enddate_get'], $field['settings']['enddate_required']);

  if (!empty($field['settings']['cache_enabled'])) {
    if (!is_numeric($field['settings']['cache_count'])) {
      form_set_error('field[settings][cache_count]', t('The number of cache values must be a number.'));
    }
    elseif ($field['settings']['cache_count'] < 0) {
      form_set_error('field[settings][cache_count]', t('The number of cache values must be a number 0 or greater.'));
    }
  }
}

/**
 * Timezone handling options.
 *
 * The 'none' option will do no timezone conversions and will store and display
 * dates exactly as entered useful in locales or situations where timezone
 * conversions are not working reliably, for dates with no times, for historical
 * dates where timezones are irrelevant, or anytime conversion is unnecessary or
 * undesirable.
 */
function date_timezone_handling_options() {
  return array(
    'site' => t("Site's time zone"),
    'date' => t("Date's time zone"),
    'user' => t("User's time zone"),
    'utc' => 'UTC',
    'none' => t('No time zone conversion'),
  );
}
