<?php
/**
 * @file
 * Definition of Drupal\date\Plugin\field\widget\DateWidgetBase.
 */

namespace Drupal\date\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Plugin\PluginSettingsBase;
use Drupal\field\FieldInstance;
use Drupal\date_api\DateGranularity;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Abstract class for all date widgets.
 */
abstract class DateWidgetBase extends WidgetBase {

  /**
   * Constructs a DateWidget object.
   *
   * @param array $plugin_id
   *   The plugin_id for the widget.
   * @param Drupal\Component\Plugin\Discovery\DiscoveryInterface $discovery
   *   The Discovery class that holds access to the widget implementation
   *   definition.
   * @param Drupal\field\FieldInstance $instance
   *   The field instance to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param int $weight
   *   The widget weight.
   */
  public function __construct($plugin_id, DiscoveryInterface $discovery, FieldInstance $instance, array $settings, $weight) {
    // Identify the function used to set the default value.
    $instance['default_value_function'] = $this->defaultValueFunction();
    parent::__construct($plugin_id, $discovery, $instance, $settings, $weight);
  }

  /**
   * Return the callback used to set a date default value.
   */
  public function defaultValueFunction() {
    return 'date_default_value';
  }

  /**
   * Return the format options used by this widget.
   */
  public function formatOptions() {
    $options = array();
    $formats = date_datepicker_formats();
    $example_date = date_example_date();
    foreach ($formats as $f) {
      $options[$f] = $example_date->format($f);
    }
    return $options;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::settingsForm().
   */
  public function settingsForm(array $form, array &$form_state) {
    $field = $this->field;
    $instance = $this->instance;
    $widget = $instance['widget'];
    $settings = $widget['settings'];

    if (empty($settings['date_date_format'])) {
      $settings['date_date_format'] = variable_get('date_format_html_date', 'Y-m-d') . ' ' . variable_get('date_format_html_time', 'H:i:s');
    }
    
    $element = array('#element_validate' => array('date_field_widget_settings_form_validate'));
    
    $element['year_range'] = array(
      '#type' => 'date_year_range',
      '#default_value' => $settings['year_range'],
      '#fieldset' => 'date_format',
      '#weight' => 6,
    );
    $element['increment'] = array(
      '#type' => 'select', '#title' => t('Time increments'),
      '#default_value' => $settings['increment'],
      '#options' => array(
        1 => t('1 minute'),
        5 => t('5 minute'),
        10 => t('10 minute'),
        15 => t('15 minute'),
        30 => t('30 minute')),
      '#weight' => 7,
      '#fieldset' => 'date_format',
    );
        
    $context = array(
      'field' => $field,
      'instance' => $instance,
    );
    drupal_alter('date_field_widget_settings_form', $element, $context);
    
    return $element;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::formElement().
   *
   * The widget builds out a complex date element in the following way:
   *
   * - A field is pulled out of the database which is comprised of one or
   *   more collections of start/end dates.
   *
   * - The dates in this field are all converted from the UTC values stored
   *   in the database back to the local time. This is done in #process
   *   to avoid making this change to dates that are not being processed,
   *   like those hidden with #access.
   *
   * - If values are empty, the field settings rules are used to determine
   *   if the default_values should be empty, now, the same, or use strtotime.
   *
   * - Each start/end combination is created using the date_combo element type
   *   defined by the date module. If the timezone is date-specific, a
   *   timezone selector is added to the first combo element.
   *
   * - The date combo element creates two individual date elements, one each
   *   for the start and end field, using the appropriate individual Date API
   *   date elements, like selects, textfields, or popups.
   *
   * - In the individual element validation, the data supplied by the user is
   *   used to update the individual date values.
   *
   * - In the combo date validation, the timezone is updated, if necessary,
   *   then the user input date values are used with that timezone to create
   *   date objects, which are used update combo date timezone and offset values.
   *
   * - In the field's submission processing, the new date values, which are in
   *   the local timezone, are converted back to their UTC values and stored.
   *
   */
  public function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {

    $field = $this->field;
    $instance = $this->instance;
    $field_name = $field['field_name'];
    $entity_type = $instance['entity_type'];
  
  
    // @TODO Repeating dates should probably be made into their own field type and completely separated out.
    // That will have to wait for a new branch since it may break other things, including other modules
    // that have an expectation of what the date field types are.
  
    // Since repeating dates cannot use the default Add more button, we have to handle our own behaviors here.
    // Return only the first multiple value for repeating dates, then clean up the 'Add more' bits in #after_build.
    // The repeating values will be re-generated when the repeat widget form is validated.
    // At this point we can't tell if this form element is going to be hidden by #access, and we're going to
    // lose all but the first value by doing this, so store the original values in case we need to replace them later.
    if (!empty($field['settings']['repeat'])) {
      if ($delta == 0) {
        $form['#after_build'] = array('date_repeat_after_build');
        $form_state['storage']['repeat_fields'][$field_name] = array_merge($form['#parents'], array($field_name));
        $form_state['storage']['date_items'][$field_name][$langcode] = $items;
      }
      else {
        return;
      }
    }
  
    module_load_include('inc', 'date_api', 'date_api_elements');
    $timezone = date_get_timezone($field['settings']['tz_handling'], isset($items[0]['timezone']) ? $items[0]['timezone'] : drupal_get_user_timezone());
  
    // TODO see if there's a way to keep the timezone element from ever being
    // nested as array('timezone' => 'timezone' => value)). After struggling
    // with this a while, I can find no way to get it displayed in the form
    // correctly and get it to use the timezone element without ending up
    // with nesting.
    if (is_array($timezone)) {
      $timezone = $timezone['timezone'];
    }
  
    $element += array(
      '#weight' => $delta,
      '#default_value' => isset($items[$delta]) ? $items[$delta] : '',
      '#date_timezone' => $timezone,
      '#element_validate' => array('date_combo_validate'),
      '#date_is_default' => $is_default,
      '#date_increment' => $this->getSetting('increment'),
      '#date_year_range' => $this->getSetting('year_range'),
      '#required' => $element['#required'],
  
      // Store the original values, for use with disabled and hidden fields.
      '#date_items' => isset($items[$delta]) ? $items[$delta] : '',
    );
  
    $element['#title'] = $instance['label'];
  
    if ($field['settings']['tz_handling'] == 'date') {
      $element['timezone'] = array(
        '#type' => 'date_timezone',
        '#theme_wrappers' => array('date_timezone'),
        '#delta' => $delta,
        '#default_value' => $timezone,
        '#weight' => $instance['widget']['weight'] + 1,
        '#attributes' => array('class' => array('date-no-float')),
        '#date_label_position' => $instance['widget']['settings']['label_position'],
        );
    }

    return $element;
  }

  /**
   * Implements Drupal\field\Plugin\Type\Widget\WidgetInterface::errorElement().
   */
  //public function errorElement(array $element, array $error, array $form, array &$form_state) {
  //  return $element['value'];
  //}

}
