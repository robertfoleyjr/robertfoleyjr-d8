<?php

/**
 * @file
 * Contains \Drupal\video_embed_field\Tests\VideoEmbedFieldWidgetTest.
 */

namespace Drupal\video_embed_field\Tests;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simpletest\WebTestBase;

/**
 * Test the video embed field widget.
 *
 * @group video_embed_field
 */
class VideoEmbedFieldWidgetTest extends WebTestBase {

  /**
   * A user with permission to administer content types, node fields, etc.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The field name
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The name of the content type.
   *
   * @var string
   */
  protected $contentTypeName;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'video_embed_field',
    'field_ui',
    'node',
  ];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'access content',
    'administer content types',
    'administer node fields',
    'administer node form display',
    'bypass node access',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->fieldName = strtolower($this->randomMachineName());
    $this->contentTypeName = strtolower($this->randomMachineName());
    $this->drupalCreateContentType(['type' => $this->contentTypeName]);
    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => 'node',
      'type' => 'video_embed_field',
      'settings' => [
        'allowed_providers' => [],
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->contentTypeName,
      'settings' => [],
    ])->save();
    $this->fieldName = $this->fieldName;
  }

  /**
   * Test the input widget.
   */
  function testVideoEmbedFieldDefaultWidget() {
    entity_get_form_display('node', $this->contentTypeName, 'default')
      ->setComponent($this->fieldName, ['type' => 'video_embed_field_textfield',])
      ->save();

    $this->drupalLogin($this->adminUser);
    $node_title = $this->randomMachineName();

    // Test an invalid input.
    $this->drupalPostForm(Url::fromRoute('node.add', ['node_type' => $this->contentTypeName]), [
      'title[0][value]' => $node_title,
      $this->fieldName . '[0][value]' => 'Some useless value.',
    ], t('Save'));
    $this->assertRaw(t('Could not find a video provider to handle the given URL.'));

    // Test a valid input.
    $valid_input = 'https://vimeo.com/80896303';
    $this->drupalPostForm(NULL, [
      $this->fieldName . '[0][value]' => $valid_input,
    ], t('Save'));
    $this->assertRaw(t('@type %title has been created.', [
      '@type' => $this->contentTypeName,
      '%title' => $node_title
    ]));

    // Load the saved node and assert the valid value was saved into the field.
    $nodes = \Drupal::entityManager()
      ->getStorage('node')
      ->loadByProperties(['title' => $node_title]);
    $node = array_shift($nodes);
    $this->assertEqual($node->{$this->fieldName}[0]->value, $valid_input);
  }

}
