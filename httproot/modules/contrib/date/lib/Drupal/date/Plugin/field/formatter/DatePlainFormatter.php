<?php

/**
 * @file
 * Definition of Drupal\date\Plugin\field\formatter\DatePlainFormatter.
 */

namespace Drupal\date\Plugin\field\formatter;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Formatter\FormatterBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'date_plain' formatter.
 *
 * @Plugin(
 *   id = "date_plain",
 *   module = "date",
 *   label = @Translation("Plain"),
 *   field_types = {
 *     "date"
 *   }
 * )
 */
class DatePlainFormatter extends FormatterBase {

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
        if (empty($item['value2']) || $item['value'] == $item['value2']) {
          $elements[$delta] = array('#markup' => $item['value']);
        }
        else {
          $elements[$delta] = array('#markup' => t('!start-date to !end-date', array('!start-date' => $item['value'], '!end-date' => $item['value2'])));
        }
      }
    }

    return $elements;
  }

}
