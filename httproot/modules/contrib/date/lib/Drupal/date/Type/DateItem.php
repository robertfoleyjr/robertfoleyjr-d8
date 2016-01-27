<?php

/**
 * @file
 * Definition of Drupal\date\Type\DateItem.
 */

namespace Drupal\date\Type;

use Drupal\Core\Entity\Field\FieldItemBase;

/**
 * Defines the 'text_item' and 'text_long_item' entity field items.
 */
class DateItem extends FieldItemBase {

  /**
   * Field definitions of the contained properties.
   *
   * @see self::getPropertyDefinitions()
   *
   * @var array
   */
  static $propertyDefinitions;

  /**
   * Implements ComplexDataInterface::getPropertyDefinitions().
   */
  public function getPropertyDefinitions() {

    if (!isset(self::$propertyDefinitions)) {
      self::$propertyDefinitions['value'] = array(
        'type' => 'string',
        'label' => t('Date value'),
      );
      self::$propertyDefinitions['value2'] = array(
        'type' => 'string',
        'label' => t('End Date value'),
      );
      self::$propertyDefinitions['processed'] = array(
        'type' => 'string',
        'label' => t('Processed text'),
        'description' => t('The text value with the text format applied.'),
        'computed' => TRUE,
        'class' => '\Drupal\text\TextProcessed',
        'settings' => array(
          'text source' => 'value',
        ),
      );
    }
    return self::$propertyDefinitions;
  }
}
