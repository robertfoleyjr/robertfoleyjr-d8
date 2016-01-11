<?php

/**
 * Implements hook_date_field_date_callbacks().
 *
 * Returns an array of available callbacks for the date element of the datepicker widget.
 */
function hook_date_field_date_callbacks() {
  return array(
    'datetime_jquery_datepicker' => t('jQuery popup datepicker'),
  );
}

/**
 * Implements hook_date_field_time_callbacks().
 *
 * Returns an array of available callbacks for the time element of the datepicker widget.
 */
function hook_date_field_time_callbacks() {
  return array(
    'date_field_all_day_toggle_callback' => t('All day checkbox toggle'),
  );
}
