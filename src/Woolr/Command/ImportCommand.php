<?php
namespace Woolr\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Sunra\PhpSimple\HtmlDomParser;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ImportCommand extends Command {

  protected function configure()
  {   
    $this->setName("woolr:import")
    ->setDescription("Import a wordpress export file")
    ->addArgument(
        'source',
        InputArgument::IS_ARRAY,
        'Source data.',
        array('php://stdin')
    )
    ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Format (wordpress, blogger)', 'wordpress')
    ->addOption('media', 'm', InputOption::VALUE_OPTIONAL, 'Directory to store the media content')
    ;
  }


  protected function getElementCode($reader) {
    $prefix = $reader->prefix;
    if (in_array($prefix, array('wp'))) {
      $prefix = '';
    }
    $localName = $reader->localName;
    if ($prefix) return "{$prefix}__{$localName}";
    return $localName;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {

    $mediaDir = $input->getOption('media');
    if ($mediaDir) {
      $mediaFile = $mediaDir."/"."media.log";
      $this->mediaFileHandler = fopen($mediaFile, "wt");
    }


    $format = $input->getOption('format');

    $this->db = new \PDO('mysql:host=localhost;dbname=memocracia;charset=utf8', 'dev', 'dev');
    $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
    
    $headerStyle = new OutputFormatterStyle('white', 'black', array('bold'));
    $output->getFormatter()->setStyle('header', $headerStyle);    

    switch($format) {
      case 'wordpress';
        $this->executeWorpress($input, $output);
        break;
      case 'blogger';
        $this->executeBlogger($input, $output);
        break;
    }

    fclose($this->mediaFileHandler);

    // Sequentially parse a wordpress export xml file. SAX, non-recursive.
    // Because there can be a lot of comments in a post, and the memory matters, use
    // a little hack to process post data before the comments.

    // throw new \InvalidArgumentException('err!');
  }

  protected function executeWorpress($input, $output) {
    $source = reset($input->getArgument('source'));

    $reader = new \XMLReader();
    $reader->open($source);

    $currentObject = new \StdClass;
    $currentObject->element = null;

    $objectStack = array();
    $currentElementStatus = null;
    $currentValue = '';

    while ($reader->read()) {
      switch ($reader->nodeType) {
        case \XMLReader::ELEMENT:
          $elementCode = $this->getElementCode($reader);
          $currentElementStatus = 'opened';

          // HACK to process the post before the comments
          if ($elementCode == 'comment') {
            if ($currentObject->element == 'item') {              
              $this->process($currentObject);
              
              array_pop($objectStack);
              $currentObject = new \StdClass;
              $currentObject->element = 'dummy';
              $objectStack[] = $currentObject;
            }
          }

          if (!$reader->isEmptyElement) {
            $currentObject = new \StdClass;
            $currentObject->element = $elementCode;
            $objectStack[] = $currentObject;
          } else {
            $currentElementStatus = 'closed';
          }
        break;

        case \XMLReader::END_ELEMENT:
          $elementCode = $this->getElementCode($reader);
          
          // Last element is a leaf
          if ($currentElementStatus == 'opened') {
            $currentValue = trim($currentValue);
            array_pop($objectStack);
            $currentObject = end($objectStack);
            $this->addElementToObject($elementCode, $currentValue, $currentObject);
            $currentValue = '';
          } else {            
            $currentObject = array_pop($objectStack);

            $parentObject = end($objectStack);
            
            if (count($objectStack) > 1)
                $this->addElementToObject($elementCode, $currentObject, $parentObject);
            
            $this->process($currentObject);
          }
          $currentElementStatus = 'closed';
        break;

        case \XMLReader::TEXT:
        case \XMLReader::CDATA:
          $currentValue .= $reader->value;
        break;
      }
    }    
  }

  protected function executeBlogger($input, $output) {
    $sources = $input->getArgument('source');
    $source = reset($sources);

    $reader = new \XMLReader();
    $reader->open($source);


    $currentObject = new \StdClass;
    $currentObject->element = null;

    $objectStack = array();
    $currentElementStatus = null;
    $currentValue = '';

    $indent = 0;

    while ($reader->read()) {

      //echo "{$reader->nodeType}: {$reader->localName}\n";

      switch ($reader->nodeType) {
        case \XMLReader::ELEMENT:
          $elementCode = $this->getElementCode($reader);
          $currentElementStatus = 'opened';

          $parentObject = end($objectStack);

          if (!$reader->isEmptyElement) {
            $currentObject = new \StdClass;
            $currentObject->element = $elementCode;  
            $objectStack[] = $currentObject;
          } else {            
            $currentObject = new \StdClass;

            $this->addElementToObject($elementCode, $currentObject, $parentObject);
            $currentElementStatus = 'closed';
          }

          if($reader->hasAttributes)  {
            $attr = array();
            while($reader->moveToNextAttribute()) { 
              $attr[$reader->name] = $reader->value;              
            }
            $currentObject->attr = $attr;
         
            if ($elementCode == 'category') {
              if (strpos($attr['scheme'], '#kind') !== false) {
                $kind = explode('#', $attr['term']);
                $kind = end($kind);

                $parentObject->kind = $kind;
              } else {
                $parentObject->categories[] = $attr['term'];
              }
            }
          }

        break;

        case \XMLReader::END_ELEMENT:
          $elementCode = $this->getElementCode($reader);
          
          // Last element is a leaf
          if ($currentElementStatus == 'opened') {
            $currentValue = trim($currentValue);
            array_pop($objectStack);
            $currentObject = end($objectStack);

            $this->addElementToObject($elementCode, $currentValue, $currentObject);

            $currentValue = '';
          } else {            
            $currentObject = array_pop($objectStack);

            $parentObject = end($objectStack);
            
            if (count($objectStack) > 1)
                $this->addElementToObject($elementCode, $currentObject, $parentObject);
            
            $preparedObject = $this->prepareBlogger($currentObject);
            $this->process($preparedObject);
          }
          $currentElementStatus = 'closed';
        break;

        case \XMLReader::TEXT:
        case \XMLReader::CDATA:
          $currentValue .= $reader->value;
        break;
      }
    }    
  }

  function prepareBlogger($object) {
    $result = new \StdClass;

    $kind = false;
    if (key_exists('kind', $object)) $kind = $object->kind;

    switch ($kind) {
      case 'post':
        $result = $this->preparePost($object);
        break;
      
      case 'comment':
        $result = $this->prepareComment($object);
        break;

      default:
        return false;
    }

    return $result;
  }

  protected function preparePost($object) {

    //print_r($object);

    $result = new \StdClass;
    $result->externalId = $object->id;
    $result->title = $object->title;

    $content = $object->content;
    $dom = HtmlDomParser::str_get_html($content);
    $imgs = $dom->find('img');
    foreach($imgs as $img) {      
      $this->prepareImage($img->src);
    }

    $result->content = substr($object->content, 0, 100);
    
    if (key_exists('categories', $object)) 
      $result->categories = $object->categories;
    else
      $result->categories = array();

    return $object;
  }

  protected function prepareComment($object) {
    print_r($object);

    $result = new \StdClass;
    $result->externalId = $object->id;
    $result->content = $object->content;

    print_r($result);

    return $object;
  }

  protected function prepareImage($img) {
    fputs($this->mediaFileHandler, $img."\n");
  }

  protected function addElementToObject($code, $value, $object) {
    if (key_exists($code, $object)) {
      $codeMul = $code."Mul";

      if (!key_exists($codeMul, $object))
        $object->$codeMul = array($object->$code);
      array_push($object->$codeMul, $value);
    } else {
      $object->$code = $value;
    }
  }


  protected function process($object)
  {
    if (empty($object)) return;
   
    //print_r($object);

    return;


    switch($object->element) {
      case 'item':
        $postType = $object->post_type;
        switch($postType) {
          case 'post':
            echo "\n".'[ii] post: '.$object->title."\n";

            $stmt = $this->db->prepare(<<<EOT
INSERT INTO links (
  link_author,
  link_blog,
  link_status,
  link_date,
  link_uri,
  link_title,
  link_content,
  link_votes
) VALUES (
  ?, ?, ?, ?, ?, ?, ?, ?
)
EOT
);
            $stmt->execute(array(
              2,
              0,
              'queued',
              $object->post_date,
              $object->post_name,
              $object->title,
              $object->content__encoded,
              mt_rand(1, 100),
            ));

            $linkId = $this->db->lastInsertId();
  
            $stmt = $this->db->prepare(<<<EOT
INSERT INTO sub_statuses (
  id,
  status,
  date,
  category,
  link,
  origen
) VALUES (
  ?, ?, ?, ?, ?, ?
)
EOT
);

            $stmt->execute(array(
              2,
              'queued',
              $object->post_date,
              0,
              $linkId,
              2,
            ));


            $stmt = $this->db->prepare(<<<EOT
INSERT INTO link_clicks (
  id,
  counter
) VALUES (
  ?, ?
)
EOT
);

            $stmt->execute(array(
              $linkId,
              mt_rand(1, 10000),
            ));

            break;
        }
      break;
      
      case 'comment':
        echo '[ii] comment by '.$object->comment_author."\n";
      break;

    }

  }

}
