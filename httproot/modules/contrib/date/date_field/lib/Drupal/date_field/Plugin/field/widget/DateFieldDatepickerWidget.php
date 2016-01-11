<?php
/**
 * @file
 * Definition of Drupal\datefield\Plugin\field\widget\DateFieldDatepickerWidget.
 */

namespace Drupal\date_field\Plugin\field\widget;

use Drupal\Core\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\field\Plugin\Type\Widget\WidgetBase;
use Drupal\date_field\Plugin\field\widget\DateFieldWidgetBase;

/**
 * Plugin implementation of the 'date' widget.
 *
 * @Plugin(
 *   id = "date_field_datepicker",
 *   module = "date_field",
 *   label = @Translation("Datepicker"),
 *   field_types = {
 *     "date"
 *   },
 *   settings = {
 *     "date_format" = "Y-m-d",
 *     "date_element" = "date",
 *     "date_callbacks" = {
 *       "datetime_jquery_datepicker"
 *     },
 *     "time_format" = "H:i:s",
 *     "time_element" = "time",
 *     "time_callbacks" = {
 *        "date_field_all_day_toggle_callback"
 *     },
 *     "increment" = 15,
 *     "text_parts" = {""},
 *     "year_range" = "-3:+3",
 *   }
 * )
 */
class DateFieldDatepickerWidget extends DateFieldWidgetBase {

  function settingsForm(array $form, array &$form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['date_element'] = array(
      '#type' => 'select',
      '#title' => t('Date element'),
      '#default_value' => $this->getSetting('date_element'),
      '#options' => array('date' => t('HTML5 Date'), 'datetime' => t('HTML5 Datetime'), 'datetime-local' => t('HTML5 Datetime-local'), 'text' => t('Text field'), 'none' => t('<Hidden>')),
      '#description' => t('The element to use for the date in a textfield.'),
      '#fieldset' => 'date_format',
    );
    // The custom format only applies for non-HTML5 elements.
    $element['date_format'] = array(
      '#type' => 'textfield',
      '#title' => t('Date text field entry format'),
      '#default_value' => $this->getSetting('date_format'),
      '#description' => t('Custom format for the text field date. See the <a href="@url">PHP manual</a> for available options.', array('@url' => 'http://php.net/manual/function.date.php')),
      '#fieldset' => 'date_format',
      '#states' => array(
        'visible' => array(
          ":input[name=\"instance[widget][settings][date_element]\"]" => array('value' => 'text'),
      )),
    );

    $element['time_element'] = array(
      '#type' => 'select',
      '#title' => t('Time element'),
      '#default_value' => $this->getSetting('time_element'),
      '#options' => array('time' => t('HTML5 Time'), 'text' => t('Text field'), 'none' => t('<Hidden>')),
      '#description' => t('The element to use for the time.'),
      '#fieldset' => 'date_format',
    );
    // The custom format only applies for non-HTML5 elements.
    $element['time_format'] = array(
      '#type' => 'textfield',
      '#title' => t('Time text field entry format'),
      '#default_value' => $this->getSetting('time_format'),
      '#description' => t('Custom format for the text field time See the <a href="@url">PHP manual</a> for available options.', array('@url' => 'http://php.net/manual/function.date.php')),
      '#fieldset' => 'date_format',
      '#states' => array(
        'visible' => array(
          ":input[name=\"instance[widget][settings][time_element]\"]" => array('value' => 'text'),
      )),
    );

    $options = module_invoke_all('date_field_date_callbacks');
    $element['date_callbacks'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Additional date features'),
      '#default_value' => $this->getSetting('date_callbacks'),
      '#options' => $options,
      '#fieldset' => 'date_format',
      '#states' => array(
        'invisible' => array(
          ":input[name=\"instance[widget][settings][date_element]\"]" => array('value' => 'none'),
      )),
    );

    $options = module_invoke_all('date_field_time_callbacks');
    $element['time_callbacks'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Additional time features'),
      '#default_value' => $this->getSetting('time_callbacks'),
      '#options' => $options,
      '#fieldset' => 'date_format',
      '#states' => array(
        'invisible' => array(
          ":input[name=\"instance[widget][settings][time_element]\"]" => array('value' => 'none'),
      )),
    );

    return $element;
  }

  function formElement(array $items, $delta, array $element, $langcode, array &$form, array &$form_state) {

    $element = parent::formElement($items, $delta, $element, $langcode, $form, $form_state);

    $element += array(
      '#type' => 'datetime',
      '#date_increment'=>  $this->getSetting('increment'),
      '#date_text_parts'=>  $this->getSetting('text_parts'),
      '#date_year_range'=>  $this->getSetting('year_range'),
      '#date_date_format'=>  $this->getSetting('date_format'),
      '#date_date_element' => $this->getSetting('date_element'),
      '#date_date_callbacks' => $this->getSetting('date_callbacks'),
      '#date_time_format' => $this->getSetting('time_format'),
      '#date_time_element' => $this->getSetting('time_element'),
      '#date_time_callbacks' => $this->getSetting('time_callbacks'),
    );

    return $element;
  }
}
