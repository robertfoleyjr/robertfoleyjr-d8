<?php

/**
 * @file
 * Contains Drupal\migrate_source_xml\Plugin\migrate\source\MigrateXmlReader.
 */

namespace Drupal\migrate_source_xml\Plugin\migrate\source;

use Drupal\migrate\MigrateException;

/**
 * Makes an XMLReader object iterable over elements matching xpath-like syntax.
 */
class MigrateXmlReader implements \Iterator {

  /**
   * The XMLReader we are encapsulating.
   *
   * @var \XMLReader
   */
  public $reader;

  /**
   * URL of the source XML file.
   *
   * @var string
   */
  public $url;

  /**
   * Array of the element names from the query.
   *
   * 0-based from the first (root) element. For example, '//file/article' would
   * be stored as [0 => 'file', 1 => 'article'].
   *
   * @var array
   */
  protected $elementsToMatch = [];

  /**
   * An optional xpath predicate.
   *
   * Restricts the matching elements based on values in their children. Parsed
   * from the element query at construct time.
   *
   * @var string
   */
  protected $xpathPredicate = NULL;

  /**
   * Array representing the path to the current element as we traverse the XML.
   *
   * For example, if in an XML string like '<file><article>...</article></file>'
   * we are positioned within the article element, currentPath will be
   * [0 => 'file', 1 => 'article'].
   *
   * @var array
   */
  protected $currentPath = [];

  /**
   * Retains all elements with a given name to support extraction from parents.
   *
   * This is a hack to support field extraction of values in parents
   * of the 'context node' - ie, if $this->fields() has something like '..\nid'.
   * Since we are using a streaming xml processor, it is too late to snoop
   * around parent elements again once we've located an element of interest. So,
   * grab elements with matching names and their depths, and refer back to it
   * when building the source row.
   *
   * @var array
   */
  protected $parentXpathCache = [];

  /**
   * Hash of the element names that should be captured into $parentXpathCache.
   *
   * @var array
   */
  protected $parentElementsOfInterest = [];

  /**
   * Query string used to retrieve the elements from the XML file.
   *
   * @var string
   */
  public $elementQuery;

  /**
   * Xpath query string used to retrieve the primary key from each element.
   *
   * @var string
   */
  public $idQuery;

  /**
   * Current element object when iterating.
   *
   * @var \SimpleXMLElement
   */
  protected $currentElement = NULL;

  /**
   * Value of the ID for the current element when iterating.
   *
   * @var string
   */
  protected $currentId = NULL;

  /**
   * Element name matching mode.
   *
   * When matching element names, whether to compare to the namespace-prefixed
   * name, or the local name.
   *
   * @var bool
   */
  protected $prefixedName = FALSE;

  /**
   * Reference to the XmlBase source plugin we are serving as iterator for.
   *
   * @var XmlBase
   */
  protected $xmlSource;

  /**
   * Prepares our extensions to the XMLReader object.
   *
   * @param string $xml_url
   *   URL of the XML file to be parsed.
   * @param XmlBase $xml_source
   *   The xml source plugin.
   * @param string $element_query
   *   Query string in a restricted xpath format, for selecting elements to be.
   * @param string $id_query
   *   Query string to the unique identifier for an element,
   *   relative to the root of that element. This supports the full
   *   xpath syntax.
   * @param array $parent_elements_of_interest
   *   Named elements that should be preserved whenever they are encountered,
   *   so that they are available from getAncestorElement(). For efficiency, try
   *   to limit these to elements containing just text or small structures.
   */
  public function __construct($xml_url, XmlBase $xml_source, $element_query, $id_query, $parent_elements_of_interest = []) {
    $this->reader = new \XMLReader();
    $this->url = $xml_url;
    $this->elementQuery = $element_query;
    $this->idQuery = $id_query;
    $this->xmlSource = $xml_source;
    $this->parentElementsOfInterest = array_flip($parent_elements_of_interest);

    // Suppress errors during parsing, so we can pick them up after.
    libxml_use_internal_errors(TRUE);

    // Parse the element query. First capture group is the element path, second
    // (if present) is the attribute.
    preg_match_all('|^/([^\[]+)\[?(.*?)]?$|', $element_query, $matches);
    $element_path = $matches[1][0];
    $this->elementsToMatch = explode('/', $element_path);
    $predicate = $matches[2][0];
    if ($predicate) {
      $this->xpathPredicate = $predicate;
    }

    // If the element path contains any colons, it must be specifying
    // namespaces, so we need to compare using the prefixed element
    // name in next().
    if (strpos($element_path, ':')) {
      $this->prefixedName = TRUE;
    }
  }

