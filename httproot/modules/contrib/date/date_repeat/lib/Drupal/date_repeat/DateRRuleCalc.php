<?php
/**
 * @file
 * Code to compute the dates that match an iCal RRULE.
 *
 * Extensive simpletests have been created to test the RRULE calculation
 * results against official examples from RFC 2445.
 *
 * These calculations are expensive and results should be stored or cached
 * so the calculation code is not called more often than necessary.
 *
 * Currently implemented:
 *   INTERVAL, UNTIL, COUNT, EXDATE, RDATE, BYDAY, BYMONTHDAY, BYMONTH,
 *   YEARLY, MONTHLY, WEEKLY, DAILY
 *
 * Not implemented:
 *   BYYEARDAY, MINUTELY, HOURLY, SECONDLY, BYMINUTE, BYHOUR, BYSECOND
 *
 * BYSETPOS
 *   Seldom used anywhere, so no reason to complicated the code.
 */

namespace Drupal\date_repeat;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\date_api\DateiCalParse;

class DateRRuleCalc {

  /**
   * Map the abbreviation used in iCal day names
   * to the day name usable by DateTime and DateInterval.
   */
  public static $day_names = array(
    'SU' => 'Sunday',
    'MO' => 'Monday',
    'TU' => 'Tuesday',
    'WE' => 'Wednesday',
    'TH' => 'Thursday',
    'FR' => 'Friday',
    'SA' => 'Saturday',
    );

  /**
   * Map some common iCal direction values to the text that
   * works more reliably in DateTime::modify().
   */
  public static $date_order = array(
    '+1' => 'First',
    '+2' => 'Second',
    '+3' => 'Third',
    '+4' => 'Fourth',
    '+5' => 'Fifth',
    '-1' => 'Last',
    '-2' => '-2',
    '-3' => '-3',
    '-4' => '-4',
    '-5' => '-5',
    );

  /**
   * The format to use when creating and comparing dates.
   */
  public $default_format = 'Y-m-d H:i:s';

  /**
   * The name of the timezone to use in these computations.
   */
  public $timezone_name = 'UTC';

  /**
   * The time that will be used for all the created dates.
   */
  public $time_string = '00:00:00';

  /**
   * An array of RRULE parts, as parsed by the DateiCalParse.
   */
  public $rrule = array();

  /**
   * A date object for the start of the series.
   */
  public $start_date = NULL;

  /**
   * A date object for the start of the series.
   */
  public $end_date = NULL;

  /**
   * An optional limit on the number of results, as set in the RRULE.
   */
  public $max_count = NULL;

  /**
   * The array of days that match the criteria.
   */
  public $result = array();

  /**
   * The current day, as we iterate through the RRULE.
   */
  public $current_day = NULL;

  /**
   * An array of dates that should not be selected.
   */
  public $exceptions = array();

  /**
   * An array of dates that should be added.
   */
  public $additions = array();

  /**
   * The start day of the week.
   */
  public $week_start_day = 'MO';

  /**
   * A DateInterval representing the amount of time to jump
   * after each iteration of the calculation.
   */
  public $jump = NULL;

  /**
   * The maximum number of times to cycle through this code.
   * Needed to avoid endless loops that check for a COUNT
   * without finding any results. This checks the number of
   * times that $this->is_finished() gets called.
   */
  public $max_cycles = 10000000;

  /**
   * Compute dates that match the requested rule, within a specified
   * date range.
   *
   * @param string $rrule
   *   A string RRULE, in the standard iCal format.
   * @param object $start
   *   A date object to start the series.
   * @param object $end
   *   A date object to end the series, if not ended earlier by UNTIL
   *   or COUNT. Requred unless a COUNT is provided.
   * @param array $this->exceptions
   *   Optional array of exception dates, each in the standard ISO format
   *   of YYYY-MM-DD.
   * @param array $additions
   *   Optional array of additional dates, each in the standard ISO format
   *   of YYYY-MM-DD.
   */
  function __construct($rrule, $start, $end = NULL, $exceptions = array(), $additions = array()) {

    // Get the parsed array of rule values.
    $this->rrule = DateiCalParse::parse_rrule($rrule);

    // Create a date object for the start and end dates, if valid.
    $this->start_date = $start;
    $this->end_date = $end;
    $this->timezone_name = $this->start_date->getTimezone()->getName();

    // Make sure we have something we can work with.
    if (!$this->isValid()) {
      return FALSE;
    }

    // If the rule has an UNTIL, see if that is earlier than the end date.
    if (!empty($this->rrule['UNTIL'])) {
      $until_date = DateiCalParse::ical_date($this->rrule['UNTIL'], $this->timezone_name);
      if (empty($this->end_date) || $until_date < $this->end_date) {
        $this->end_date = $until_date;
      }
    }

    // Versions of PHP greater than PHP 5.3.5 require that we set an
    // explicit time when using date_modify() or the time may not match
    // the original value. Adding this modifier gives us the same
    // results in both older and newer versions of PHP.
    $this->time_string = ' ' . $this->start_date->format('g:ia');

    $this->max_count = isset($this->rrule['COUNT']) ? $this->rrule['COUNT'] : NULL;

    $this->exceptions = $exceptions;
    $this->additions = $additions;

  }

