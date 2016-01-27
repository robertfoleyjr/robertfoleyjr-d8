<?php

/**
 * @file
 * Definition of Drupal\date\Plugin\field\formatter\DateDefaultFormatter.
 */

namespace Drupal\date\Plugin\field\formatter;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'date_default' formatter.
 *
 * @Plugin(
 *   id = "date_default",
 *   module = "adate",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "format_type" = "long",
 *     "multiple_number" = "",
 *     "multiple_from" = "",
 *     "multiple_to" = "",
 *     "fromto" = "both"
 *    }
 * )
 */
class DateDefaultFormatter extends FormatterBase {

  /**
   * Implements Drupal\field\Plugin\Type\Formatter\FormatterInterface::viewElements().
   *
   *
   * Useful values:
   *
   *   $entity->date_id
   *     If set, this will show only an individual date on a field with
   *     multiple dates. The value should be a string that contains
   *     the following values, separated with periods:
   *     - module name of the module adding the item
   *     - node nid
   *     - field name
   *     - delta value of the field to be displayed
   *     - other information the module's custom theme might need
   *
   *     Used by the calendar module and available for other uses.
   *     example: 'date:217:field_date:3:test'
   *
   *   $entity->date_repeat_show
   *     If true, tells the theme to show all the computed values
   *     of a repeating date. If not true or not set, only the
   *     start date and the repeat rule will be displayed.
   */
  public function viewElements(EntityInterface $entity, $langcode, array $items) {

    $field = $this->field;
    $instance = $this->instance;
    $settings = $this->settings;
    $view_mode = $this->viewMode;
    $weight = $this->weight;
    $label = $this->label;
    $definition = $this->getDefinition();
    $formatter = $definition['id'];

    $elements = array();

    $variables = array(
      'entity' => $entity,
      'field' => $field,
      'instance' => $instance,
      'langcode' => $langcode,
      'items' => $items,
      'dates' => array(),
      'attributes' => array(),
      'rdf_mapping' => array(),
      'add_rdf' => module_exists('rdf'),
    );

    // If there is an RDf mapping for this date field, pass it down to the theme.
    $rdf_mapping = array();
    if (!empty($entity->rdf_mapping) && function_exists('rdf_rdfa_attributes')) {
      if (!empty($entity->rdf_mapping[$field['field_name']])) {
        $variables['rdf_mapping'] = $rdf_mapping = $entity->rdf_mapping[$field['field_name']];
      }
    }

    // Give other modules a chance to prepare the entity before formatting it.
    drupal_alter('date_formatter_pre_view', $entity, $variables);

    // See if we are only supposed to display a selected
    // item from multiple value date fields.
    $selected_deltas = array();
    if (!empty($entity->date_id)) {
      foreach ((array) $entity->date_id as $key => $id) {
        list($module, $nid, $field_name, $selected_delta, $other) = explode('.', $id . '.');
        if ($field_name == $field['field_name']) {
          $selected_deltas[] = $selected_delta;
        }
      }
    }

    foreach ($items as $delta => $item) {
      if (!empty($entity->date_id) && !in_array($delta, $selected_deltas)) {
        continue;
      }
      else {
        $variables['delta'] = $delta;
        $variables['item'] = $item;
        $variables['dates'] = date_formatter_process($formatter, $entity, $field, $instance, $langcode, $item, $settings);
        $variables['attributes'] = !empty($rdf_mapping) ? rdf_rdfa_attributes($rdf_mapping, $item['value']) : array();
        $output = theme('date_display_combination', $variables);
        if (!empty($output)) {
          $elements[$delta] = array('#markup' => $output);
        }
      }
    }

    return $elements;

  }

