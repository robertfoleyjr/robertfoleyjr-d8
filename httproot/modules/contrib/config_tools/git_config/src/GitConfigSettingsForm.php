<?php
/**
 * @file
 * Contains Drupal\plugin_message|MessageSettingsForm.
 */

namespace Drupal\git_config;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;


class GitConfigSettingsForm implements ContainerInjectionInterface, FormInterface {

  /**
   * @var \Drupal\Core\Config\ConfigFactory.
   */
  protected $config;

  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

  public function __construct(ConfigFactory $config) {
    $this->config = $config;
  }

  public function getFormID() {
    return 'git_config_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config->get('git_config.config');
    $form['git_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Git URL'),
      '#default_value' => $config->get('git_url'),
    );
    $form['private_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Private SSH Key'),
      '#default_value' => $config->get('private_key'),
      '#description' => t('The directory path to your private key for this git repository.'),
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
    $config = $this->config->get('git_config.config');
    $config->set('git_url', $form_state->getValue('git_url'));
    $config->set('private_key', $form_state->getValue('private_key'));
    $config->save();
  }
}

