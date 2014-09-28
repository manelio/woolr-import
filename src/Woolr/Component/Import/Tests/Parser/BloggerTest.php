<?php
namespace Woolr\Component\Import\Tests\Parser;

use Woolr\Component\Import\Parser\Blogger;
use Woolr\Component\Import\Parser\Xml;

class BloggerTest extends \PHPUnit_Framework_TestCase
{
  protected static $fixturesPath;

  public static function setUpBeforeClass()
  {
    self::$fixturesPath = __DIR__.'/../Fixtures/';
  }

  public function testParsePost()
  {
    $blogger = new Blogger();
    $xml = new Xml();

    $blogger->setXmlParser($xml);

    $uri =self::$fixturesPath.'blogger-post.xml';
    $post = $blogger->parse($uri);
    $this->assertEquals(1, 1);
  }

  /**
   * xdataProvider getParsePostTests
   */
  /*
  public function testParsePost($stream)
  {
    $blogger = new Blogger();

    $blogger->parse(self::$fixturesPath.'/blogger-post.xml');

    $this->assertEquals(1, 1);
  }

  public function getParsePostTests()
  {
    return array(
      array(
        
      ),
    );
  }
  */
}