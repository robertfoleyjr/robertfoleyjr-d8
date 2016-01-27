<?php
/**
 * @file
 * Contains ConfigEvents.php.
 */

namespace Drupal\config_files;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

class FileConfigEvents implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = array('saveConfig', 10);
    $events[ConfigEvents::DELETE][] = array('deleteConfig', 10);
    return $events;
  }

  /**
   * Writes a file to the configured active config directory when a
   * ConfigEvents::SAVE event is dispatches.
   *
   * @param ConfigCrudEvent $event
   */
  public function saveConfig(ConfigCrudEvent $event) {
    $config = \Drupal::config('config_files.config');
    $active_dir = $config->get('directory');
    if ($active_dir) {
      $object = $event->getConfig();
      $file_name = $object->getName() . '.yml';
      $yml = Yaml::dump($object->getRawData());
      file_put_contents($active_dir . '/' . $file_name, $yml, FILE_EXISTS_REPLACE);
    }
  }

  /**
   * Deletes files from the configured active config directory when a
   * ConfigEvents::DELETE event is dispatched.
   *
   * @param ConfigCrudEvent $event
   */
  public function deleteConfig(ConfigCrudEvent $event) {
    $config = \Drupal::config('config_files.config');
    $active_dir = $config->get('directory');
    if ($active_dir) {
      $object = $event->getConfig();
      $file_name = $object->getName() . '.yml';
      unlink($active_dir . '/' . $file_name);
    }
  }

} 