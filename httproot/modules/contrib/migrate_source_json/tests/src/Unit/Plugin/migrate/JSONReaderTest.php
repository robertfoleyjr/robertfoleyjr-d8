<?php

/**
 * @file
 * Contains Drupal\Tests\migrate_source_json\Unit\JSONReaderTest
 */

namespace Drupal\Tests\migrate_source_json\Unit\Plugin\migrate;

use Drupal\migrate_source_json\Plugin\migrate\JSONReader;
use Drupal\Tests\migrate_source_json\Unit\JSONUnitTestCase;

/**
 * @coversDefaultClass Drupal\migrate_source_json\Plugin\migrate\JSONReader
 *
 * @group migrate_source_json
 */
class JSONReaderTest extends JSONUnitTestCase {

  /**
   * The JSONReader object.
   *
   * @var JSONReader
   */
  protected $reader;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $readerClass = $this->configuration['readerClass'];
    $this->reader = new $readerClass($this->configuration);
  }

  /**
   * Tests the construction of the reader.
   *
   * @test
   *
   * @covers ::__construct
   */
  public function create() {
    $this->assertInstanceOf('Drupal\migrate_source_json\Plugin\migrate\JSONReaderInterface', $this->reader);
  }

  /**
   * @test
   * @covers ::__construct
   * @expectedException \Drupal\Migrate\MigrateException
   */
  public function missingIdentifier() {
    $readerClass = $this->configuration['readerClass'];
    $configuration = $this->configuration;
    unset($configuration['identifier']);
    $this->reader = new $readerClass($configuration);
  }

  /**
   * @test
   * @covers ::__construct
   * @expectedException \Drupal\Migrate\MigrateException
   */
  public function missingIdentifierDepth() {
    $readerClass = $this->configuration['readerClass'];
    $configuration = $this->configuration;
    unset($configuration['identifierDepth']);
    $this->reader = new $readerClass($configuration);
  }

  /**
   * Tests setClient.
   *
   * @test
   *
   * @covers ::setClient
   * @covers ::getClient
   */
  public function setClient() {
    $this->reader->setClient();
    $client = $this->reader->getClient();
    $this->assertInstanceOf('Drupal\migrate_source_json\Plugin\migrate\JSONClientInterface', $client);
  }

  /**
   * Tests getIdentifier.
   *
   * @test
   *
   * @covers ::getIdentifier
   */
  public function getIdentifier() {
    $identifier = $this->reader->getIdentifier();
    $this->assertEquals($identifier, 'id');
  }

  /**
   * Tests getIdentifierDepth.
   *
   * @test
   *
   * @covers ::getIdentifierDepth
   */
  public function getIdentifierDepth() {
    $identifier = $this->reader->getIdentifierDepth();
    $this->assertEquals($identifier, 1);
  }

  /**
   * Tests getSourceData.
   *
   * @test
   *
   * @covers ::getSourceData
   */
  public function getTopLevelSourceData() {

    $iterator = $this->reader->getSourceData('top.json');
    $this->assertInstanceOf('RecursiveIteratorIterator', $iterator);
    $item = $iterator->current();
    $this->assertEquals($item['id'], 1);

  }

  /**
   * Tests getSourceData.
   *
   * @test
   *
   * @covers ::getSourceData
   */
  public function getNestedSourceData() {

    $iterator = $this->reader->getSourceData('nested.json');
    $this->assertInstanceOf('RecursiveIteratorIterator', $iterator);
    $item = $iterator->current();
    $this->assertEquals($item[0]['id'], 1);

  }

  /**
   * @test
   * @expectedException \Drupal\migrate\MigrateException
   */
  public function getInvalidSourceData() {
    $iterator = $this->reader->getSourceData('404.json');
  }

  /**
   * Tests getSourceFields.
   *
   * @test
   *
   * @covers ::getSourceFields
   */
  public function getTopLevelSourceFields() {

    $configuration = $this->configuration;
    $configuration['identifierDepth'] = 0;
    $reader = new $configuration['readerClass']($configuration);
    $array = $reader->getSourceFields('top.json');
    $this->assertEquals($array[0]['id'], 1);

  }

  /**
   * Tests getSourceFields.
   *
   * @test
   *
   * @covers ::getSourceFields
   */
  public function getNestedSourceFields() {

    $configuration = $this->configuration;
    $configuration['identifierDepth'] = 1;
    $reader = new $configuration['readerClass']($configuration);
    $array = $reader->getSourceFields('nested.json');
    $this->assertEquals($array[0]['id'], 1);

  }

  /**
   * Tests getSourceFieldsIterator.
   *
   * @test
   *
   * @covers ::getSourceFieldsIterator
   */
  public function getTopLevelSourceFieldsIterator() {

    $configuration = $this->configuration;
    $configuration['identifierDepth'] = 0;
    $reader = new $configuration['readerClass']($configuration);
    $iterator = $reader->getSourceFieldsIterator('top.json');
    $this->assertInstanceOf('Iterator', $iterator);
    $item = $iterator->current();
    $this->assertEquals($item['id'], 1);
  }

  /**
   * Tests getSourceFieldsIterator.
   *
   * @test
   *
   * @covers ::getSourceFieldsIterator
   */
  public function getNestedSourceFieldsIterator() {

    $configuration = $this->configuration;
    $configuration['identifierDepth'] = 1;
    $reader = new $configuration['readerClass']($configuration);
    $iterator = $this->reader->getSourceFieldsIterator('nested.json');
    $this->assertInstanceOf('Iterator', $iterator);
    $item = $iterator->current();
    $this->assertEquals($item['id'], 1);
  }

}
