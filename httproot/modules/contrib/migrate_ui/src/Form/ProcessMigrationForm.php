<?php

/**
 * @file
 * Contains \Drupal\migrate_ui\Form\ProcessMigrationForm
 */

namespace Drupal\migrate_ui\Form;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\migrate\Entity\MigrationInterface;

/**
 * This form is specifically for configuring process pipelines.
 */
class ProcessMigrationForm extends MigrationEntityFormBase {

  /**
   * The name of the destination field we're configuring.
   *
   * @var string
   */
  protected $destinationField;

  /**
   * Entity builder for the migration.
   */
  public function buildEntityFromForm($entity_type_id, MigrationInterface $migration, &$form, FormStateInterface $form_state) {
    $source_fields = $form_state->getValue(['source_field']);
    $source_fields = count($source_fields) > 1 ? array_values($source_fields) : reset($source_fields);

    if ($source_fields) {
      $process = [
        ['plugin' => 'get', 'source' => $source_fields],
      ];
      $values = $form_state->getValues();

      // Sort based on the weight field.
      if (!empty($values['process_pipeline'])) {
        usort($values['process_pipeline'], ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);
      }

      $i = 0;
      while (isset($values['process_pipeline'][$i])) {
        // If none selected, ignore it.
        $config = isset($values['process_pipeline'][$i]['pipeline_step']['plugin_config']) ? $values['process_pipeline'][$i]['pipeline_step']['plugin_config'] : [];
        $process[] = [
          'plugin' => $values['process_pipeline'][$i]['pipeline_step']['plugin'],
        ] + $config;
        $i++;
      }

      // If we've added a new plugin, tack it on the end.
      $op = (string) $form_state->getTriggeringElement()['#value'];
      if (($plugin = $form_state->getValue('plugin_selection')) && $plugin !== '_none' && $op === 'Configure') {
        $process[] = [
          'plugin' => $plugin,
        ];
      }

      if ($op === 'Remove') {
        // If the action was remove, then we remove it.
        $array_parents = $form_state->getTriggeringElement()['#array_parents'];
        $delta = $array_parents[1];
        // Offset by one for the first item which is always get.
        unset($process[$delta + 1]);
        $process = array_values($process);
      }
    }
    else {
      $process = [];
    }

    $migration->setProcessOfProperty($this->destinationField, $process);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $destination_field = NULL) {
    $this->destinationField = $destination_field;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['#type'] = 'table';
    $form['#entity_builders'] = [[$this, 'buildEntityFromForm']];
    $form['source'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Source Info'),
      '#weight' => 0,
    ];

    $fields = array_keys($this->entity->getSourcePlugin()->fields());
    $source_fields = array_combine($fields, $fields);
    $form['source']['source_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Source field will be imported into %destination_field', ['%destination_field' => $this->destinationField]),
      '#options' => $source_fields,
      '#default_value' => $this->getSourceValue($this->destinationField),
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    $form['process'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Configure Process'),
      '#attributes' => ['class' => ['container-inline']],
      '#weight' => 0,
    ];
    $form['process']['plugin_selection'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a plugin'),
      '#default_value' => '',
      '#options' => ['_none' => '- Select - '] + $this->getProcessIds(),
    ];
    $form['process']['plugin_configure'] = [
      '#type' => 'submit',
      '#value' => $this->t('Configure'),
      '#submit' => [[$this, 'addMoreSubmit']],
      '#ajax' => [
        'callback' => '::addMoreAjax',
        'wrapper' => 'process-pipeline-wrapper',
        'effect' => 'fade',
      ],
      '#states' => [
        'invisible' => [
          ':input[name="plugin_selection"]' => ['value' => '_none'],
        ],
      ],
    ];

    $form['process_pipeline'] = [
      '#type' => 'table',
      '#attributes' => ['id' => 'migrate-process-table'],
      '#header' => [$this->t('Process Pipeline'), $this->t('Weight')],
      '#prefix' => '<div id="process-pipeline-wrapper">',
      '#suffix' => '</div>',
      '#weight' => 2,
      '#tree' => TRUE,

    ];
    foreach ($this->getProcessGivenKey($this->destinationField) as $delta => $process_array) {
      if (empty($process_array['plugin']) || $process_array['plugin'] === 'get') {
        continue;
      }

      // Generate the configuration form for an individual step.
      $config_form = [
        '#attributes' => ['class' => ['draggable']],
        'pipeline_step' => [
          '#type' => 'fieldset',
          '#title' => $this->t('Step @step', ['@step' => $delta]),
          '#attributes' => ['class' => ['migrate-process']],
          'plugin' => [
            '#type' => 'textfield',
            '#disabled' => TRUE,
            '#default_value' => $process_array['plugin'],
          ],
          'plugin_config' => $this->getProcessForm($process_array['plugin']),
          'plugin_remove' => [
            '#type' => 'submit',
            '#name' => 'remove_' . $delta,
            '#value' => $this->t('Remove'),
            '#submit' => [[$this, 'removeProcessSubmit']],
            '#ajax' => [
              'callback' => '::removeProcessAjax',
              'wrapper' => 'process-pipeline-wrapper',
              'effect' => 'fade',
            ],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#default_value' => $delta,
          '#delta' => $delta,
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#attributes' => [
            'class' => ['process-weight', 'process-weight-' . $delta],
          ],
        ],
      ];

      $form['process_pipeline']['#tabledrag'][] = [
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'process-weight',
        'subgroup' => 'process-weight-' . $delta,
        'hidden' => TRUE,
      ];

      $form['process_pipeline'][] = $config_form;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    drupal_set_message($this->t('Migration @migration process has been updated.', ['@migration' => $this->entity->label()]));
    $form_state->setRedirect('entity.migration.edit_form', ['migration' => $this->entity->id()]);
  }

  /**
   * Submission handler for the "Add another item" button.
   */
  public function addMoreSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for the add another item button.
   */
  public function addMoreAjax(array $form, FormStateInterface $form_state) {
    return $form['process_pipeline'];
  }

  /**
   * Submit handler for removing a process step.
   */
  public function removeProcessSubmit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for remove process.
   */
  public function removeProcessAjax(array $form, FormStateInterface $form_state) {
    return $form['process_pipeline'];
  }

  /**
   * Get a form for a process plugin.
   *
   * @param string $process_id
   *   The Process id for the plugin.
   *
   * @return array
   *   An array representing the form for this process config.
   */
  protected function getProcessForm($process_id) {
    /** @var \Drupal\Core\Config\TypedConfigManager $config_manager */
    $config_manager = \Drupal::service('config.typed');
    $definition = $config_manager->getDefinition('migrate.process.' . $process_id);

    // We don't need the plugin definition.
    unset($definition['mapping']['plugin']);

    $element = [];
    foreach ($definition['mapping'] as $config_name => $config_info) {
      $element[$config_name] = [
        '#type' => 'textfield',
        '#title' => $config_info['label'],
      ];
    }
    return $element;
  }

  /**
   * Gets the process plugins that are marked for the ui.
   *
   * To have our custom process plugins appear in the UI, you must add
   * "ui" = TRUE to your process plugin annotation definition.
   *
   * @return array
   *   An array where the keys and values are process ids.
   */
  protected function getProcessIds() {
    $definitions = array_filter($this->processPluginManager->getDefinitions(), function($definition) {
      return isset($definition['ui']) && $definition['ui'] === TRUE;
    });
    $process_ids = array_keys($definitions);
    return array_combine($process_ids, $process_ids);
  }

}