  /**
   * Implementation of Iterator::rewind().
   */
  public function rewind() {
    // (Re)open the provided URL.
    $this->reader->close();
    $status = $this->reader->open($this->url, NULL, \LIBXML_NOWARNING);

    // Reset our path tracker.
    $this->currentPath = [];

    if ($status) {
      // Load the first matching element and its ID.
      $this->next();
    }
    else {
      throw new MigrateException(t('Could not open XML file @url',
        ['@url' => $this->url]), 'error');
    }
  }

  /**
   * Builds a \SimpleXmlElement rooted at the iterator's current location.
   *
   * The resulting SimpleXmlElement also contains any child nodes of the current
   * element.
   *
   * @return \SimpleXmlElement|false
   *   A \SimpleXmlElement when the document is parseable, or false if a
   *   parsing error occurred.
   */
  protected function getSimpleXml() {
    $node = $this->reader->expand();
    if ($node) {
      // We must associate the DOMNode with a
      // DOMDocument to be able to import
      // it into SimpleXML.
      // Despite appearances, this is almost twice as fast as
      // simplexml_load_string($this->readOuterXML());
      $dom = new \DOMDocument();
      $node = $dom->importNode($node, TRUE);
      $dom->appendChild($node);
      $sxml_elem = simplexml_import_dom($node);
      $this->registerNamespaces($sxml_elem);
      return $sxml_elem;
    }
    else {
      foreach (libxml_get_errors() as $error) {
        $error_string = self::parseLibXmlError($error);
        throw new MigrateException($error_string);
      }
      return FALSE;
    }
  }

  /**
   * Implementation of Iterator::next().
   */
  public function next() {
    $this->currentElement = $this->currentId = NULL;

    // Loop over each node in the XML file, looking for elements at a path
    // matching the input query string (represented in $this->elementsToMatch).
    while ($this->reader->read()) {
      if ($this->reader->nodeType == \XMLReader::ELEMENT) {
        if ($this->prefixedName) {
          $this->currentPath[$this->reader->depth] = $this->reader->name;
          if (array_key_exists($this->reader->name, $this->parentElementsOfInterest)) {
            $this->parentXpathCache[$this->reader->depth][$this->reader->name][] = $this->getSimpleXml();
          }
        }
        else {
          $this->currentPath[$this->reader->depth] = $this->reader->localName;
          if (array_key_exists($this->reader->localName, $this->parentElementsOfInterest)) {
            $this->parentXpathCache[$this->reader->depth][$this->reader->name][] = $this->getSimpleXml();
          }
        }
        if ($this->currentPath == $this->elementsToMatch) {
          // We're positioned to the right element path - build the SimpleXML
          // object to enable proper xpath predicate evaluation.
          $sxml_elem = $this->getSimpleXml();
          if ($sxml_elem !== FALSE) {
            if (empty($this->xpathPredicate) || $this->predicateMatches($sxml_elem)) {
              $this->currentElement = $sxml_elem;
              $idnode = $this->currentElement->xpath($this->idQuery);
              if (is_array($idnode)) {
                $this->currentId = (string) reset($idnode);
              }
              else {
                throw new \Exception(t('Failure retrieving ID, xpath: @xpath',
                  ['@xpath' => $this->idQuery]));
              }
              break;
            }
          }
        }
      }
      elseif ($this->reader->nodeType == \XMLReader::END_ELEMENT) {
        // Remove this element and any deeper ones from the current path.
        foreach ($this->currentPath as $depth => $name) {
          if ($depth >= $this->reader->depth) {
            unset($this->currentPath[$depth]);
          }
        }
        foreach ($this->parentXpathCache as $depth => $elements) {
          if ($depth > $this->reader->depth) {
            unset($this->parentXpathCache[$depth]);
          }
        }
      }
    }
  }

