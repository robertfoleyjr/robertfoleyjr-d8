<?php

/**
 * @file
 * Contains \Drupal\config_sync\Form\ConfigSync.
 */

namespace Drupal\config_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\config_sync\ConfigSyncListerInterface;
use Drupal\config_sync\ConfigSyncManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConfigSync extends FormBase {

  /**
   * @var \Drupal\config_sync\ConfigSyncListerInterface
   */
  protected $configSyncLister;

  /**
   * @var \Drupal\config_sync\ConfigSyncManagerInterface
   */
  protected $configSyncManager;

  /**
   * Constructs a new ConfigSync object.
   *
   * @param \Drupal\config_sync\ConfigSyncListerInterface $config_sync_lister
   *   The configuration syncronizer lister.
   * @param \Drupal\config_sync\ConfigSyncManagerInterface $config_sync_manager
   *   The configuration syncronizer manager.
   */
  public function __construct(ConfigSyncListerInterface $config_sync_lister, ConfigSyncManagerInterface $config_sync_manager) {
    $this->configSyncLister = $config_sync_lister;
    $this->configSyncManager = $config_sync_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config_sync.lister'),
      $container->get('config_sync.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'config_sync_config_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $changelist = $this->configSyncLister->getFullChangelist();
    $form['safe'] = [
      '#type' => 'details',
      '#title' => $this->t('Safe changes'),
      '#description' => $this->t('Safe changes are those that will not overwrite changes that have been made since configuration was originally imported.'),
      '#open' => TRUE,
    ];
    if (empty($changelist)) {
      $form['safe']['message'] = [
        '#markup' => $this->t('No safe changes available for import.'),
      ];
    }
    else {
      $count = $this->getChangelistCount($changelist);

      $form['safe']['message'] = [
        '#markup' => $this->formatPlural($count, '1 safe configuration change available for import.', '@count safe configuration changes available for import.'),
      ];

      $form['safe']['safe_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import safe changes'),
        '#submit' => array('::doSafeConfigUpdates'),
      ];
    }

    $changelist = $this->configSyncLister->getFullChangelist(FALSE);
    $form['all'] = [
      '#type' => 'details',
      '#title' => $this->t('All changes'),
      '#description' => $this->t('All changes include those that may overwrite changes that have been made since configuration was originally imported.'),
    ];
    if (empty($changelist)) {
      $form['all']['message'] = [
        '#markup' => $this->t('No changes available for import.'),
      ];
    }
    else {
      $count = $this->getChangelistCount($changelist);

      $form['all']['message'] = [
        '#markup' => $this->formatPlural($count, '1 configuration change available for import.', '@count configuration changes available for import.'),
      ];

      $form['all']['safe_submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Import all changes'),
        '#submit' => array('::doAllConfigUpdates'),
      ];
    }

    return $form;
  }

  /**
   * Form submission handler for the import safe changes button on the
   * configuration synchronizer form.
   */
  public function doSafeConfigUpdates(array &$form, FormStateInterface $form_state) {
    $this->configSyncManager->updateAll();
  }

  /**
   * Form submission handler for the import all changes button on the
   * configuration synchronizer form.
   */
  public function doAllConfigUpdates(array &$form, FormStateInterface $form_state) {
    $this->configSyncManager->updateAll(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Calculates the number of changes in a changelist.
   *
   * @param array $changelist
   *   A change list.
   */
  protected function getChangelistCount($changelist) {
    $count = 0;
    foreach ($changelist as $extension_type => $extension) {
      foreach ($extension as $change_type => $items) {
        $count += count($items);
      }
    }
    return $count;
  }

}