  /**
   * Implements Drupal\field\Plugin\Type\Formatter\FormatterInterface::settingsForm().
   */
  public function settingsForm(array $form, array &$form_state) {

    $field = $this->field;
    $instance = $this->instance;
    $settings = $this->settings;
    $view_mode = $this->viewMode;
    $weight = $this->weight;
    $label = $this->label;
    $definition = $this->getDefinition();
    $formatter = $definition['id'];

    $element = array();

    $element['format_type'] = array(
      '#title' => t('Choose how users view dates and times:'),
      '#type' => 'select',
      '#options' => date_format_type_options(),
      '#default_value' => $settings['format_type'],
      '#description' => t('To add or edit options, visit <a href="@date-time-page">Date and time settings</a>.', array('@date-time-page' => url('admin/config/regional/date-time'))),
      '#weight' => 0,
    );

    $element['fromto'] = array(
      '#title' => t('Display:'),
      '#type' => 'select',
      '#options' => array(
        'both' => t('Both Start and End dates'),
        'value' => t('Start date only'),
        'value2' => t('End date only'),
      ),
      '#access' => $field['settings']['todate'],
      '#default_value' => $settings['fromto'],
      '#weight' => 1,
    );

    // Make the string translatable by keeping it as a whole rather than
    // translating prefix and suffix separately.
    list($prefix, $suffix) = explode('@count', t('Show @count value(s)'));
    $element['multiple_number'] = array(
      '#type' => 'textfield',
      '#title' => t('Multiple values:'),
      '#size' => 5,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $settings['multiple_number'],
      '#weight' => 2,
      '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
      '#description' => t('Identify a specific number of values to display, or leave blank to show all values.'),
    );

    list($prefix, $suffix) = explode('@isodate', t('starting from @isodate'));
    $element['multiple_from'] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $settings['multiple_from'],
      '#weight' => 3,
      '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
    );

    list($prefix, $suffix) = explode('@isodate', t('ending with @isodate'));
    $element['multiple_to'] = array(
      '#type' => 'textfield',
      '#size' => 15,
      '#field_prefix' => $prefix,
      '#field_suffix' => $suffix,
      '#default_value' => $settings['multiple_to'],
      '#weight' => 4,
      '#access' => ($field['cardinality'] == FIELD_CARDINALITY_UNLIMITED) || ($field['cardinality'] > 1),
      '#description' => t('Identify specific start and/or end dates in the format YYYY-MM-DDTHH:MM:SS, or leave blank for all available dates.'),
    );

    $context = array(
      'field' => $field,
      'instance' => $instance,
      'view_mode' => $view_mode,
      'formatter' => $formatter,
      'settings' => $settings,
    );
    drupal_alter('date_field_formatter_settings_form', $element, $form_state, $context);

    return $element;
  }

  /**
   * Settings summary for the default formatter.
   */
  public function settingsSummary() {

    $field = $this->field;
    $instance = $this->instance;
    $settings = $this->settings;
    $view_mode = $this->viewMode;
    $weight = $this->weight;
    $label = $this->label;
    $definition = $this->getDefinition();
    $formatter = $definition['id'];

    $format_types = date_format_type_options();
    $summary = array();
    $format = FALSE;
    $format = t('Plain');
    if (!empty($format_types[$settings['format_type']])) {
      $format = $format_types[$settings['format_type']];
    }
    if ($format) {
      $summary[] = t('Display dates using the @format format', array('@format' => $format));
    }
    else {
      $summary[] = t('Display dates using the default format because the specified format (@format) is not defined', array('@format' => $settings['format_type']));
    }

    if (array_key_exists('fromto', $settings) && $field['settings']['todate']) {
      $options = array(
        'both' => t('Display both Start and End dates'),
        'value' => t('Display Start date only'),
        'value2' => t('Display End date only'),
      );
      $summary[] = $options[$settings['fromto']];
    }

    if (array_key_exists('multiple_number', $settings) && !empty($field['cardinality'])) {
      $summary[] = t('Show @count value(s) starting with @date1, ending with @date2', array(
        '@count' => !empty($settings['multiple_number']) ? $settings['multiple_number'] : t('all'),
        '@date1' => !empty($settings['multiple_from']) ? $settings['multiple_from'] : t('earliest'),
        '@date2' => !empty($settings['multiple_to']) ? $settings['multiple_to'] : t('latest'),
      ));
    }

    $context = array(
      'field' => $field,
      'instance' => $instance,
      'view_mode' => $view_mode,
      'formatter' => $formatter,
      'settings' => $settings,
    );
    drupal_alter('date_field_formatter_settings_summary', $summary, $context);

    return implode('<br />', $summary);
  }


}
