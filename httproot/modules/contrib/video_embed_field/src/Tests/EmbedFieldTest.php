<?php

/**
 * @file
 * Contains \Drupal\video_embed_field\Tests\EmbedFieldTest.
 */

namespace Drupal\video_embed_field\Tests;

use Drupal\Core\Url;
use Drupal\field\Tests\FieldUnitTestBase;
use Drupal\video_embed_field\Plugin\Field\FieldFormatter\Thumbnail;

/**
 * Test the embed field is functioning.
 *
 * @group video_embed_field
 */
class EmbedFieldTest extends FieldUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['video_embed_field', 'image'];

  /**
   * The test cases.
   */
  public function renderedFieldTestCases() {
    return [
      // YouTube field tests.
      'YouTube: Thumbnail' => [
        'https://www.youtube.com/watch?v=fdbFVWupSsw',
        [
          'type' => 'video_embed_field_thumbnail',
          'settings' => [],
        ],
        [
          '#theme' => 'image',
          '#uri' => 'public://video_thumbnails/fdbFVWupSsw.jpg',
        ],
      ],
      'YouTube: Embed Code' => [
        'https://www.youtube.com/watch?v=fdbFVWupSsw',
        [
          'type' => 'video_embed_field_video',
          'settings' => [
            'width' => '100%',
            'height' => '100%',
            'autoplay' => TRUE,
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'width' => '100%',
            'height' => '100%',
            'frameborder' => '0',
            'allowfullscreen' => 'allowfullscreen',
            'src' => 'https://www.youtube.com/embed/fdbFVWupSsw?autoplay=1&start=0',
          ],
        ],
      ],
      'YouTube: Time-index Embed Code' => [
        'https://www.youtube.com/watch?v=fdbFVWupSsw&t=100',
        [
          'type' => 'video_embed_field_video',
          'settings' => [
            'width' => '100%',
            'height' => '100%',
            'autoplay' => TRUE,
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'width' => '100%',
            'height' => '100%',
            'frameborder' => '0',
            'allowfullscreen' => 'allowfullscreen',
            'src' => 'https://www.youtube.com/embed/fdbFVWupSsw?autoplay=1&start=100',
          ],
        ],
      ],
      // Vimeo field tests.
      'Vimeo: Thumbnail' => [
        'https://vimeo.com/80896303',
        [
          'type' => 'video_embed_field_thumbnail',
          'settings' => [],
        ],
        [
          '#theme' => 'image',
          '#uri' => 'public://video_thumbnails/80896303.jpg',
        ],
      ],
      'Vimeo: Embed Code' => [
        'https://vimeo.com/80896303',
        [
          'type' => 'video_embed_field_video',
          'settings' => [
            'width' => '100%',
            'height' => '100%',
            'autoplay' => TRUE,
          ],
        ],
        [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'width' => '100%',
            'height' => '100%',
            'frameborder' => '0',
            'allowfullscreen' => 'allowfullscreen',
            'src' => 'https://player.vimeo.com/video/80896303?autoplay=1',
          ],
        ]
      ],
      // Linked thumbnails.
      'Linked Thumbnail: Content' => [
        'https://vimeo.com/80896303',
        [
          'type' => 'video_embed_field_thumbnail',
          'settings' => ['link_image_to' => Thumbnail::LINK_CONTENT],
        ],
        [
          '#type' => 'link',
          '#title' => [
            '#theme' => 'image',
            '#uri' => 'public://video_thumbnails/80896303.jpg',
          ],
          '#url' => 'entity.entity_test.canonical',
        ],
      ],
      'Linked Thumbnail: Provider' => [
        'https://vimeo.com/80896303',
        [
          'type' => 'video_embed_field_thumbnail',
          'settings' => ['link_image_to' => Thumbnail::LINK_PROVIDER],
        ],
        [
          '#type' => 'link',
          '#title' => [
            '#theme' => 'image',
            '#uri' => 'public://video_thumbnails/80896303.jpg',
          ],
          '#url' => 'https://vimeo.com/80896303',
        ],
      ],
      // Colorbox modals.
      'Colorbox Modal: Linked Image & Autoplay' => [
        'https://vimeo.com/80896303',
        [
          'type' => 'video_embed_field_colorbox',
          'settings' => [
            'link_image_to' => Thumbnail::LINK_PROVIDER,
            'autoplay' => TRUE,
            'width' => 500,
            'height' => 500,
          ],
        ],
        [
          '#type' => 'container',
          '#attributes' => [
            'data-video-embed-field-modal' => '<iframe width="500" height="500" frameborder="0" allowfullscreen="allowfullscreen" src="https://player.vimeo.com/video/80896303?autoplay=1"></iframe>',
            'class' => ['video-embed-field-launch-modal'],
          ],
          '#attached' => ['library' => ['video_embed_field/colorbox']],
          'children' => [
            '#type' => 'link',
            '#title' => [
              '#theme' => 'image',
              '#uri' => 'public://video_thumbnails/80896303.jpg',
            ],
            '#url' => 'https://vimeo.com/80896303',
          ],
        ],
      ],
    ];
  }

  /**
   * @dataProvider fieldRenderedValues
   *
   * Test the embed field.
   */
  public function assertEmbedField($url, $settings, $expected_output) {
    $entity = entity_create('entity_test');
    $entity->field_test->value = $url;
    $entity->save();
    $field = $entity->field_test->view($settings)[0];
    // Prevent circular references with comparing field output that
    // contains url objects.
    array_walk_recursive($field, function (&$value) {
      if ($value instanceof Url) {
        $value = $value->isRouted() ? $value->getRouteName() : $value->getUri();
      }
      $value = trim($value);
    });
    $this->assertEqual($field, $expected_output);
  }

  /**
   * Test the embed fields are working.
   */
  public function testEmbedField() {
    foreach ($this->renderedFieldTestCases() as $test_case) {
      $this->assertEmbedField($test_case[0], $test_case[1], $test_case[2]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    entity_create('field_storage_config', [
      'field_name' => 'field_test',
      'entity_type' => 'entity_test',
      'type' => 'video_embed_field',
    ])->save();
    entity_create('field_config', [
      'entity_type' => 'entity_test',
      'field_name' => 'field_test',
      'bundle' => 'entity_test',
    ])->save();
    // Fake colorbox being enabled for the purposes of testing.
    $module_handler = \Drupal::moduleHandler();
    $module_handler->addModule('colorbox', NULL);
  }

}
