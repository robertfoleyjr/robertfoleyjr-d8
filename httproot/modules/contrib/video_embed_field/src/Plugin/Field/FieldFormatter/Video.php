<?php

/**
 * @file
 * Contains \Drupal\video_embed_field\Plugin\Field\FieldFormatter\Video.
 */

namespace Drupal\video_embed_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\video_embed_field\ProviderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the video field formatter.
 *
 * @FieldFormatter(
 *   id = "video_embed_field_video",
 *   label = @Translation("Video"),
 *   field_types = {
 *     "video_embed_field"
 *   }
 * )
 */
class Video extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The embed provider plugin manager.
   *
   * @var \Drupal\video_embed_field\ProviderManagerInterface
   */
  protected $providerManager;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $settings = $this->getSettings();
    foreach ($items as $delta => $item) {
      $provider = $this->providerManager->loadProviderFromInput($item->value);
      $element[$delta] = $provider->renderEmbedCode($settings['width'], $settings['height'], $settings['autoplay']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'width' => '854',
      'height' => '480',
      'autoplay' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['autoplay'] = [
      '#title' => t('Autoplay'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('autoplay'),
    ];
    $form['width'] = [
      '#title' => t('Width'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('width'),
      '#required' => TRUE,
    ];
    $form['height'] = [
      '#title' => t('Height'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('height'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary[] = t('Embedded Video (@widthx@height@autoplay).', [
      '@width' => $this->getSetting('width'),
      '@height' => $this->getSetting('height'),
      '@autoplay' => $this->getSetting('autoplay') ? t(', autoplaying') : '' ,
    ]);
    return $summary;
  }

  /**
   * Constructs a new instance of the plugin.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\video_embed_field\ProviderManagerInterface $provider_manager
   *   The video embed provider manager.
   */
  public function __construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings, ProviderManagerInterface $provider_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->providerManager = $provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('video_embed_field.provider_manager')
    );
  }

}
