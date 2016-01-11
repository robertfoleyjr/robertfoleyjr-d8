<?php

/**
 * @file
 * Parse iCal data.
 */
namespace Drupal\date_api;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Return an array of iCalendar information from an iCalendar file.
 *
 *   No timezone adjustment is performed in the import since the timezone
 *   conversion needed will vary depending on whether the value is being
 *   imported into the database (when it needs to be converted to UTC), is being
 *   viewed on a site that has user-configurable timezones (when it needs to be
 *   converted to the user's timezone), if it needs to be converted to the
 *   site timezone, or if it is a date without a timezone which should not have
 *   any timezone conversion applied.
 *
 *   Properties that have dates and times are converted to sub-arrays like:
 *      'datetime'   => date in YYYY-MM-DD HH:MM format, not timezone adjusted
 *      'all_day'    => whether this is an all-day event
 *      'tz'         => the timezone of the date, could be blank for absolute
 *                      times that should get no timezone conversion.
 *
 *   Exception dates can have muliple values and are returned as arrays
 *   like the above for each exception date.
 *
 *   Most other properties are returned as PROPERTY => VALUE.
 *
 *   Each item in the VCALENDAR will return an array like:
 *   [0] => Array (
 *     [TYPE] => VEVENT
 *     [UID] => 104
 *     [SUMMARY] => An example event
 *     [URL] => http://example.com/node/1
 *     [DTSTART] => Array (
 *       [datetime] => 1997-09-07 09:00:00
 *       [all_day] => 0
 *       [tz] => US/Eastern
 *     )
 *     [DTEND] => Array (
 *       [datetime] => 1997-09-07 11:00:00
 *       [all_day] => 0
 *       [tz] => US/Eastern
 *     )
 *     [RRULE] => Array (
 *       [FREQ] => Array (
 *         [0] => MONTHLY
 *       )
 *       [BYDAY] => Array (
 *         [0] => 1SU
 *         [1] => -1SU
 *       )
 *     )
 *     [EXDATE] => Array (
 *       [0] = Array (
 *         [datetime] => 1997-09-21 09:00:00
 *         [all_day] => 0
 *         [tz] => US/Eastern
 *       )
 *       [1] = Array (
 *         [datetime] => 1997-10-05 09:00:00
 *         [all_day] => 0
 *         [tz] => US/Eastern
 *       )
 *     )
 *     [RDATE] => Array (
 *       [0] = Array (
 *         [datetime] => 1997-09-21 09:00:00
 *         [all_day] => 0
 *         [tz] => US/Eastern
 *       )
 *       [1] = Array (
 *         [datetime] => 1997-10-05 09:00:00
 *         [all_day] => 0
 *         [tz] => US/Eastern
 *       )
 *     )
 *   )
 *
 * @todo
 *   figure out how to handle this if subgroups are nested,
 *   like a VALARM nested inside a VEVENT.
 *
 * @param string $filename
 *   Location (local or remote) of a valid iCalendar file.
 *
 * @return array
 *   An array with all the elements from the ical.
 */
class DateiCalParse {

  /**
   * A regex string that will extract date parts from an ical date.
   */
  public static $regex_ical_date = '/(\d{4})(\d{2})(\d{2})/';

  /**
   * A regex string that will extract date and time parts from an ical datetime.
   */
  public static $regex_ical_datetime = '/(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?/';

  /**
   * A regex string that will extract date and time parts from either
   * a datetime string or an iso string, with or without missing date
   * and time values.
   */
  public static $regex_loose = '/(\d{4})-?(\d{1,2})-?(\d{1,2})([T\s]?(\d{2}):?(\d{2}):?(\d{2})?(\.\d+)?(Z|[\+\-]\d{2}:?\d{2})?)?/';

  /**
   * The start day of the week.
   */
  public static $week_start_day = 'MO';

  /**
   * The name of the timezone to use in these computations.
   */
  public static $timezone_name = 'UTC';

  public function import($filename) {
    // Fetch the iCal data. If file is a URL, use drupal_http_request. fopen
    // isn't always configured to allow network connections.
    if (substr($filename, 0, 4) == 'http') {
      // Fetch the ical data from the specified network location.
      $icaldatafetch = drupal_http_request($filename);
      // Check the return result.
      if ($icaldatafetch->error) {
        watchdog('date ical', 'HTTP Request Error importing %filename: @error', array('%filename' => $filename, '@error' => $icaldatafetch->error));
        return FALSE;
      }
      // Break the return result into one array entry per lines.
      $icaldatafolded = explode("\n", $icaldatafetch->data);
    }
    else {
      $icaldatafolded = @file($filename, FILE_IGNORE_NEW_LINES);
      if ($icaldatafolded === FALSE) {
        watchdog('date ical', 'Failed to open file: %filename', array('%filename' => $filename));
        return FALSE;
      }
    }
    // Verify this is iCal data.
    if (trim($icaldatafolded[0]) != 'BEGIN:VCALENDAR') {
      watchdog('date ical', 'Invalid calendar file: %filename', array('%filename' => $filename));
      return FALSE;
    }
    return $this->parse($icaldatafolded);
  }

  /**
   * Returns an array of iCalendar information from an iCalendar file.
   *
   * As date_ical_import() but different param.
   *
   * @param array $icaldatafolded
   *   An array of lines from an ical feed.
   *
   * @return array
   *   An array with all the elements from the ical.
   */
  public function parse($icaldatafolded = array()) {
    $items = array();

    // Verify this is iCal data.
    if (trim($icaldatafolded[0]) != 'BEGIN:VCALENDAR') {
      watchdog('date ical', 'Invalid calendar file.');
      return FALSE;
    }

    // "Unfold" wrapped lines.
    $icaldata = array();
    foreach ($icaldatafolded as $line) {
      $out = array();
      // See if this looks like the beginning of a new property or value. If not,
      // it is a continuation of the previous line. The regex is to ensure that
      // wrapped QUOTED-PRINTABLE data is kept intact.
      if (!preg_match('/([A-Z]+)[:;](.*)/', $line, $out)) {
        // Trim up to 1 leading space from wrapped line per iCalendar standard.
        $line = array_pop($icaldata) . (ltrim(substr($line, 0, 1)) . substr($line, 1));
      }
      $icaldata[] = $line;
    }
    unset($icaldatafolded);

    // Parse the iCal information.
    $parents = array();
    $subgroups = array();
    $vcal = '';
    foreach ($icaldata as $line) {
      $line = trim($line);
      $vcal .= $line . "\n";
      // Deal with begin/end tags separately.
      if (preg_match('/(BEGIN|END):V(\S+)/', $line, $matches)) {
        $closure = $matches[1];
        $type = 'V' . $matches[2];
        if ($closure == 'BEGIN') {
          array_push($parents, $type);
          array_push($subgroups, array());
        }
        elseif ($closure == 'END') {
          end($subgroups);
          $subgroup = &$subgroups[key($subgroups)];
          switch ($type) {
            case 'VCALENDAR':
              if (prev($subgroups) == FALSE) {
                $items[] = array_pop($subgroups);
              }
              else {
                $parent[array_pop($parents)][] = array_pop($subgroups);
              }
              break;
            // Add the timezones in with their index their TZID.
            case 'VTIMEZONE':
              $subgroup = end($subgroups);
              $id = $subgroup['TZID'];
              unset($subgroup['TZID']);

              // Append this subgroup onto the one above it.
              prev($subgroups);
              $parent = &$subgroups[key($subgroups)];

              $parent[$type][$id] = $subgroup;

              array_pop($subgroups);
              array_pop($parents);
              break;
            // Do some fun stuff with durations and all_day events and then append
            // to parent.
            case 'VEVENT':
            case 'VALARM':
            case 'VTODO':
            case 'VJOURNAL':
            case 'VVENUE':
            case 'VFREEBUSY':
            default:
              // Can't be sure whether DTSTART is before or after DURATION, so
              // parse DURATION at the end.
              if (isset($subgroup['DURATION'])) {
                self::parse_duration($subgroup, 'DURATION');
              }
              // Add a top-level indication for the 'All day' condition. Leave it
              // in the individual date components, too, so it is always available
              // even when you are working with only a portion of the VEVENT
              // array, like in Feed API parsers.
              $subgroup['all_day'] = FALSE;

              // iCal spec states 'The "DTEND" property for a "VEVENT" calendar
              // component specifies the non-inclusive end of the event'. Adjust
              // multi-day events to remove the extra day because the Date code
              // assumes the end date is inclusive.
              if (!empty($subgroup['DTEND']) && (!empty($subgroup['DTEND']['all_day']))) {
                // Make the end date one day earlier.
                $date = new DrupalDateTime ($subgroup['DTEND']['datetime'] . ' 00:00:00', $subgroup['DTEND']['tz']);
                date_modify($date, '-1 day');
                $subgroup['DTEND']['datetime'] = date_format($date,  'Y-m-d');
              }
              // If a start datetime is defined AND there is no definition for
              // the end datetime THEN make the end datetime equal the start
              // datetime and if it is an all day event define the entire event
              // as a single all day event.
              if (!empty($subgroup['DTSTART']) &&
                 (empty($subgroup['DTEND']) && empty($subgroup['RRULE']) && empty($subgroup['RRULE']['COUNT']))) {
                $subgroup['DTEND'] = $subgroup['DTSTART'];
              }
              // Add this element to the parent as an array under the component
              // name.
              if (!empty($subgroup['DTSTART']['all_day'])) {
                $subgroup['all_day'] = TRUE;
              }
              // Add this element to the parent as an array under the
              prev($subgroups);
              $parent = &$subgroups[key($subgroups)];

              $parent[$type][] = $subgroup;

              array_pop($subgroups);
              array_pop($parents);
              break;
          }
        }
      }
      // Handle all other possibilities.
      else {
        // Grab current subgroup.
        end($subgroups);
        $subgroup = &$subgroups[key($subgroups)];

        // Split up the line into nice pieces for PROPERTYNAME,
        // PROPERTYATTRIBUTES, and PROPERTYVALUE.
        preg_match('/([^;:]+)(?:;([^:]*))?:(.+)/', $line, $matches);
        $name = !empty($matches[1]) ? strtoupper(trim($matches[1])) : '';
        $field = !empty($matches[2]) ? $matches[2] : '';
        $data = !empty($matches[3]) ? $matches[3] : '';
        $parse_result = '';
        switch ($name) {
          // Keep blank lines out of the results.
          case '':
            break;

            // Lots of properties have date values that must be parsed out.
          case 'CREATED':
          case 'LAST-MODIFIED':
          case 'DTSTART':
          case 'DTEND':
          case 'DTSTAMP':
          case 'FREEBUSY':
          case 'DUE':
          case 'COMPLETED':
            $parse_result = self::parse_date($data, $field);
            break;

          case 'EXDATE':
          case 'RDATE':
            $parse_result = self::parse_exceptions($data, $field);
            break;

          case 'TRIGGER':
            // A TRIGGER can either be a date or in the form -PT1H.
            if (!empty($field)) {
              $parse_result = self::parse_date($data, $field);
            }
            else {
              $parse_result = array('DATA' => $data);
            }
            break;

          case 'DURATION':
            // Can't be sure whether DTSTART is before or after DURATION in
            // the VEVENT, so store the data and parse it at the end.
            $parse_result = array('DATA' => $data);
            break;

          case 'RRULE':
          case 'EXRULE':
            $parse_result = self::parse_rrule($data, $field);
            break;

          case 'STATUS':
          case 'SUMMARY':
          case 'DESCRIPTION':
            $parse_result = self::parse_text($data, $field);
            break;

          case 'LOCATION':
            $parse_result = self::parse_location($data, $field);
            break;

            // For all other properties, just store the property and the value.
            // This can be expanded on in the future if other properties should
            // be given special treatment.
          default:
            $parse_result = $data;
            break;
        }

        // Store the result of our parsing.
        $subgroup[$name] = $parse_result;
      }
    }
    return $items;
  }

  /**
   * Parses a ical date element.
   *
   * Possible formats to parse include:
   *   PROPERTY:YYYYMMDD[T][HH][MM][SS][Z]
   *   PROPERTY;VALUE=DATE:YYYYMMDD[T][HH][MM][SS][Z]
   *   PROPERTY;VALUE=DATE-TIME:YYYYMMDD[T][HH][MM][SS][Z]
   *   PROPERTY;TZID=XXXXXXXX;VALUE=DATE:YYYYMMDD[T][HH][MM][SS]
   *   PROPERTY;TZID=XXXXXXXX:YYYYMMDD[T][HH][MM][SS]
   *
   *   The property and the colon before the date are removed in the import
   *   process above and we are left with $field and $data.
   *
   * @param string $field
   *   The text before the colon and the date, i.e.
   *   ';VALUE=DATE:', ';VALUE=DATE-TIME:', ';TZID='
   * @param string $data
   *   The date itself, after the colon, in the format YYYYMMDD[T][HH][MM][SS][Z]
   *   'Z', if supplied, means the date is in UTC.
   *
   * @return array
   *   $items array, consisting of:
   *      'datetime'   => date in YYYY-MM-DD HH:MM format, not timezone adjusted
   *      'all_day'    => whether this is an all-day event with no time
   *      'tz'         => the timezone of the date, could be blank if the ical
   *                      has no timezone; the ical specs say no timezone
   *                      conversion should be done if no timezone info is
   *                      supplied
   *  @todo
   *   Another option for dates is the format PROPERTY;VALUE=PERIOD:XXXX. The
   *   period may include a duration, or a date and a duration, or two dates, so
   *   would have to be split into parts and run through date_ical_parse_date()
   *   and date_ical_parse_duration(). This is not commonly used, so ignored for
   *   now. It will take more work to figure how to support that.
   */
  public static function parse_date($data, $field = 'DATE:') {

    $items = array('datetime' => '', 'all_day' => '', 'tz' => '');
    if (empty($data)) {
      return $items;
    }
    // Make this a little more whitespace independent.
    $data = trim($data);

    // Turn the properties into a nice indexed array of
    // array(PROPERTYNAME => PROPERTYVALUE);
    $field_parts = preg_split('/[;:]/', $field);
    $properties = array();
    foreach ($field_parts as $part) {
      if (strpos($part, '=') !== FALSE) {
        $tmp = explode('=', $part);
        $properties[$tmp[0]] = $tmp[1];
      }
    }

    // Make this a little more whitespace independent.
    $data = trim($data);

    // Record if a time has been found.
    $has_time = FALSE;

    // If a format is specified, parse it according to that format.
    if (isset($properties['VALUE'])) {
      switch ($properties['VALUE']) {
        case 'DATE':
          preg_match(self::$regex_ical_date, $data, $regs);
          // Date.
          $datetime = DrupalDateTime::datePad($regs[1]) . '-' . DrupalDateTime::datePad($regs[2]) . '-' . DrupalDateTime::datePad($regs[3]);
          break;
        case 'DATE-TIME':
          preg_match(self::$regex_ical_datetime, $data, $regs);
          // Date.
          $datetime = DrupalDateTime::datePad($regs[1]) . '-' . DrupalDateTime::datePad($regs[2]) . '-' . DrupalDateTime::datePad($regs[3]);
          // Time.
          $datetime .= ' ' . DrupalDateTime::datePad($regs[4]) . ':' . DrupalDateTime::datePad($regs[5]) . ':' . DrupalDateTime::datePad($regs[6]);
          $has_time = TRUE;
          break;
      }
    }
    // If no format is specified, attempt a loose match.
    else {
      preg_match(self::$regex_loose, $data, $regs);
      if (!empty($regs) && count($regs) > 2) {
        // Date.
        $datetime = DrupalDateTime::datePad($regs[1]) . '-' . DrupalDateTime::datePad($regs[2]) . '-' . DrupalDateTime::datePad($regs[3]);
        if (isset($regs[4])) {
          $has_time = TRUE;
          // Time.
          $datetime .= ' ' . (!empty($regs[5]) ? DrupalDateTime::datePad($regs[5]) : '00') .
           ':' . (!empty($regs[6]) ? DrupalDateTime::datePad($regs[6]) : '00') .
           ':' . (!empty($regs[7]) ? DrupalDateTime::datePad($regs[7]) : '00');
        }
      }
    }

    // Use timezone if explicitly declared.
    if (isset($properties['TZID'])) {
      $tz = $properties['TZID'];
      // Fix alternatives like US-Eastern which should be US/Eastern.
      $tz = str_replace('-', '/', $tz);
      // Unset invalid timezone names.
      module_load_include('inc', 'date_api', 'date_api.admin');
      $tz = _date_timezone_replacement($tz);
      if (!in_array($tz, array_keys(system_time_zones()))) {
        $tz = '';
      }
    }
    // If declared as UTC with terminating 'Z', use that timezone.
    elseif (strpos($data, 'Z') !== FALSE) {
      $tz = 'UTC';
    }
    // Otherwise this date is floating.
    else {
      $tz = '';
    }

    $items['datetime'] = $datetime;
    $items['all_day'] = $has_time ? FALSE : TRUE;
    $items['tz'] = $tz;
    return $items;
  }

  /**
   * Parse exception dates (can be multiple values).
   *
   * @return array
   *   an array of date value arrays.
   */
  public static function parse_exceptions($data, $field = 'EXDATE:') {
    $data = str_replace($field . ':', '', $data);
    $items = array('DATA' => $data);
    $ex_dates = explode(',', $data);
    foreach ($ex_dates as $ex_date) {
      $items[] = self::parse_date($ex_date);
    }
    return $items;
  }

  /**
   * Parses the duration of the event.
   *
   * Example:
   *  DURATION:PT1H30M
   *  DURATION:P1Y2M
   *
   * @param array $subgroup
   *   Array of other values in the vevent so we can check for DTSTART.
   */
  public static function parse_duration(&$subgroup, $field = 'DURATION') {
    $items = $subgroup[$field];
    $data  = $items['DATA'];
    $interval = new \DateInterval($data);
    $start_date = array_key_exists('DTSTART', $subgroup) ? $subgroup['DTSTART']['datetime'] : date_format(new DrupalDateTime(), DATE_FORMAT_ISO);
    $timezone = array_key_exists('DTSTART', $subgroup) ? $subgroup['DTSTART']['tz'] : $this->timezone_name;
    if (empty($timezone)) {
      $timezone = 'UTC';
    }
    $date = new DrupalDateTime($start_date, $timezone);
    $date2 = clone($date);
    $date2->add($interval);
    $format = isset($subgroup['DTSTART']['type']) && $subgroup['DTSTART']['type'] == 'DATE' ? 'Y-m-d' : 'Y-m-d H:i:s';
    $subgroup['DTEND'] = array(
      'datetime' => date_format($date2, DATE_FORMAT_DATETIME),
      'all_day' => isset($subgroup['DTSTART']['all_day']) ? $subgroup['DTSTART']['all_day'] : 0,
      'tz' => $timezone,
      );
    $duration = date_format($date2, 'U') - date_format($date, 'U');
    $subgroup['DURATION'] = array('DATA' => $data, 'DURATION' => $duration);
  }

  /**
   * Parse and clean up ical text elements.
   */
  public static function parse_text($data, $field = '') {
    if (strstr($field, 'QUOTED-PRINTABLE')) {
      $data = quoted_printable_decode($data);
    }
    // Strip line breaks within element.
    $data = str_replace(array("\r\n ", "\n ", "\r "), '', $data);
    // Put in line breaks where encoded.
    $data = str_replace(array("\\n", "\\N"), "\n", $data);
    // Remove other escaping.
    $data = stripslashes($data);
    return $data;
  }

  /**
   * Parse location elements.
   *
   * Catch situations like the upcoming.org feed that uses
   * LOCATION;VENUE-UID="http://upcoming.yahoo.com/venue/104/":First Street..
   * or more normal LOCATION;UID=123:111 First Street...
   * Upcoming feed would have been improperly broken on the ':' in http://
   * so we paste the $field and $data back together first.
   *
   * Use non-greedy check for ':' in case there are more of them in the address.
   */
  public static function parse_location($data, $field = 'LOCATION:') {
    if (preg_match('/UID=[?"](.+)[?"][*?:](.+)/', $field . ':' . $data, $matches)) {
      $location = array();
      $location['UID'] = $matches[1];
      $location['DESCRIPTION'] = stripslashes($matches[2]);
      return $location;
    }
    else {
      // Remove other escaping.
      $location = stripslashes($data);
      return $location;
    }
  }

  /**
   * Return a date object for the ical date, adjusted to its local timezone.
   *
   * @param array $ical_date
   *   An array of ical date information created in the ical import.
   * @param string $to_tz
   *   The timezone to convert the date's value to.
   *
   * @return object
   *   A timezone-adjusted date object.
   */
  public static function ical_date($ical_date, $to_tz = FALSE) {

    // If the ical date has no timezone, must assume it is stateless
    // so treat it as a local date.
    if (empty($ical_date['datetime'])) {
      return NULL;
    }
    elseif (empty($ical_date['tz'])) {
      $from_tz = drupal_get_user_timezone();
    }
    else {
      $from_tz = $ical_date['tz'];
    }
    if (strlen($ical_date['datetime']) < 11) {
      $ical_date['datetime'] .= ' 00:00:00';
    }
    $date = new DrupalDateTime($ical_date['datetime'], new \DateTimeZone($from_tz));

    if ($to_tz && $ical_date['tz'] != '' && $to_tz != $ical_date['tz']) {
      date_timezone_set($date, timezone_open($to_tz));
    }
    return $date;
  }

  /**
   * Escape #text elements for safe iCal use.
   *
   * @param string $text
   *   Text to escape
   *
   * @return string
   *   Escaped text
   *
   */
  public static function escape_text($text) {
    $text = drupal_html_to_text($text);
    $text = trim($text);
    // TODO Per #38130 the iCal specs don't want : and " escaped
    // but there was some reason for adding this in. Need to watch
    // this and see if anything breaks.
    // $text = str_replace('"', '\"', $text);
    // $text = str_replace(":", "\:", $text);
    $text = preg_replace("/\\\b/", "\\\\", $text);
    $text = str_replace(",", "\,", $text);
    $text = str_replace(";", "\;", $text);
    $text = str_replace("\n", "\\n ", $text);
    return trim($text);
  }

  /**
   * Explode a multiline entry into its lines.
   */
  public static function split_multiline_entry($entry) {
    return explode("\n", str_replace("\r\n", "\n", $entry));
  }

  /**
   * Extract the RRULE from a multiline entry.
   */
  public static function extract_rrule_string($rrule) {
  $parts = self::split_multiline_entry($rrule);
    foreach($parts as $part) {
      if (strstr($part, 'RRULE')) {
        $rrule = $part;
      }
    }
    return $rrule;
  }

  /**
   * Parse an iCal rule into a parsed RRULE array, along with EXDATE
   * and RDATE arrays.
   */
  public static function split_rrule($rrule) {
    $parts = self::split_multiline_entry($rrule);
    $rrule = array();
    $exceptions = array();
    $additions = array();
    $additions = array();
    foreach ($parts as $part) {
      if (strstr($part, 'RRULE')) {
        $RRULE = str_replace('RRULE:', '', $part);
        $rrule = (array) self::parse_rrule($RRULE);
      }
      elseif (strstr($part, 'EXDATE')) {
        $EXDATE = str_replace('EXDATE:', '', $part);
        $exceptions = (array) DateiCalParse::parse_exceptions($EXDATE);
        unset($exceptions['DATA']);
      }
      elseif (strstr($part, 'RDATE')) {
        $RDATE = str_replace('RDATE:', '', $part);
        $additions = (array) DateiCalParse::parse_exceptions($RDATE);
        unset($additions['DATA']);
      }
    }
    return array($rrule, $exceptions, $additions);
  }

  /**
   * Parse an ical repeat rule.
   *
   * @return array
   *   Array in the form of PROPERTY => array(VALUES)
   *   PROPERTIES are FREQ, INTERVAL, COUNT, BYDAY, BYMONTH, BYYEAR, UNTIL
   */
  public static function parse_rrule($data, $field = 'RRULE:') {
    $data = preg_replace("/RRULE.*:/", '', $data);
    $items = array('DATA' => $data);
    $rrule = explode(';', $data);
    foreach ($rrule as $key => $value) {
      $param = explode('=', $value);
      // Must be some kind of invalid data.
      if (count($param) != 2) {
        continue;
      }
      if ($param[0] == 'UNTIL') {
        $values = self::parse_date($param[1]);
      }
      else {
        $values = explode(',', $param[1]);
      }
      // Treat items differently if they have multiple or single values.
      if (in_array($param[0], array('FREQ', 'INTERVAL', 'COUNT', 'WKST'))) {
        $items[$param[0]] = $param[1];
      }
      else {
        $items[$param[0]] = $values;
      }
    }
    return $items;
  }

  /**
   * The reverse of the parse_rrule() function.
   *
   * Build a string RRULE from the array structure created by parsing
   * a RRule.
   *
   * @param array $ical_array
   *   An array constructed like the one created by parse_rrule().
   *     [RRULE] => Array (
   *       [FREQ] => Array (
   *         [0] => MONTHLY
   *       )
   *       [BYDAY] => Array (
   *         [0] => 1SU
   *         [1] => -1SU
   *       )
   *       [UNTIL] => Array (
   *         [datetime] => 1997-21-31 09:00:00
   *         [all_day] => 0
   *         [tz] => US/Eastern
   *       )
   *     )
   *     [EXDATE] => Array (
   *       [0] = Array (
   *         [datetime] => 1997-09-21 09:00:00
   *         [all_day] => 0
   *         [tz] => US/Eastern
   *       )
   *       [1] = Array (
   *         [datetime] => 1997-10-05 09:00:00
   *         [all_day] => 0
   *         [tz] => US/Eastern
   *       )
   *     )
   *     [RDATE] => Array (
   *       [0] = Array (
   *         [datetime] => 1997-09-21 09:00:00
   *         [all_day] => 0
   *         [tz] => US/Eastern
   *       )
   *       [1] = Array (
   *         [datetime] => 1997-10-05 09:00:00
   *         [all_day] => 0
   *         [tz] => US/Eastern
   *       )
   *     )
   */
  public static function build_rrule($ical_array) {
    $RRULE = '';
    if (empty($ical_array) || !is_array($ical_array)) {
      return $RRULE;
    }

    // Grab the RRULE data and put them into iCal RRULE format.
    $RRULE .= 'RRULE:FREQ=' . (!array_key_exists('FREQ', $ical_array) ? 'DAILY' : $ical_array['FREQ']);
    $RRULE .= ';INTERVAL=' . (!array_key_exists('INTERVAL', $ical_array) ? 1 : $ical_array['INTERVAL']);

    // Unset the empty 'All' values.
    if (array_key_exists('BYDAY', $ical_array) && is_array($ical_array['BYDAY'])) {
      unset($ical_array['BYDAY']['']);
    }
    if (array_key_exists('BYMONTH', $ical_array) && is_array($ical_array['BYMONTH'])) {
      unset($ical_array['BYMONTH']['']);
    }
    if (array_key_exists('BYMONTHDAY', $ical_array) && is_array($ical_array['BYMONTHDAY'])) {
      unset($ical_array['BYMONTHDAY']['']);
    }

    if (array_key_exists('BYDAY', $ical_array) && is_array($ical_array['BYDAY']) && $BYDAY = implode(",", $ical_array['BYDAY'])) {
      $RRULE .= ';BYDAY=' . $BYDAY;
    }
    if (array_key_exists('BYMONTH', $ical_array) && is_array($ical_array['BYMONTH']) && $BYMONTH = implode(",", $ical_array['BYMONTH'])) {
      $RRULE .= ';BYMONTH=' . $BYMONTH;
    }
    if (array_key_exists('BYMONTHDAY', $ical_array) && is_array($ical_array['BYMONTHDAY']) && $BYMONTHDAY = implode(",", $ical_array['BYMONTHDAY'])) {
      $RRULE .= ';BYMONTHDAY=' . $BYMONTHDAY;
    }
    // The UNTIL date is supposed to always be expressed in UTC.
    // The input date values may already have been converted to a
    // date object on a previous pass, so check for that.
    if (array_key_exists('UNTIL', $ical_array) && array_key_exists('datetime', $ical_array['UNTIL']) && !empty($ical_array['UNTIL']['datetime'])) {
      // We only collect a date for UNTIL, but we need it to be
      // inclusive, so force it to a full datetime element at the
      // last second of the day.
      if (!$ical_array['UNTIL']['datetime'] instanceOf DrupalDateTime) {
        // If this is a date without time, give it time.
        if (strlen($ical_array['UNTIL']['datetime']) < 11) {
          $ical_array['UNTIL']['datetime'] .= ' 23:59:59';
          $ical_array['UNTIL']['granularity'] = serialize(drupal_map_assoc(array('year', 'month', 'day', 'hour', 'minute', 'second')));
          $ical_array['UNTIL']['all_day'] = FALSE;
        }
        $until = self::ical_date($ical_array['UNTIL'], 'UTC');
      }
      else {
        $until = $ical_array['UNTIL']['datetime'];
      }
      $RRULE .= ';UNTIL=' . date_format($until, DATE_FORMAT_ICAL) . 'Z';
    }
    // Our form doesn't allow a value for COUNT, but it may be needed by
    // modules using the API, so add it to the rule.
    if (array_key_exists('COUNT', $ical_array)) {
      $RRULE .= ';COUNT=' . $ical_array['COUNT'];
    }

    // iCal rules presume the week starts on Monday unless otherwise
    // specified, so we'll specify it.
    if (array_key_exists('WKST', $ical_array)) {
      $RRULE .= ';WKST=' . $ical_array['WKST'];
    }
    else {
      $RRULE .= ';WKST=' . self::$week_start_day;
    }

    // Exceptions dates go last, on their own line.
    // The input date values may already have been converted to a date
    // object on a previous pass, so check for that.
    if (isset($ical_array['EXDATE']) && is_array($ical_array['EXDATE'])) {
      $ex_dates = array();
      foreach ($ical_array['EXDATE'] as $value) {
        if (!empty($value['datetime'])) {
          $date = !$value['datetime'] instanceOf DrupalDateTime ? self::ical_date($value, 'UTC') : $value['datetime'];
          $ex_date = !empty($date) ? date_format($date, DATE_FORMAT_ICAL) . 'Z': '';
          if (!empty($ex_date)) {
            $ex_dates[] = $ex_date;
          }
        }
      }
      if (!empty($ex_dates)) {
        sort($ex_dates);
        $RRULE .= chr(13) . chr(10) . 'EXDATE:' . implode(',', $ex_dates);
      }
    }
    elseif (!empty($ical_array['EXDATE'])) {
      $RRULE .= chr(13) . chr(10) . 'EXDATE:' . $ical_array['EXDATE'];
    }

    // Exceptions dates go last, on their own line.
    if (isset($ical_array['RDATE']) && is_array($ical_array['RDATE'])) {
      $ex_dates = array();
      foreach ($ical_array['RDATE'] as $value) {
        $date = !$value['datetime'] instanceOf DrupalDateTime ? self::ical_date($value, 'UTC') : $value['datetime'];
        $ex_date = !empty($date) ? date_format($date, DATE_FORMAT_ICAL) . 'Z': '';
        if (!empty($ex_date)) {
          $ex_dates[] = $ex_date;
        }
      }
      if (!empty($ex_dates)) {
        sort($ex_dates);
        $RRULE .= chr(13) . chr(10) . 'RDATE:' . implode(',', $ex_dates);
      }
    }
    elseif (!empty($ical_array['RDATE'])) {
      $RRULE .= chr(13) . chr(10) . 'RDATE:' . $ical_array['RDATE'];
    }

    return $RRULE;
  }
}