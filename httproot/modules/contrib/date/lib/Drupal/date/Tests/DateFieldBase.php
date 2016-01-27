<?php

/**
 * @file
 * Basic functions for Date tests.
 */

namespace Drupal\date\Tests;

use Drupal\simpletest\WebTestBase;

abstract class DateFieldBase extends WebTestBase {
  protected $privileged_user;

  /**
   * Set up required modules.
   */
  public static $modules = array('field', 'field_ui', 'date_api', 'date', 'date_popup', 'date_tools');

  /**
   * @todo.
   */
  protected function setUp() {
    parent::setUp();

    // Create and log in our privileged user.
    $this->privileged_user = $this->drupalCreateUser(
      array('administer content types', 'administer nodes', 'bypass node access', 'administer date tools')
    );
    $this->drupalLogin($this->privileged_user);

    config('date_popup.settings')->set('timepicker', 'none')->save();

    module_load_include('inc', 'node', 'content_types');
    module_load_include('inc', 'node', 'node.pages');
    module_load_include('inc', 'field', 'field.crud');
    module_load_include('inc', 'date', 'date_admin');

    $edit = array();
    $edit['name'] = 'Story';
    $edit['type'] = 'story';
    $this->drupalPost('admin/structure/types/add', $edit, t('Save content type'));
    $this->assertText('The content type Story has been added.', 'Content type added.');

  }

  /**
   * Creates a date field from an array of settings values.
   *
   * All values have defaults, only need to specify values that need to be
   * different.
   */
  protected function createDateField($values = array()) {
    extract($values);

    $field_name = !empty($field_name) ? $field_name : 'field_test';
    $entity_type = !empty($entity_type) ? $entity_type : 'node';
    $bundle = !empty($bundle) ? $bundle : 'story';
    $label = !empty($label) ? $label : 'Test';
    $field_type = !empty($field_type) ? $field_type : 'datetime';
    $repeat = !empty($repeat) ? $repeat : 0;
    $todate = !empty($todate) ? $todate : 'optional';
    $widget_type = !empty($widget_type) ? $widget_type : 'date_select';
    $tz_handling = !empty($tz_handing) ? $tz_handling : 'site';
    $granularity = !empty($granularity) ? $granularity : array('year', 'month', 'day', 'hour', 'minute');
    $year_range = !empty($year_range) ? $year_range : '2010:+1';
    $input_format = !empty($input_format) ? $input_format : variable_get('date_format_html_date', 'Y-m-d') . ' ' . variable_get('date_format_html_time', 'H:i:s');
    $input_format_custom = !empty($input_format_custom) ? $input_format_custom : '';
    $text_parts = !empty($text_parts) ? $text_parts : array();
    $increment = !empty($increment) ? $increment : 15;
    $default_value = !empty($default_value) ? $default_value : 'now';
    $default_value2 = !empty($default_value2) ? $default_value2 : 'blank';
    $default_format = !empty($default_format) ? $default_format : 'long';
    $cache_enabled = !empty($cache_enabled);
    $cache_count = !empty($cache_count) ? $cache_count : 4;

    $field = array(
      'field_name' => $field_name,
      'type' => $field_type,
      'cardinality' => !empty($repeat) ? FIELD_CARDINALITY_UNLIMITED : 1,
      'settings' => array(
        'granularity' => $granularity,
        'tz_handling' => $tz_handling,
        'timezone_db' => date_get_timezone_db($tz_handling),
        'repeat' => $repeat,
        'todate' => $todate,
        'cache_enabled' => $cache_enabled,
        'cache_count' => $cache_count,
      ),
    );
    $instance = array(
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'label' => $label,
      'bundle' => $bundle,
      // Move the date right below the title.
      'weight' => -4,
      'widget' => array(
        'type' => $widget_type,
        // Increment for minutes and seconds, can be 1, 5, 10, 15, or 30.
        'settings' => array(
          'increment' => $increment,
          // The number of years to go back and forward in drop-down year
          // selectors.
          'year_range' => $year_range,
          'input_format' => $input_format,
          'input_format_custom' => $input_format_custom,
          'text_parts' => $text_parts,
          'label_position' => 'above',
        ),
        'weight' => -4,
      ),
      'settings' => array(
        'default_value' => $default_value,
        'default_value2' => $default_value2,
      ),
    );

    $instance['display'] = array(
      'default' => array(
        'label' => 'above',
        'type' => 'date_default',
        'settings' => array(
          'format_type' => $default_format,
          'show_repeat_rule' => 'show',
          'multiple_number' => '',
          'multiple_from' => '',
          'multiple_to' => '',
          'fromto' => 'both',
        ),
        'module' => 'date',
        'weight' => 0 ,
      ),
      'teaser' => array(
        'label' => 'above',
        'type' => 'date_default',
        'weight' => 0,
        'settings' => array(
          'format_type' => $default_format,
          'show_repeat_rule' => 'show',
          'multiple_number' => '',
          'multiple_from' => '',
          'multiple_to' => '',
          'fromto' => 'both',
        ),
        'module' => 'date',
      ),
    );

    $field = field_create_field($field);
    $instance = field_create_instance($instance);

    field_info_cache_clear(TRUE);
    field_cache_clear(TRUE);

    // Look at how the field got configured.
    $this->drupalGet("admin/structure/types/manage/$bundle/fields/$field_name");
    $this->drupalGet("admin/structure/types/manage/$bundle/display");

  }

  /**
   * @todo.
   */
  protected function deleteDateField($label, $bundle = 'story', $bundle_name = 'Story') {
    $this->drupalGet("admin/structure/types/manage/$bundle/fields");
    $this->clickLink('delete');
    $this->drupalPost(NULL, NULL, t('Delete'));
    $this->assertText("The field $label has been deleted from the $bundle_name content type.", 'Removed date field.');
  }

}
