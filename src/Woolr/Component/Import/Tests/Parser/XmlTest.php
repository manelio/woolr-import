<?php
namespace Woolr\Component\Import\Tests\Parser;

use Woolr\Component\Import\Parser\Xml;

class XmlTest extends \PHPUnit_Framework_TestCase
{
  protected static $fixturesPath;

  public static function setUpBeforeClass()
  {
    self::$fixturesPath = __DIR__.'/../Fixtures/';
  }

  /**
   * @dataProvider getParseXmlTests
   */
  public function testParseFile($xmlFile, $jsonFile)
  {
    $xml = new Xml();

    $xmlFile = self::$fixturesPath.'xml/'.$xmlFile;
    $jsonFile = self::$fixturesPath.'xml/'.$jsonFile;

    $parsed = $xml->parse($xmlFile);    
    $expected = json_decode(file_get_contents($jsonFile));


    $this->assertEquals($parsed, $expected);
  }

  public function getParseXmlTests()
  {

    return array(
      array(
        'xml' => 'only-root.xml',
        'json' => 'only-root.json',
      ),
      array(
        'xml' => 'one-tag.xml',
        'json' => 'one-tag.json',
      ),
      array(
        'xml' => 'two-equal-tags.xml',
        'json' => 'two-equal-tags.json',
      ),      
      array(
        'xml' => 'one-tag-with-attributes.xml',
        'json' => 'one-tag-with-attributes.json',
      ),      
    );
    
  }
  
}