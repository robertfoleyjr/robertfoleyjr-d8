<?php

/**
 * @file
 *
 * Definition of Drupal\date_field\Plugin\field\formatter\DateFieldFormatIntervalFormatter.
 */
namespace Drupal\date_field\Plugin\field\formatter;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'format_interval'' formatter.
 *
 * @see Drupal\date\Field\Formatter\FormatIntervalFormatter
 *
 * @Plugin(
 *   id = "date_field_format_interval",
 *   module = "date",
 *   label = @Translation("Time Ago"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "interval" = "2",
 *     "interval_display" = "time ago"
 *   }
 * )
 */
class DateFieldFormatIntervalFormatter extends FormatterBase {

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
        $elements[$delta] = array('#markup' => theme('date_display_interval', $variables));
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
    $element['interval'] = array(
      '#title' => t('Interval'),
      '#description' => t("How many time units should be shown in the 'time ago' string."),
      '#type' => 'select',
      '#options' => drupal_map_assoc(range(1, 6)),
      '#default_value' => $settings['interval'],
      '#weight' => 0,
    );
    // Uses the same options used by Views format_interval.
    $options = array(
      'raw time ago' => t('Time ago'),
      'time ago' => t('Time ago (with "ago" appended)'),
      'raw time hence' => t('Time hence'),
      'time hence' => t('Time hence (with "hence" appended)'),
      'raw time span' => t('Time span (future dates have "-" prepended)'),
      'inverse time span' => t('Time span (past dates have "-" prepended)'),
      'time span' => t('Time span (with "ago/hence" appended)'),
    );
    $element['interval_display'] = array(
      '#title' => t('Display'),
      '#description' => t("How to display the time ago or time hence for this field."),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $settings['interval_display'],
      '#weight' => 0,
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
   * Implements Drupal\field\Plugin\Type\Formatter\FormatterInterface::settingsSummary().
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

    $summary = array();
    $summary[] = t('Display time ago, showing @interval units.', array('@interval' => $settings['interval']));

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
