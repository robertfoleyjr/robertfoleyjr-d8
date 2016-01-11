<?php

/**
 * @file
 * Definition of Drupal\date\Type\DateItem.
 */

namespace Drupal\date_field\Type;

use Drupal\Core\Entity\Field\FieldItemBase;

/**
 * Defines the 'text_item' and 'text_long_item' entity field items.
 */
class DateFieldItem extends FieldItemBase {

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
        'type' => 'date',
        'label' => t('Date value'),
      );
      self::$propertyDefinitions['value2'] = array(
        'type' => 'date',
        'label' => t('End Date value'),
      );
      self::$propertyDefinitions['data'] = array(
        'type' => 'string',
        'label' => t('A serialized array with more date info.'),
      );
    }
    return self::$propertyDefinitions;
  }
}
