<?php
namespace Woolr\Component\Import\Parser\Xml;

class Context
{
  protected $name = null;
  protected $data = array();

  protected $indent = 0;

  protected $rootPath = "";
  protected $pathStack = array();
  protected $tagInfoStack = array();
  
  protected $parent = null;

  protected $handleInfoStack = array();

  protected $handles = array();

  public function __construct($name = null)
  {
    $this->name = $name;
    $this->context = $this;
  }
  
  public function openTag($tag, $attributes)
  {
    $path = end($this->pathStack);
    $path = "$path/$tag";

    //echo "$path\n";

    $this->pathStack[] = $path;
    $this->tagInfoStack[] = array(
      'tag' => $tag,
      'attributes' => $attributes,
    );
    
    $handleInfo = null;    
    if (key_exists($path, $this->handles)) {
      $handleInfo = $this->handles[$path];
      
      if (is_array($handleInfo)) {
        if (key_exists('handles', $handleInfo)) {
          $context = new Context($handleInfo['name']);
          $context->setHandles($handleInfo['handles']);
          $context->setRootPath($this->rootPath.$path);
          $context->setParent($this);
          $this->handleInfoStack[] = $handleInfo;
          return $context;
        }
      }

    }
    $this->handleInfoStack[] = $handleInfo;

    // do action ^^^
    $this->indent++;
  }

  public function closeTag($tag, $context = null)
  {   
    $this->indent--;
    // do action vvv

    $path = array_pop($this->pathStack);
    $tagInfo = array_pop($this->tagInfoStack);
    $tagInfo['text'] = $this->text;

    $handleInfo = array_pop($this->handleInfoStack);

    if ($handleInfo) {
      $this->processHandle($handleInfo, $tagInfo, $context);
    }
  }

  public function finish()
  {
    print_r($this->data);
  }

  public function text($text)
  {
    $this->text = $text;
  }

  public function setHandles($handles)
  {
    $this->handles = $handles;
  }

  public function getParent()
  {
    return $this->parent;
  }

  public function setParent($parent)
  {
    $this->parent = $parent;
  }

  public function setRootPath($path)
  {
    $this->rootPath = $path;
  }  

  public function getName()
  {
    return $this->name;
  }
  
  public function getData()
  {
    return $this->data;
  }

  public function addValue($tag, $value)
  {    
    $this->data[$tag] = $value;
  }

  public function addArrayValue($tag, $value)
  {
    if (!key_exists($tag, $this->data)) $this->data[$tag] = array();
    $this->data[$tag][] = $value;
  }

  public function addArrayKeyValue($tag, $key, $value)
  {
    if (!key_exists($tag, $this->data)) $this->data[$tag] = array();
    $this->data[$tag][$key] = $value;
  }

  protected function processHandle($handleInfo, $tagInfo, $context = null)
  {
    $tag = $tagInfo['tag'];
    $attributes = $tagInfo['attributes'];

    if (is_string($handleInfo)) {
      if ($handleInfo == 'string') {
        $this->data[$tag] = $this->text;
      }
    } else if (is_array($handleInfo)) {
      if (key_exists('handles', $handleInfo)) {
        // is a context
        $data = $context->getData();
        $multiple = $handleInfo['multiple'];

        if ($multiple) {
          if (!key_exists($tag, $this->data)) $this->data[$tag] = array();
          $this->data[$tag][] = $data;
        } else {
          $this->data[$tag] = $data;
        }
        
      }
    } else if (is_callable($handleInfo)) {
      call_user_func($handleInfo, $this, $tagInfo);
    }
  }
}