  /**
   * Basic validation for an RRULE we can do something with.
   */
  protected function isValid() {
    // We alwqys need a start date.
    if (!$this->start_date instanceOf \DateTime) {
      return FALSE;
    }
    // The only valid option for an empty end date is when we have a count.
    if (!$this->end_date instanceOf \DateTime && empty($this->rrule['COUNT'])) {
      return FALSE;
    }
    return TRUE;
  }

  public function compute() {

    // Make sure we have something we can work with.
    if (!$this->isValid()) {
      return FALSE;
    }

    if (empty($this->rrule['FREQ'])) {
      $this->rrule['FREQ'] = 'DAILY';
    }

    // These default values indicate there is no RRULE here.
    if ($this->rrule['FREQ'] == 'NONE' || (isset($this->rrule['INTERVAL']) && $this->rrule['INTERVAL'] == 0)) {
      return array();
    }

    // Get an integer value for the interval, if none given, '1'
    // is implied.
    if (empty($this->rrule['INTERVAL'])) {
      $this->rrule['INTERVAL'] = 1;
    }
    $interval = max(1, $this->rrule['INTERVAL']);

    // Make sure DAILY frequency isn't used in places it won't work;
    if (!empty($this->rrule['BYMONTHDAY']) && !in_array($this->rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
      $this->rrule['FREQ'] = 'MONTHLY';
    }
    elseif (!empty($this->rrule['BYDAY']) && !in_array($this->rrule['FREQ'], array('MONTHLY', 'WEEKLY', 'YEARLY'))) {
      $this->rrule['FREQ'] = 'WEEKLY';
    }

    // Find the time period to jump forward between dates.
    switch ($this->rrule['FREQ']) {
     case 'DAILY':
       $jump_interval = 'P' . $interval . 'D';
       break;
     case 'WEEKLY':
       $jump_interval = 'P' . $interval . 'W';
       break;
     case 'MONTHLY':
       $jump_interval = 'P' . $interval . 'M';
       break;
     case 'YEARLY':
       $jump_interval = 'P' . $interval . 'Y';
       break;
    }
    $this->jump = new \DateInterval($jump_interval);

    // Make sure the rrule array has all the values we expect.
    $this->complete_rrule();

    // The start date always goes into the results, whether or not
    // it meets the rules. RFC 2445 includes examples where the start
    // date DOES NOT meet the rules, but the expected results always
    // include the start date.
    $this->result[] = date_format($this->start_date, $this->default_format);

    // BYMONTHDAY will look for specific days of the month in one or
    // more months. This process is only valid when frequency is
    // monthly or yearly.

    if (!empty($this->rrule['BYMONTHDAY'])) {
      $this->get_bymonthday_results();
    }

    // This is the simple fallback case, not looking for any BYDAY,
    // just repeating the start date. Because of imputed BYDAY above, this
    // will only test TRUE for a DAILY or less frequency (like HOURLY).

    elseif (empty($this->rrule['BYDAY'])) {
      $this->get_other_results();
    }

    // More complex searches for day names and criteria like '-1SU'
    // or '2TU,2TH', require that we interate through the whole time
    // period checking each day selected in BYDAY.

    else {
      $this->get_byday_results();
    }

    // Add additional dates, if any.
    foreach ($this->additions as $addition) {
      $date = new DrupalDateTime($addition . ' ' . $this->time_string, $this->timezone_name);
      $this->result[] = date_format($date, $this->default_format);
    }

    // Sort and return the result.
    sort($this->result);
    return $this->result;
  }

  /**
   * Processing other than BYDAY or BYMONTHDAY.
   *
   * This is the simple fallback case, not looking for any specific day,
   * just repeating the start date. The complete_rrule() code ensures this
   * will only test TRUE for a DAILY or less frequency (like HOURLY).
   */
  protected function get_other_results() {
    $this->current_day = clone($this->start_date);
    $finished = FALSE;
    $months = !empty($this->rrule['BYMONTH']) ? $this->rrule['BYMONTH'] : array();
    while (!$finished) {
      $this->add_current_day();
      $finished = $this->is_finished();
      $this->current_day->add($this->jump);
    }
  }

  /**
   * Processing for BYMONTHDAY values.
   *
   * BYMONTHDAY will look for specific days of the month in one or more
   * months. This process is only valid when frequency is monthly or yearly.
   * BYMONTHDAY values will look like '11' (the 11th day) or '-1'
   * (the last day) or '10,11,12' (the 10th, 11th, and 12th days).
   */
  protected function get_bymonthday_results() {

    $finished = FALSE;
    $time = $this->time_string;

    $this->current_day = clone($this->start_date);
    $month_days = array();
    // Deconstruct the day in case it has a negative modifier.
    foreach ($this->rrule['BYMONTHDAY'] as $day) {
      preg_match("@(-)?([0-9]{1,2})@", $day, $regs);
      if (!empty($regs[2])) {
        // Convert parameters into count, and direction.
        $month_days[$day] = array(
          'direction' => !empty($regs[1]) ? $regs[1] : '+',
          'count' => $regs[2],
          );
      }
    }
    while (!$finished) {

      $year_finished = FALSE;
      while (!$year_finished) {
        // Check each requested day in the month.
        foreach ($this->rrule['BYMONTHDAY'] as $monthday) {
          $day = $month_days[$monthday];
          if ($this->set_month_day(NULL, $day['count'], $day['direction'])) {
            $this->add_current_day();
          }
          if ($finished = $this->is_finished()) {
            $year_finished = TRUE;
          }
        }
        switch ($this->rrule['FREQ']) {
          case 'MONTHLY':
            // If it's monthly, keep looping through months.
            if ($finished = $this->is_finished()) {
              $year_finished = TRUE;
            }
            // Back up to first of month and jump.
            $this->current_day->modify("first day of this month $time");
            $this->current_day->add($this->jump);
            break;

          case 'YEARLY':
            // If it's yearly, break out of the loop at the
            // end of every year.
            if ($this->current_day->format('n') == 12) {
              $year_finished = TRUE;
            }
            else {
              // Jump to first day of next month.
              $this->current_day->modify("first day of next month $time");
            }
            break;
        }
      }

      if ($this->rrule['FREQ'] == 'YEARLY') {
        // Back up to first of year and jump to next year.
        $this->current_day->modify("this year January 1");
        $this->current_day->add($this->jump);
      }
      $finished = $this->is_finished();
    }
  }

  /**
   * Processing for BYDAY values.
   *
   * More complex searches for day names and criteria like '-1SU'
   * or '2TU,2TH', require that we interate through the whole time
   * period checking each day selected in BYDAY.
   */
  protected function get_byday_results() {

    // Create helper array to pull day names out of iCal day strings.
    $day_names = self::$day_names;
    $this->days_of_week = array_keys($day_names);

    // Parse out information about the BYDAYs and separate them
    // depending on whether they have directional parameters
    // like -1SU or 2TH.
    $week_days = array();

    // Find the right first day of the week to use, iCal rules say
    // Monday should be used if none is specified.
    $week_start_rule = !empty($this->rrule['WKST']) ? trim($this->rrule['WKST']) : 'MO';
    $this->week_start_day = $day_names[$week_start_rule];

    // Make sure the week days array is sorted into week order,
    // we use the $ordered_keys to get the right values into the key
    // and force the array to that order. Needed later when we
    // iterate through each week looking for days so we don't
    // jump to the next week when we hit a day out of order.
    $ordered = date_repeat_days_ordered($week_start_rule);
    $ordered_keys = array_flip($ordered);
    foreach ($this->rrule['BYDAY'] as $day) {
      preg_match("@(-)?([0-9]+)?([SU|MO|TU|WE|TH|FR|SA]{2})@", trim($day), $regs);
      if (!empty($regs[2])) {
        // Convert parameters into full day name, count, and direction.
        $relative_days[] = array(
          'day' => $day_names[$regs[3]],
          'direction' => !empty($regs[1]) ? $regs[1] : '+',
          'direction_count' => $regs[2],
          );
      }
      else {
        $week_days[$ordered_keys[$regs[3]]] = $day_names[$regs[3]];
      }
    }
    ksort($week_days);

    // Get BYDAYs with parameters like -1SU (last Sun) or
    // 2TH (second Thur).
    if (!empty($relative_days) && in_array($this->rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
      $this->get_relative_bydays($relative_days);
    }

    // Get BYDAYs without parameters,like TU,TH (every
    // Tues and Thur).
    if (!empty($week_days) && in_array($this->rrule['FREQ'], array('MONTHLY', 'WEEKLY', 'YEARLY'))) {
      $this->get_absolute_bydays($week_days);
    }
  }

  /**
   * Get results for relative BYDAY values.
   *
   * BYDAYs with parameters like -1SU (last Sun) or
   * 2TH (second Thur) need to be processed one month or
   * year at a time.
   */
  protected function get_relative_bydays($relative_days) {
    $finished = FALSE;
    $this->current_day = clone($this->start_date);
    while (!$finished) {
      foreach ($relative_days as $day) {
        // Find the BYDAY date in the current period.
        switch($this->rrule['FREQ']) {
          case 'MONTHLY':
            if ($this->set_month_day($day['day'], $day['direction_count'], $day['direction'])) {
              $this->add_current_day();
            }
            break;
          case 'YEARLY':
            if ($this->set_year_day($day['day'], $day['direction_count'], $day['direction'])) {
              $this->add_current_day();
            }
            break;
        }
      }
      $finished = $this->is_finished();
      // Reset to beginning of period before jumping to next period.
      // Needed especially when working with values like 'last
      // Saturday' to be sure we don't skip months like February.
      $year = $this->current_day->format('Y');
      $month = $this->current_day->format('n');
      switch($this->rrule['FREQ']) {
        case 'MONTHLY':
          date_date_set($this->current_day, $year, $month, 1);
          break;
        case 'YEARLY':
          date_date_set($this->current_day, $year, 1, 1);
          break;
      }
      // Jump to the next period.
      $this->current_day->add($this->jump);
    }
  }

  /**
   * Get values for absolute BYDAYs.
   *
   * For BYDAYs without parameters,like TU,TH (every Tues and Thur),
   * we look for every one of those days during the frequency period.
   * Iterate through periods of a WEEK, MONTH, or YEAR, checking for
   * the days of the week that match our criteria for each week in the
   * period, then jumping ahead to the next week, month, or year,
   * an INTERVAL at a time.
  */
  protected function get_absolute_bydays($week_days) {
    $finished = FALSE;
    $this->current_day = clone($this->start_date);
    $format = $this->rrule['FREQ'] == 'YEARLY' ? 'Y' : 'n';
    $current_period = $this->current_day->format($format);

    // Back up to the beginning of the week in case we are somewhere
    // in the middle of the possible week days, needed so we don't
    // prematurely jump to the next week. The add_dates() function
    // will keep dates outside the range from getting added.
    if ($this->current_day->format('l') != $this->week_start_day) {
      date_modify($this->current_day, 'last ' . $this->week_start_day . $this->time_string);
    }
    while (!$finished) {
      $period_finished = FALSE;
      while (!$period_finished) {
        $moved = FALSE;
        foreach ($week_days as $delta => $day) {
          // Find the next occurence of each day in this week, only
          // add it if we are still in the current month or year. The
          // add_current_date() function is insufficient to test whether
          // to include this date if we are using a rule like 'every
          // other month', so we must explicitly test it here.

          // If we're already on the right day, don't jump or we
          // will prematurely move into the next week.
          if ($this->current_day->format('l') != $day) {
            date_modify($this->current_day, '+1 ' . $day . $this->time_string);
            $moved = TRUE;
          }
          if ($this->rrule['FREQ'] == 'WEEKLY' || $this->current_day->format($format) == $current_period) {
            $this->add_current_day();
          }
        }
        $finished = $this->is_finished();

        // Make sure we don't get stuck in endless loop if the current
        // day never got changed above.
        if (!$moved) {
          date_modify($this->current_day, '+1 day' . $this->time_string);
        }

        // If this is a WEEKLY frequency, stop after each week,
        // otherwise, stop when we've moved outside the current period.
        // Jump to the end of the week, then test the period.
        if ($finished || $this->rrule['FREQ'] == 'WEEKLY') {
          $period_finished = TRUE;
        }
        elseif ($this->rrule['FREQ'] != 'WEEKLY' && $this->current_day->format($format) != $current_period) {
          $period_finished = TRUE;
        }
      }

      if ($finished) {
        continue;
      }

      // We'll be at the end of a week, month, or year when
      // we get to this point in the code.

      // Go back to the beginning of this period before we jump, to
      // ensure we jump to the first day of the next period.
      switch ($this->rrule['FREQ']) {
        case 'WEEKLY':
          date_modify($this->current_day, '+1 ' . $this->week_start_day . $this->time_string);
          date_modify($this->current_day, '-1 week' . $this->time_string);
          break;
        case 'MONTHLY':
          date_modify($this->current_day, '-' . ($this->current_day->format('j') - 1) . ' days' . $this->time_string);
          date_modify($this->current_day, '-1 month' . $this->time_string);
          break;
        case 'YEARLY':
          date_modify($this->current_day, '-' . $this->current_day->format('z') . ' days' . $this->time_string);
          date_modify($this->current_day, '-1 year' . $this->time_string);
          break;
      }
      // Jump ahead to the next period to be evaluated.
      $this->current_day->add($this->jump);
      $current_period = $this->current_day->format($format);
      $finished = $this->is_finished();
    }
  }

  /**
   * See if the RRULE needs some imputed values added to it.
   */
  protected function complete_rrule() {

    // If this is not a valid value, do nothing;
    if (empty($this->rrule) || empty($this->rrule['FREQ'])) {
      return FALSE;
    }

    // RFC 2445 says if no day or monthday is specified when creating repeats
    // for weeks, months, or years, impute the value from the start date.

    if (empty($this->rrule['BYDAY']) && $this->rrule['FREQ'] == 'WEEKLY') {
      $this->rrule['BYDAY'] = array(date_repeat_dow2day($this->start_date->format('w')));
    }
    elseif (empty($this->rrule['BYDAY']) && empty($this->rrule['BYMONTHDAY']) && $this->rrule['FREQ'] == 'MONTHLY') {
      $this->rrule['BYMONTHDAY'] = array($this->start_date->format('j'));
    }
    elseif (empty($this->rrule['BYDAY']) && empty($this->rrule['BYMONTHDAY']) && empty($this->rrule['BYYEARDAY']) && $this->rrule['FREQ'] == 'YEARLY') {
      $this->rrule['BYMONTHDAY'] = array($this->start_date->format('j'));
      if (empty($this->rrule['BYMONTH'])) {
        $this->rrule['BYMONTH'] = array($this->start_date->format('n'));
      }
    }
    // If we are processing rules for period other than YEARLY or MONTHLY
    // and have BYDAYS like 2SU or -1SA, simplify them to SU or SA since the
    // position rules make no sense in other periods and just add complexity.

    elseif (!empty($this->rrule['BYDAY']) && !in_array($this->rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
      foreach ($this->rrule['BYDAY'] as $delta => $BYDAY) {
        $this->rrule['BYDAY'][$delta] = substr($BYDAY, -2);
      }
    }
  }

  /**
   * Helper function to add current date to the $dates array.
   *
   * Check that the date to be added is between the start and end date
   * and that it is not in the $this->exceptions, nor already in the
   * $this->result array, and that it meets other criteria in the RRULE.
   */
  protected function add_current_day() {
    if (!empty($this->max_count) && sizeof($this->result) >= $this->max_count) {
      return FALSE;
    }
    if (!empty($this->end_date) && $this->current_day > $this->end_date) {
      return FALSE;
    }
    if ($this->current_day < $this->start_date) {
      return FALSE;
    }
    if (in_array($this->current_day->format('Y-m-d'), $this->exceptions)) {
      return FALSE;
    }
    if (!empty($this->rrule['BYDAY'])) {
      $BYDAYS = $this->rrule['BYDAY'];
      foreach ($BYDAYS as $delta => $BYDAY) {
        $BYDAYS[$delta] = substr($BYDAY, -2);
      }
      if (!in_array(date_repeat_dow2day($this->current_day->format('w')), $BYDAYS)) {
        return FALSE;
      }}
    if (!empty($this->rrule['BYYEAR']) && !in_array($this->current_day->format('Y'), $this->rrule['BYYEAR'])) {
      return FALSE;
    }
    if (!empty($this->rrule['BYMONTH']) && !in_array($this->current_day->format('n'), $this->rrule['BYMONTH'])) {
      return FALSE;
    }
    if (!empty($this->rrule['BYMONTHDAY'])) {
      // Test month days, but only if there are no negative numbers.
      $test = TRUE;
      $BYMONTHDAYS = array();
      foreach ($this->rrule['BYMONTHDAY'] as $day) {
        if ($day > 0) {
          $BYMONTHDAYS[] = $day;
        }
        else {
          $test = FALSE;
          break;
        }
      }
      if ($test && !empty($BYMONTHDAYS) && !in_array($this->current_day->format('j'), $BYMONTHDAYS)) {
        return FALSE;
      }
    }
    // Don't add a day if it is already saved so we don't throw the count off.
    $formatted = $this->current_day->format($this->default_format);
    if (in_array($formatted, $this->result)) {
      return TRUE;
    }
    else {
      $this->result[] = $formatted;
    }
  }

  /**
   * Stop when $this->current_day is greater than $this->end_date
   * or $this->max_count is reached.
   */
  protected function is_finished() {
    static $cycles;
    $cycles++;

    if (!empty($this->max_count) && sizeof($this->result) >= $this->max_count) {
      return TRUE;
    }
    elseif (!empty($this->end_date) && $this->current_day > $this->end_date) {
      return TRUE;
    }
    // The bail out when looking for a COUNT number of values
    // but nothing is found.
    elseif ($cycles >= $this->max_cycles) {
      return TRUE;
    }

    // Nothing tells us we are finished.
    return FALSE;
  }

  /**
   * Set a date object to a specific day of the month.
   *
   * Example,
   *   set_month_day('Sunday', 2, '-')
   *   will reset $date to the second to last Sunday in the month.
   *   If $day is empty, will set to the number of days from the
   *   beginning or end of the month.
   */
  protected function set_month_day($day, $count = 1, $direction = '+') {
    $time = $this->time_string;
    $current_month = $this->current_day->format('n');

    // Create a clone and reset.
    $date = clone($this->current_day);
    if ($direction == '-') {
      // For negative search, start from just outside the end
      // of the month, so we can catch the last day of the month.
      $date->modify("first day of next month $time");
    }
    else {
      // For positive search, back up one day to get outside the
      // current month, so we can catch the first of the month.
      $date->modify("last day of last month $time");
    }

    if (empty($day)) {
      $date->modify("$direction $count days $time");
    }
    else {
      // Use the English text for order, like First Sunday
      // instead of +1 Sunday to overcome PHP5 bug, (see #369020).
      $order = self::$date_order;
      $step = $count <= 5 ? $order[$direction . $count] : $direction . $count;
      $date->modify("$step $day $time");
    }

    // If that takes us outside the current month, don't go there,
    // only reset the date if it's in the current month.
    if ($date->format('n') == $current_month) {
      $this->current_day = $date;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Set a date object to a specific day of the year.
   *
   * Example,
   *   date_set_year_day($date, 'Sunday', 2, '-')
   *   will reset $date to the second to last Sunday in the year.
   *   If $day is empty, will set to the number of days from the
   *   beginning or end of the year.
   */
  protected function set_year_day($day, $count = 1, $direction = '+') {
    $time = $this->time_string;
    $current_year = $this->current_day->format('Y');

    // Create a clone and reset.
    $date = clone($this->current_day);
    if ($direction == '-') {
      // For negative search, start from the end of the year.
      // It is important to set year before month for some reason.
      $date->modify("next year January 1 $time");
    }
    else {
      // For positive search, back up one day to get outside the
      // current year, so we can catch the first of the year.
      // It is important to set year before month for some reason.
      $date->modify("last year December 31 $time");
    }
    if (empty($day)) {
      $date->modify("$direction $count days $time");
    }
    else {
      // Use the English text for order, like First Sunday
      // instead of +1 Sunday to overcome PHP5 bug, (see #369020).
      $order = self::$date_order;
      $step = $count <= 5 ? $order[$direction . $count] : $direction . $count;
      $date->modify("$step $day $time");
    }

    // If that takes us outside the current year, don't go there.
    if ($date->format('Y') == $current_year) {
      $this->current_day = $date;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
}