<?php
/**
 * @file
 * Contains Drupal\plugin_message|MessageSettingsForm.
 */

namespace Drupal\config_files;

use Drupal\Core\Config\ConfigManager;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;


class ConfigFilesSettingsForm implements ContainerInjectionInterface, FormInterface {

  /**
   * @var \Drupal\Core\Config\ConfigFactory.
   */
  protected $config;

  /**
   * @var \Drupal\Core\Config\ConfigManager
   */
  protected $configManager;

  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'), $container->get('config.manager'));
  }

  public function __construct(ConfigFactory $config, ConfigManager $configManager) {
    $this->config = $config;
    $this->configManager = $configManager;
  }

  public function getFormID() {
    return 'config_files_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config->get('config_files.config');
    $form['directory'] = array(
      '#type' => 'textfield',
      '#title' => t('Active files directory'),
      '#description' => t('Provide an absolute path to a directory outside of the Drupal webroot. DO NOT use Drupal\'s active configuration directory.'),
      '#default_value' => $config->get('directory'),
    );
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    );
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo validate that we got a git url we can commit to and that this is a private key.
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config->get('config_files.config');
    $config->set('directory', $form_state->getValue('directory'));
    $config->save();

    $active_dir = $config->get('directory');

    foreach ($this->configManager->getConfigFactory()->listAll() as $name) {
      $yml = Yaml::dump($this->configManager->getConfigFactory()->get($name)->getRawData());
      $file_name = $name . '.yml';
      file_put_contents($active_dir . '/' . $file_name, $yml, FILE_EXISTS_REPLACE);
    }
  }
}

