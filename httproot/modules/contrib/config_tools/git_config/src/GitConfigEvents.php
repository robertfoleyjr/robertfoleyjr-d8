<?php
/**
 * @file
 * Contains ConfigEvents.php.
 */

namespace Drupal\git_config;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use GitWrapper\GitException;
use GitWrapper\GitWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GitConfigEvents implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = array('saveConfig');
    $events[ConfigEvents::DELETE][] = array('deleteConfig');
    return $events;
  }

  public function saveConfig(ConfigCrudEvent $event) {
    $config = \Drupal::config('git_config.config');
    $private_key = $config->get('private_key');
    $git_url = $config->get('git_url');
    $active_dir = \Drupal::config('config_files.config')->get('directory');
    if ($active_dir && !empty($private_key) && !empty($git_url)) {
      $wrapper = new GitWrapper();
      $wrapper->setPrivateKey($config->get('private_key'));
      $git = $wrapper->workingCopy($active_dir);

      $object = $event->getConfig();
      $file_name = $object->getName() . '.yml';

      try {
        $user = \Drupal::currentUser();
        $name = $user->getAccount()->getUsername();
        $git->add($file_name)
          ->commit(t('Updated by @name', array('@name' => $name)))
          ->push();
      }
      catch (GitException $e) {
        drupal_set_message($e->getMessage(), 'warning');
      }
    }
  }

  public function deleteConfig(ConfigCrudEvent $event) {
    $config = \Drupal::config('git_config.config');
    $private_key = $config->get('private_key');
    $git_url = $config->get('git_url');
    $active_dir = \Drupal::config('config_files.config')->get('directory');
    if ($active_dir && !empty($private_key) && !empty($git_url)) {
      $wrapper = new GitWrapper();
      $wrapper->setPrivateKey($config->get('private_key'));
      $git = $wrapper->workingCopy($active_dir);

      $object = $event->getConfig();
      $file_name = $object->getName() . '.yml';

      try {
        $user = \Drupal::currentUser();
        $name = $user->getAccount()->getUsername();
        $git->rm($file_name)
          ->commit(t('Removed by @name', array('@name' => $name)))
          ->push();
      }
      catch (GitException $e) {
        drupal_set_message($e->getMessage(), 'warning');
      }
}
  }

} 