<?php
namespace Woolr\Component\Import\Parser;

class Xml
{
  const TAG_STATUS_CLOSED = 0;
  const TAG_STATUS_OPENED = 1;

  const DUMMY_TAG = '#dummy';

  protected $uri;
  protected $processed = array();

  protected $objectStack;  
  protected $currentObject;
  protected $currentTagStatus;
  protected $currentValue;

  protected $indent =  0;

  protected $changeProcessOrder = array();
  protected $handlers = array();

  public function __construct($config = array())
  {
    foreach($config as $key => $value) {
      $this->$key = $value;
    }

    $this->parsed = new \StdClass;

    $this->currentObject = null;    
    $this->objectStack = array();
    
    $this->currentTagStatus = null;
    $this->currentValue = null;
  }

  public function parse($uri, $return = false)
  {
    if (!$uri) $uri = $this->uri;
    
    $this->return = $return;

    $reader = new \XMLReader();
    $reader->open($uri);

    while ($reader->read()) {
      switch ($reader->nodeType) {
        case \XMLReader::ELEMENT:
          $this->readTag($reader);
        break;

        case \XMLReader::END_ELEMENT:
          $this->readEndTag($reader);
        break;

        case \XMLReader::TEXT:
          $this->readText($reader);
        break;

        case \XMLReader::CDATA:
          $this->readCdata($reader);
        break;
      }
    }

    return $this->processed;
  }

  public function addHackChangeProcessOrder($currentTag = 'item', $foundTag = 'comment')
  {
    $this->changeProcessOrder[] = array($currentTag, $foundTag);
  }

  public function addHandler($handler)
  {
    $this->handlers[] = $handler;
  }

  protected function readTag($reader)
  {    
    $tagCode = $this->getTagCode($reader);

    $this->indent++;

    $this->currentTagStatus = self::TAG_STATUS_OPENED;

    // Check for change process order hack
    foreach($this->changeProcessOrder as $pair) {
      $_currentTag = $pair[0];
      $_foundTag = $pair[1];
      
      $parentObject = end($this->objectStack);

      if ($tagCode == $_foundTag) {

        if ($parentObject->_tag == $_currentTag) {          
          $this->process($parentObject);
          array_pop($this->objectStack);

          $currentObject = new \StdClass;
          $currentObject->_tag = self::DUMMY_TAG;
          $this->objectStack[] = $currentObject;
        }
      }
    }

    $currentObject = new \StdClass;
    $currentObject->_tag = $tagCode;

    if ($reader->isEmptyElement) {      
      $this->addEntryToObject($tagCode, $currentObject, $parentObject);
      $this->currentTagStatus = self::TAG_STATUS_CLOSED;
      $this->indent--;
    } else {
      $this->objectStack[] = $currentObject;
    }

    $this->readAttributes($reader, $currentObject);
    $this->currentObject = $currentObject;
  }

  protected function readEndTag($reader)
  {
    $tagCode = $this->getTagCode($reader);

    $this->indent--;
    
    // Last element is a leaf
    if ($this->currentTagStatus == self::TAG_STATUS_OPENED) {
      $this->currentValue = trim($this->currentValue);
      $currentObject = array_pop($this->objectStack);
      $this->addEntryToObject('_value', $this->currentValue, $currentObject);
      $this->currentValue = '';
    } else {
      $currentObject = array_pop($this->objectStack);
      $this->process($currentObject);
    }

    $parentObject = end($this->objectStack);
      
    if (count($this->objectStack) > 1)
      $this->addEntryToObject($tagCode, $currentObject, $parentObject);
    else {      
    }

    $this->currentTagStatus = self::TAG_STATUS_CLOSED;
  }

  protected function readText($reader)
  {
    $this->currentValue .= $reader->value;
  }

  protected function readCdata($reader)
  {    
    $this->readText($reader);
  }

  protected function readAttributes($reader, $currentObject)
  {
    if($reader->hasAttributes)  {
      $attr = new \StdClass;
      while($reader->moveToNextAttribute()) { 
        $attr->{$reader->name} = $reader->value;
      }      
      $currentObject->_attr = $attr;
    }
  }

  protected function addEntryToObject($code, $value, $object) {
    if (key_exists($code, $object)) {
      $codeMul = $code."Mul";

      if (!key_exists($codeMul, $object))
        $object->$codeMul = array($object->$code);
      array_push($object->$codeMul, $value);
    } else {
      $object->$code = $value;
    }
  }  

  protected function getTagCode($reader) {
    $prefix = $reader->prefix;
    if (in_array($prefix, array('wp'))) {
      $prefix = '';
    }
    $localName = $reader->localName;
    if ($prefix) return "{$prefix}__{$localName}";
    return $localName;
  }

  protected function process($object)
  {
    if ($object->_tag == self::DUMMY_TAG) return;
    //if ($this->indent == 0) return;

    if ($this->return) $this->processed[] = $object;

    foreach($this->handlers as $handler) {
      $handler->process($object);
    }
  }

}