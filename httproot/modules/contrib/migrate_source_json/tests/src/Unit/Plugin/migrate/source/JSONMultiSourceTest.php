<?php

/**
 * @file
 * Contains Drupal\Tests\migrate_source_json\Unit\Plugin\migrate\source\JSONMultiSourceTest
 */

namespace Drupal\Tests\migrate_source_json\Unit\Plugin\migrate\source;

use Drupal\migrate_source_json\Plugin\migrate\source\JSONMultiSource;
use Drupal\Tests\migrate_source_json\Unit\JSONUnitTestCase;

/**
 * @coversDefaultClass Drupal\migrate_source_json\Plugin\migrate\source\JSONSource
 *
 * @group migrate_source_json
 */
class JSONMultiSourceTest extends JSONUnitTestCase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->source = new JSONMultiSource($this->configuration, $this->pluginId, $this->pluginDefinition, $this->plugin);

  }

  /**
   * Tests the construction of JSON.
   *
   * @test
   *
   * @covers ::__construct
   */
  public function create() {
    $this->assertInstanceOf('\Drupal\migrate_source_json\Plugin\migrate\source\JSONMultiSource', $this->source);
  }

  /**
   * Tests that a missing path will throw an exception.
   *
   * @test
   *
   * @expectedException \Drupal\migrate\MigrateException
   *
   * @expectedExceptionMessage The source configuration must include path.
   */
  public function migrateExceptionPathMissing() {
    $configuration = $this->configuration;
    unset($configuration['path']);
    new JSONMultiSource($configuration, $this->pluginId, $this->pluginDefinition, $this->plugin);
  }

  /**
   * Tests that missing identifiers will throw an exception.
   *
   * @test
   *
   * @expectedException \Drupal\migrate\MigrateException
   *
   * @expectedExceptionMessage The source configuration must include identifier.
   */
  public function migrateExceptionIdentifiersMissing() {
    $configuration = $this->configuration;
    unset($configuration['identifier']);
    new JSONMultiSource($configuration, $this->pluginId, $this->pluginDefinition, $this->plugin);
  }

  /**
   * Tests that toString functions as expected.
   *
   * @test
   *
   * @covers ::__toString
   */
  public function toString() {
    $this->assertEquals($this->configuration['path'], (string) $this->source);
  }

  /**
   * Tests that fields functions as expected.
   *
   * @test
   *
   * @covers ::fields
   */
  public function fields() {
    $fields = array('id', 'user_name', 'description');
    $this->assertArrayEquals($fields, $this->source->fields());
  }

  /**
   * Tests that get functions as expected.
   *
   * @test
   *
   * @covers ::get
   */
  public function get() {
    $this->assertEquals('id', $this->source->get('identifier'));
  }

  /**
   * Tests initialization of the iterator.
   *
   * @test
   *
   * @covers ::initializeIterator
   */
  public function initializeIterator() {
//    $configuration = $this->configuration;
//    $configuration['path'] = 'top.json';
//    $configuration['identifierDepth'] = 0;
//    $source = new JSONMultiSource($configuration, $this->pluginId, $this->pluginDefinition, $this->plugin);
//    $iterator = $this->invokeMethod($source, 'initializeIterator');
//    $this->assertInstanceOf('Iterator', $iterator);
//    $iterator->rewind();
//    $item = $iterator->current();
//    $this->assertEquals($item['id'], 1);
//
//    $configuration = $this->configuration;
//    $configuration['path'] = 'nested.json';
//    $configuration['identifierDepth'] = 1;
//    $source = new JSONMultiSource($configuration, $this->pluginId, $this->pluginDefinition, $this->plugin);
//    $iterator = $this->invokeMethod($source, 'initializeIterator');
//    $iterator->rewind();
//    $item = $iterator->current();
//    $this->assertEquals($item['id'], 1);
//
  }

}
