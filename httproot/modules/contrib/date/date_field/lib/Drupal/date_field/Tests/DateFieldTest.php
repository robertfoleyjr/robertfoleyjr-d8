<?php

/**
 * @file
 * Basic functions for Date tests.
 */

namespace Drupal\date\Tests;

use Drupal\simpletest\WebTestBase;

class DateFieldTest extends DateFieldBase {

  /**
   * @todo.
   */
  public static function getInfo() {
    return array(
      'name' => 'Date Field',
      'description' => 'Test date field settings and Start/End date interaction.',
      'group' => 'Date',
    );
  }

  /**
   * @todo.
   */
  public function testField() {
    // Create a date fields with simple values.
    foreach (array('date') as $field_type) {
      foreach (array('date_select', 'date_popup') as $widget_type) {
        $field_name = "field_test_$widget_type";
        $label = 'Test';
        $options = array(
          'label' => $label,
          'widget_type' => $widget_type,
          'field_name' => $field_name,
          'field_type' => $field_type,
          'input_format' => 'm/d/Y - H:i',
        );
        $this->createDateField($options);
        $this->dateForm($field_name, $field_type, $widget_type);
        $this->deleteDateField($label);
      }
    }
  }

  /**
   * @todo.
   */
  public function dateForm($field_name, $field_type, $widget_type, $todate = TRUE) {
    // Tests that date field functions properly.
    $edit = array();
    $edit['title'] = $this->randomName(8);
    if ($widget_type == 'date_select') {
      $edit[$field_name . '[und][0][value][year]'] = '2010';
      $edit[$field_name . '[und][0][value][month]'] = '10';
      $edit[$field_name . '[und][0][value][day]'] = '7';
      $edit[$field_name . '[und][0][value][hour]'] = '10';
      $edit[$field_name . '[und][0][value][minute]'] = '30';
      if ($todate) {
        $edit[$field_name . '[und][0][show_todate]'] = '1';
        $edit[$field_name . '[und][0][value2][year]'] = '2010';
        $edit[$field_name . '[und][0][value2][month]'] = '10';
        $edit[$field_name . '[und][0][value2][day]'] = '7';
        $edit[$field_name . '[und][0][value2][hour]'] = '11';
        $edit[$field_name . '[und][0][value2][minute]'] = '30';
      }
    }
    elseif ($widget_type == 'date_popup') {
      $edit[$field_name . '[und][0][value][date]'] = '10/07/2010';
      $edit[$field_name . '[und][0][value][time]'] = '10:30';
      if ($todate) {
        $edit[$field_name . '[und][0][show_todate]'] = '1';
        $edit[$field_name . '[und][0][value2][date]'] = '10/07/2010';
        $edit[$field_name . '[und][0][value2][time]'] = '11:30';
      }
    }
    // Test that the date is displayed correctly using both the 'short' and
    // 'long' date types.
    //
    // For the short type, save an explicit format and assert that is the one
    // which is displayed.
    variable_set('date_format_short', 'l, m/d/Y - H:i:s');
    $instance = field_info_instance('node', $field_name, 'story');
    $instance['display']['default']['settings']['format_type'] = 'short';
    field_update_instance($instance);
    $this->drupalPost('node/add/story', $edit, t('Save'));
    $this->assertText($edit['title'], "Node has been created");
    $should_be = $todate ? 'Thursday, 10/07/2010 - 10:30 to 11:30' : 'Thursday, 10/07/2010 - 10:30';
    $this->assertText($should_be, "Found the correct date for a $field_type field using the $widget_type widget displayed using the short date format.");
    // For the long format, do not save anything, and assert that the displayed
    // date uses the expected default value of this format provided by Drupal
    // core ('l, F j, Y - H:i').
    $instance = field_info_instance('node', $field_name, 'story');
    $instance['display']['default']['settings']['format_type'] = 'long';
    field_update_instance($instance);
    $this->drupalPost('node/add/story', $edit, t('Save'));
    $this->assertText($edit['title'], "Node has been created");
    $should_be = $todate ? 'Thursday, October 7, 2010 - 10:30 to 11:30' : 'Thursday, October 7, 2010 - 10:30';
    $this->assertText($should_be, "Found the correct date for a $field_type field using the $widget_type widget displayed using the long date format.");
  }
}
