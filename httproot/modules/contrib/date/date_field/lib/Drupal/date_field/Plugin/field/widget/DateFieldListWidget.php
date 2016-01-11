<?php
/**
 * @file
 * Definition of Drupal\date_field\Plugin\field\widget\DateFieldListWidget.
 */

namespace Drupal\date_field\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\date_field\Plugin\field\widget\DateFieldWidgetBase;
use Drupal\date_api\DateGranularity;

/**
 * Plugin implementation of the 'date' widget.
 *
 * @Plugin(
 *   id = "date_field_list",
 *   module = "date",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "date_format" = "Y-m-d",
 *     "date_element" = "date",
 *     "input_format_custom" = "",
 *     "increment" = 15,
 *     "text_parts" = {""},
 *     "year_range" = "-3:+3",
 *     "all_day_toggle" = 0
 *   }
 * )
 */
class DateFieldListWidget extends DateFieldWidgetBase {

  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['date_format'] = array(
      '#type' => 'select',
      '#title' => t('Date entry format'),
      '#default_value' => $this->getSetting('date_format'),
      '#options' => $this->formatOptions(),
      '#description' => t('Control the order and format of the options users see.'),
      '#weight' => 3,
      '#fieldset' => 'date_format',
    );

    $form['all_day_toggle'] = array(
      '#type' => 'select',
      '#title' => t('All day toggle'),
      '#description' => t("Add an 'All day' checkbox to the form to allow the user to hide or show the time."),
      '#default_value' => $this->getSetting('all_day_toggle'),
      '#options' => array(0 => t('No'), 1 => t('Yes')),
      '#weight' => 2,
    );
    
    $element['advanced'] = array(
      '#type' => 'fieldset',
      '#title' => t('Advanced settings'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#fieldset' => 'date_format',
      '#weight' => 9,
    );
    $element['advanced']['text_parts'][$key] = array(
      '#type' => 'value',
      '#value' => (int) in_array($key, (array) $this->getSetting('text_parts')),
    );

    $element['advanced']['text_parts'] = array('#theme' => 'date_text_parts');
    $text_parts = (array) $this->getSetting('text_parts');
    foreach (DateGranularity::granularityNames() as $key => $value) {
      $element['advanced']['text_parts'][$key] = array(
        '#type' => 'radios',
        '#default_value' => in_array($key, $text_parts) ? 1 : 0,
        '#options' => array(0 => '', 1 => ''),
      ); 
    }

    return $element;
  }

  function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {

    $element = parent::formElement($items, $delta, $element, $langcode, $form, $form_state);

    $element += array(
      '#type' => 'datetime',
      '#date_increment'=>  $this->getSetting('increment'),
      '#date_text_parts'=>  $this->getSetting('text_parts'),
      '#date_year_range'=>  $this->getSetting('year_range'),
      '#date_all_day_toggle'=>  $this->getSetting('all_day_toggle'),
      '#date_date_format'=>  $this->getSetting('date_format'),
      '#date_date_element' => $this->getSetting('date_element'),
    );

    return $element;
  }

}
