<?php
namespace Woolr\Component\Import\Parser;

class Blogger
{  
  protected $uri;
  protected $xmlParser;

  public function __construct($config = array())
  {
    foreach($config as $key => $value) {
      $this->$key = $value;
    }
  }

  public function setXmlParser($xmlParser)
  {
    $xmlParser->addHackChangeProcessOrder('item', 'comment');
    $xmlParser->addHandler($this);
    $this->xmlParser = $xmlParser;
  }

  public function parse($uri = null)
  {
    if (!$uri) $uri = $this->uri;

    $this->xmlParser->parse($uri);

    return array();
  }

  public function process($object)
  {
    if ($object->_tag != 'entry') return;
    
    echo "[ii] process item {$object->_tag}\n";
  }
}