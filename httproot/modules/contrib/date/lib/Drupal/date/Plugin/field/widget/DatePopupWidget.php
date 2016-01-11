<?php
/**
 * @file
 * Definition of Drupal\date\Plugin\field\widget\DatePopupWidget.
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
 *   id = "date_popup",
 *   module = "date",
 *   label = @Translation("Pop-up calendar"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "date_date_format" = "Y-m-d",
 *     "date_date_element" = "date",
 *     "date_time_format" = "H:i:s",
 *     "date_time_element" = "time",
 *     "input_format_custom" = "",
 *     "increment" = 15,
 *     "text_parts" = {""},
 *     "year_range" = "-3:+3",
 *   }
 * )
 */
class DatePopupWidget extends DateWidgetBase {

  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm(array $form, array &$form_state);

    $element['date_date_elementt'] = array(
      '#type' => 'select',
      '#title' => t('Date element'),
      '#default_value' => $settings['date_date_element'],
      '#options' => array('date' => t('HTML5 Date'), 'datetime' => t('HTML5 Datetime'), 'datetime-local' => t('HTML5 Datetime-local'), 'text' => t('Textfield'), 'none' => t('<Hidden>')),
      '#description' => t('The element to use for the date.'),
      '#weight' => 2,
      '#fieldset' => 'date_format',
    );
    $element['date_date_format']['#description'] = t('Format for the date part of the date.');
    $element['date_time_element'] = array(
      '#type' => 'select',
      '#title' => t('Time element'),
      '#default_value' => $settings['date_time_element'],
      '#options' => array('time' => t('HTML5 Time element'), 'text' => t('Textfield'), 'none' => t('<Hidden>')),
      '#description' => t('The element to use for the time.'),
      '#weight' => 4,
      '#fieldset' => 'date_format',
    );
    $element['date_time_format'] = array(
      '#type' => 'select',
      '#title' => t('Time entry format'),
      '#default_value' => $settings['date_time_format'],
      '#options' => $this->formatOptions(),
      '#description' => t('Format for the time part of the date.'),
      '#weight' => 5,
      '#fieldset' => 'date_format',
    );
    return $element;
  }

  function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {

    $element = parent::formElement($items, $delta, $element, $langcode, $form, $form_state)

    $element += array(
      '#type' => 'datetime',
      '#date_date_format'=>  $this->getSetting('date_date_format'),
      '#date_date_element' => $this->getSetting('date_date_element'),
      '#date_date_callbacks' => array('datetime_jquery_datepicker'),
      '#date_time_format' => $this->getSetting('date_time_format'),
      '#date_time_element' => $this->getSetting('date_time_element'),
      '#date_time_callbacks' => array(),
    );

    return $element;
  }
}
