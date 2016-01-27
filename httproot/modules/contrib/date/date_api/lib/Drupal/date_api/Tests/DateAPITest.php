<?php

/**
 * @file
 * Test Date API functions
 */

namespace Drupal\date_api\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Core\Datetime\DrupalDateTime;
use DateTimeZone;

class DateAPITest extends WebTestBase {
  /**
   * @todo.
   */
  public static function getInfo() {
    return array(
      'name' => t('Date API'),
      'description' => t('Test Date API functions.') ,
      'group' => t('Date'),
    );
  }

  /**
   * Set up required modules.
   */
  public static $modules = array('date_api');

  /**
   * @todo.
   */
  public function setUp() {
    parent::setUp();
    config('date_api.settings')->set('iso8601', FALSE)->save();
    variable_set('date_first_day', 1);
  }

  /**
   * @todo.
   */
  public function testDateAPI() {

    $calendar = system_calendar();

    // Test the order of the weeks days for a calendar that starts on Monday and
    // one that starts on Sunday.
    variable_set('date_first_day', 1);
    $expected = array(0 => t('Mon'), 1 => t('Tue'), 2 => t('Wed'), 3 => t('Thu'), 4 => t('Fri'), 5 => t('Sat'), 6 => t('Sun'));
    $days = $calendar->week_days_ordered($calendar->week_days_abbr(1));
    $this->assertEqual($expected, $days, 'Test that $calendar->week_days_ordered() array starts on Monday when the site first day is on Monday.');
    variable_set('date_first_day', 0);
    $expected = array(0 => t('Sun'), 1 => t('Mon'), 2 => t('Tue'), 3 => t('Wed'), 4 => t('Thu'), 5 => t('Fri'), 6 => t('Sat'));
    $days = $calendar->week_days_ordered($calendar->week_days_abbr(1));
    $this->assertEqual($expected, $days, 'Test that $calendar->week_days_ordered() array starts on Sunday when the site first day is on Sunday.');

    // Test days in February for a leap year and a non-leap year.
    $expected = 28;
    $date = new DrupalDateTime(array('year' => 2005, 'month' => 2));
    $value = $calendar->days_in_month($date);
    $this->assertEqual($expected, $value, "Test \$calendar->days_in_month(2, 2005): should be $expected, found $value.");
    $expected = 29;
    $date = new DrupalDateTime(array('year' => 2004, 'month' => 2));
    $value = $calendar->days_in_month($date);
    $this->assertEqual($expected, $value, "Test \$calendar->days_in_month(2, 2004): should be $expected, found $value.");

    // Test days in year for a leap year and a non-leap year.
    $expected = 365;
    $date = new DrupalDateTime('2005-06-01 00:00:00');
    $value = $calendar->days_in_year($date);
    $this->assertEqual($expected, $value, "Test \$calendar->days_in_year(2005-06-01): should be $expected, found $value.");
    $expected = 366;
    $date = new DrupalDateTime('2004-06-01 00:00:00');
    $value = $calendar->days_in_year($date);
    $this->assertEqual($expected, $value, "Test \$calendar->days_in_year(2004-06-01): should be $expected, found $value.");

    // Test ISO weeks for a leap year and a non-leap year.
    $expected = 52;
    $value = date_iso_weeks_in_year('2008-06-01 00:00:00');
    $this->assertEqual($expected, $value, "Test date_iso_weeks_in_year(2008-06-01): should be $expected, found $value.");
    $expected = 53;
    $value = date_iso_weeks_in_year('2009-06-01 00:00:00');
    $this->assertEqual($expected, $value, "Test date_iso_weeks_in_year(2009-06-01): should be $expected, found $value.");

    // Test day of week for March 1, the day after leap day.
    $expected = 6;
    $date = new DrupalDateTime('2008-03-01 00:00:00');
    $value = $calendar->day_of_week($date);
    $this->assertEqual($expected, $value, "Test \$calendar->day_of_week(2008-03-01): should be $expected, found $value.");
    $expected = 0;
    $date = new DrupalDateTime('2009-03-01 00:00:00');
    $value = $calendar->day_of_week($date);
    $this->assertEqual($expected, $value, "Test \$calendar->day_of_week(2009-03-01): should be $expected, found $value.");

    // Test day of week name for March 1, the day after leap day.
    $expected = 'Sat';
    $date = new DrupalDateTime('2008-03-01 00:00:00');
    $value = $calendar->day_of_week_name($date);
    $this->assertEqual($expected, $value, "Test \$calendar->day_of_week_name(2008-03-01): should be $expected, found $value.");
    $expected = 'Sun';
    $date = new DrupalDateTime('2009-03-01 00:00:00');
    $value = $calendar->day_of_week_name($date);
    $this->assertEqual($expected, $value, "Test \$calendar->day_of_week_name(2009-03-01): should be $expected, found $value.");

    // Test week range with calendar weeks.
    variable_set('date_first_day', 0);
    $expected = '2008-01-27 to 2008-02-03';
    $result = date_calendar_week_range(5, 2008);
    $value = $result[0]->format(DATE_FORMAT_DATE) . ' to ' . $result[1]->format(DATE_FORMAT_DATE);
    $this->assertEqual($expected, $value, "Test calendar date_calendar_week_range(5, 2008): should be $expected, found $value.");
    $expected = '2009-01-25 to 2009-02-01';
    $result = date_calendar_week_range(5, 2009);
    $value = $result[0]->format(DATE_FORMAT_DATE) . ' to ' . $result[1]->format(DATE_FORMAT_DATE);
    $this->assertEqual($expected, $value, "Test calendar date_calendar_week_range(5, 2009): should be $expected, found $value.");

    // And now with ISO weeks.
    variable_set('date_first_day', 1);
    $expected = '2008-01-28 to 2008-02-04';
    $result = date_iso_week_range(5, 2008);
    $value = $result[0]->format(DATE_FORMAT_DATE) . ' to ' . $result[1]->format(DATE_FORMAT_DATE);
    $this->assertEqual($expected, $value, "Test ISO date_iso_week_range(5, 2008): should be $expected, found $value.");
    $expected = '2009-01-26 to 2009-02-02';
    $result = date_iso_week_range(5, 2009);
    $value = $result[0]->format(DATE_FORMAT_DATE) . ' to ' . $result[1]->format(DATE_FORMAT_DATE);
    $this->assertEqual($expected, $value, "Test ISO date_iso_week_range(5, 2009): should be $expected, found $value.");
    config('date_api.settings')->set('iso8601', FALSE)->save();

    // Find calendar week for a date.
    variable_set('date_first_day', 0);
    $expected = '09';
    $value = date_calendar_week('2008-03-01');
    $this->assertEqual($expected, $value, "Test date_calendar_week(2008-03-01): should be $expected, found $value.");
    $expected = '10';
    $value = date_calendar_week('2009-03-01');
    $this->assertEqual($expected, $value, "Test date_calendar_week(2009-03-01): should be $expected, found $value.");

    // Test date ranges.
    $valid = array(
      '-20:+20',
      '-1:+0',
      '-10:-5',
      '2000:2020',
      '-10:2010',
      '1980:-10',
      '1920:+20',
    );
    $invalid = array(
      'abc',
      'abc:+20',
      '1920:+20a',
      '+-20:+-30',
      '12:12',
      '0:+20',
      '-20:0',
    );
    foreach ($valid as $range) {
      $this->assertTrue(date_range_valid($range), "$range recognized as a valid date range.");
    }
    foreach ($invalid as $range) {
      $this->assertFalse(date_range_valid($range), "$range recognized as an invalid date range.");
    }

  }

  /**
   * @todo.
   */
  public function tearDown() {
    variable_del('date_first_day');
    parent::tearDown();
  }
}
