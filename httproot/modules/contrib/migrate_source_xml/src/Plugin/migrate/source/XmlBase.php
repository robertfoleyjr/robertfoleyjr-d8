<?php

/**
 * @file
 * Contains Drupal\migrate_source_xml\Plugin\migrate\source\XmlBase.
 */

namespace Drupal\migrate_source_xml\Plugin\migrate\source;

use Drupal\migrate\Entity\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;

/**
 * Extension of SourcePluginBase to handle imports from XML files.
 */
abstract class XmlBase extends SourcePluginBase {
  /**
   * The iterator object to employ while processing the source.
   *
   * @var $reader MigrateXMLReader
   */
  protected $reader;

  /**
   * The MigrateXMLReader object serving as a cursor over the XML source.
   *
   * @return MigrateXMLReader
   *   MigrateXMLReader
   */
  public function getReader() {
    return $this->reader;
  }

  /**
   * The source URLs to load XML from.
   *
   * @var array
   */
  protected $sourceUrls = [];

  /**
   * An array of namespaces to explicitly register before Xpath queries.
   *
   * @var array
   */
  protected $namespaces;

  /**
   * The query string used to recognize elements being iterated.
   *
   * This is an xpath-like expression.
   *
   * @var string
   */
  protected $elementQuery = '';

  /**
   * The query string used to retrieve the primary key value.
   *
   * @var string
   */
  protected $idQuery = '';

  /**
   * The iterator class used to traverse the XML.
   *
   * @var string
   */
  protected $iteratorClass = '';

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, array $namespaces = []) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);

    if (empty($configuration['iterator_class'])) {
      $iterator_class = '\Drupal\migrate_plus\Plugin\migrate\source\MigrateXmlIterator';
    }
    else {
      $iterator_class = $configuration['iterator_class'];
    }

    if (!is_array($configuration['urls'])) {
      $configuration['urls'] = [$configuration['urls']];
    }

    $this->sourceUrls = $configuration['urls'];
    $this->activeUrl = NULL;
    $this->elementQuery = $configuration['element_query'];
    $this->idQuery = $configuration['id_query'];
    $this->iteratorClass = $iterator_class;
    $this->namespaces = $namespaces;
  }

  /**
   * Explicitly register namespaces on an XML element.
   *
   * @param \SimpleXMLElement $xml
   *   A SimpleXMLElement to register the namespaces on.
   */
  protected function registerNamespaces(\SimpleXMLElement &$xml) {
    foreach ($this->namespaces as $prefix => $namespace) {
      $xml->registerXPathNamespace($prefix, $namespace);
    }
  }

  /**
   * Return a string representing the source query.
   *
   * @return string
   *   source query
   */
  public function __toString() {
    // Clump the urls into a string
    // This could cause a problem when using
    // a lot of urls, may need to hash.
    $urls = implode(', ', $this->sourceUrls);
    return 'urls = ' . $urls .
           ' | item xpath = ' . $this->elementQuery .
           ' | item ID xpath = ' . $this->idQuery;
  }

  /**
   * Gets the iterator class used to traverse the XML.
   *
   * @return string
   *   The name of the class to be used for low-level XML processing.
   */
  public function iteratorClass() {
    return $this->iteratorClass;
  }

  /**
   * Gets the source URLs where the XML is located.
   *
   * @return array
   *   Array of URLs
   */
  public function sourceUrls() {
    return $this->sourceUrls;
  }

  /**
   * Gets the xpath-like query controlling the iterated elements.
   *
   * Matching elements will be presented by the iterator. Most xpath syntax
   * is supported (it is evaluated by \SimpleXMLElement::xpath), however the
   * SimpleXMLElement object is rooted at the context node and has no ancestors
   * available.
   *
   * @return string
   *   An xpath-like expression.
   */
  public function elementQuery() {
    return $this->elementQuery;
  }

  /**
   * Gets the xpath-like query from context node for source row id.
   *
   * @return string
   *   The xpath-like query from context node for source row id.
   */
  public function idQuery() {
    return $this->idQuery;
  }

  /**
   * Return a count of all available source records.
   *
   * @return int
   *   The number of available source records.
   */
  public function computeCount() {
    $count = 0;
    foreach ($this->sourceUrls as $url) {
      $iterator = new $this->iteratorClass($this);
      foreach ($iterator as $element) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Creates and returns a filtered Iterator over the documents.
   *
   * @return \Iterator
   *   An iterator over the documents providing source rows that match the
   *   configured elementQuery.
   */
  protected function initializeIterator() {
    $iterator_class = $this->iteratorClass();
    $iterator = new $iterator_class($this);

    return $iterator;
  }

  /**
   * Lists the namespaces found in the source document(s).
   */
  public function namespaces() {
    return [];
  }

}
