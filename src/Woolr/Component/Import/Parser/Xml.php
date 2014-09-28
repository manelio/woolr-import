<?php
namespace Woolr\Component\Import\Parser;

use Woolr\Component\Import\Parser\Xml\Context;

class Xml
{
  const ELEMENT_STATUS_CLOSED = 0;
  const ELEMENT_STATUS_OPENED = 1;

  protected $xmlParser = null;
  protected $stream = null;

  protected $cdata = '';
  protected $indent = 0;

  protected $pathInfoStack = array();
  protected $context = null;
  protected $contextStack = array();

  public function __construct()
  {
    $this->xmlParser = xml_parser_create('UTF-8');
    xml_parser_set_option($this->xmlParser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
    xml_parser_set_option($this->xmlParser, XML_OPTION_SKIP_WHITE, 1);
    xml_parser_set_option($this->xmlParser, XML_OPTION_CASE_FOLDING, 0);

    xml_set_object($this->xmlParser, $this);

    xml_set_element_handler($this->xmlParser, 'readElement', 'readEndElement');
    xml_set_character_data_handler($this->xmlParser, "readCdata");

    $this->context = new Context('root');
    $this->contextStack[] = $this->context;
  }

  public function setContext($context)
  {
    $this->context = $context;
  }

  public function parse($source = null, $return = false)
  {
    $xmlParser = $this->xmlParser;
    
    $this->size = filesize($source);
    $stream = fopen($source, "rt");

    while($data = fread($stream, 65536)) {      
      if (!xml_parse($xmlParser, $data, feof($stream))) {
        die(sprintf("XML error: %s at line %d",
          xml_error_string(xml_get_error_code($xmlParser)),
          xml_get_current_line_number($xmlParser)));
      }
    }
  }

  protected function readElement($xmlParser, $elementName, $attrs)
  {
    $elementName = strtolower($elementName);
    $progress = sprintf("%05.2f", $this->getProgress());

    $this->currentElementStatus = self::ELEMENT_STATUS_OPENED;
    $this->cdata = '';

    $pathInfo = end($this->pathInfoStack);
    unset($pathInfo['op']);

    $path = "{$pathInfo['path']}/$elementName";
    $pathInfo['path'] = $path;
    
    if (is_object($context = $this->context->openTag($elementName, $attrs))) {
      $this->contextStack[] = $this->context;
      $this->context = $context;
      $pathInfo['op'] = 'context';
    }    
    $this->pathInfoStack[] = $pathInfo;
  }

  protected function readEndElement($xmlParser, $elementName)
  {
    $elementName = strtolower($elementName);
    $progress = sprintf("%05.2f", $this->getProgress());

    $pathInfo = array_pop($this->pathInfoStack);
    $path = $pathInfo['path'];

    // is a leaf
    if ($this->currentElementStatus == self::ELEMENT_STATUS_OPENED)
      $this->context->text($this->cdata);

    $context = null;
    if (key_exists('op', $pathInfo)) {
      $op = $pathInfo['op'];
      if ($op == 'context') {
        $context = $this->context;
        $this->context->finish();
        $this->context = array_pop($this->contextStack);
      }
    }

    $this->context->closeTag($elementName, $context);

    $this->currentElementStatus = self::ELEMENT_STATUS_CLOSED;
    $this->cdata = '';
  }

  protected function readCdata($xmlParser, $cdata)
  {
    $this->cdata .= $cdata;
  }

  protected function getProgress()
  {
    $i = xml_get_current_byte_index($this->xmlParser);
    $percent = ($i*100)/$this->size;
    return $percent;
  }

}