  /**
   * Tests whether the iterator's xpath predicate matches the provided element.
   *
   * Has some limitations esp. in that it is easy to write predicates that
   * reference things outside this SimpleXmlElement's tree, but "simpler"
   * predicates should work as expected.
   *
   * @param \SimpleXMLElement $elem
   *   The element to test.
   *
   * @return bool
   *   True if the element matches the predicate, false if not.
   */
  protected function predicateMatches(\SimpleXMLElement $elem) {
    return !empty($elem->xpath('/*[' . $this->xpathPredicate . ']'));
  }

  /**
   * Implementation of Iterator::current().
   *
   * @return \SimpleXMLElement|null
   *   Current item
   */
  public function current() {
    return $this->currentElement;
  }

  /**
   * Gets an ancestor SimpleXMLElement, if the element name was registered.
   *
   * Gets the SimpleXMLElement some number of levels above the iterator
   * having the given name, but only for element names that this
   * MigrateXmlReader was told to retain for future reference through the
   * constructor's $parent_elements_of_interest.
   *
   * @param int $levels_up
   *   The number of levels back towards the root of the DOM tree to ascend
   *   before searching for the named element.
   * @param string $name
   *   The name of the desired element.
   *
   * @return \SimpleXMLElement|false
   *   The element matching the level and name requirements, or false if it is
   *   not present or was not retained.
   */
  public function getAncestorElements($levels_up, $name) {
    if ($levels_up > 0) {
      $levels_up *= -1;
    }
    $ancestor_depth = $this->reader->depth + $levels_up + 1;
    if ($ancestor_depth < 0) {
      return FALSE;
    }

    if (array_key_exists($ancestor_depth, $this->parentXpathCache) && array_key_exists($name, $this->parentXpathCache[$ancestor_depth])) {
      return $this->parentXpathCache[$ancestor_depth][$name];
    }
    else {
      return FALSE;
    }
  }

  /**
   * Implementation of Iterator::key().
   *
   * @return null|string
   *   Current key
   */
  public function key() {
    return $this->currentId;
  }

  /**
   * Implementation of Iterator::valid().
   *
   * @return bool
   *   Indicates if current element is valid
   */
  public function valid() {
    return $this->currentElement instanceof \SimpleXMLElement;
  }

  /**
   * Registers the iterator's namespaces to a SimpleXMLElement.
   *
   * @param \SimpleXMLElement $xml
   *   The element to apply namespace registrations to.
   */
  protected function registerNamespaces(\SimpleXMLElement $xml) {
    foreach ($this->xmlSource->namespaces() as $prefix => $ns) {
      $xml->registerXPathNamespace($prefix, $ns);
    }
  }

  /**
   * Parses a LibXMLError to a error message string.
   *
   * @param \LibXMLError $error
   *   Error thrown by the XML.
   *
   * @return string
   *   Error message
   */
  public static function parseLibXmlError(\LibXMLError $error) {
    $error_code_name = 'Unknown Error';
    switch ($error->level) {
      case LIBXML_ERR_WARNING:
        $error_code_name = t('Warning');
        break;

      case LIBXML_ERR_ERROR:
        $error_code_name = t('Error');
        break;

      case LIBXML_ERR_FATAL:
        $error_code_name = t('Fatal Error');
        break;
    }

    return t(
      "@libxmlerrorcodename @libxmlerrorcode: @libxmlerrormessage\n" .
      "Line: @libxmlerrorline\n" .
      "Column: @libxmlerrorcolumn\n" .
      "File: @libxmlerrorfile",
      [
        '@libxmlerrorcodename' => $error_code_name,
        '@libxmlerrorcode' => $error->code,
        '@libxmlerrormessage' => trim($error->message),
        '@libxmlerrorline' => $error->line,
        '@libxmlerrorcolumn' => $error->column,
        '@libxmlerrorfile' => (($error->file)) ? $error->file : NULL,
      ]
    );
  }

}
