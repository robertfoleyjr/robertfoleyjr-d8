<?php
/**
 * @file
 * Definition of Drupal\date\Plugin\field\widget\DateSelectWidget.
 */

namespace Drupal\date\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\date\Plugin\field\widget\DateTextWidget;

/**
 * Plugin implementation of the 'date' widget.
 *
 * @Plugin(
 *   id = "date_select",
 *   module = "date",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "date_date_format" = "Y-m-d",
 *     "date_date_element" = "date",
 *     "input_format_custom" = "",
 *     "increment" = 15,
 *     "text_parts" = {""},
 *     "year_range" = "-3:+3",
 *   }
 * )
 */
class DateSelectWidget extends DateWidgetBase {

  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm(array $form, array &$form_state);

    $element['date_date_format'] = array(
      '#type' => 'select',
      '#title' => t('Date entry format'),
      '#default_value' => $settings['date_date_format'],
      '#options' => $this->formatOptions(),
      '#description' => t('Control the order and format of the options users see.'),
      '#weight' => 3,
      '#fieldset' => 'date_format',
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
      '#value' => (int) in_array($key, (array) $settings['text_parts']),
    );

    $element['advanced']['text_parts'] = array('#theme' => 'date_text_parts');
    $text_parts = (array) $settings['text_parts'];
    foreach (DateGranularity::granularityNames() as $key => $value) {
      $element['advanced']['text_parts'][$key] = array(
        '#type' => 'radios',
        '#default_value' => in_array($key, $text_parts) ? 1 : 0,
        '#options' => array(0 => '', 1 => ''),
      );
    }

    return $element;
  }
}
