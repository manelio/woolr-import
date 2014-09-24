<?php
namespace Woolr\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class ImportCommand extends Command {

  protected function configure()
  {   
    $this->setName("woolr:import")
    ->setDescription("Import a wordpress export file")
    ->setDefinition(array(
      new InputOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Wordpress export file'),
    ));
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

    $headerStyle = new OutputFormatterStyle('white', 'black', array('bold'));
    $output->getFormatter()->setStyle('header', $headerStyle);

    // Sequentially parse a wordpress export xml file. SAX, non-recursive.
    // Because there can be a lot of comments in a post, and the memory matters, use
    // a little hack to process post data before the comments.

    $file = $input->getOption('file');

    $reader = new \XMLReader();
    $reader->open($file);

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

          $currentObject = new \StdClass;
          $currentObject->element = $elementCode;
          $objectStack[] = $currentObject;          
        break;

        case \XMLReader::END_ELEMENT:
          $elementCode = $this->getElementCode($reader);
          
          // Last element is a leaf
          if ($currentElementStatus == 'opened') {
            $currentValue = trim($currentValue);
            array_pop($objectStack);
            $currentObject = end($objectStack);
            $currentObject->$elementCode = $currentValue;
            $currentValue = '';
          } else {
            $currentObject = array_pop($objectStack);
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
    // throw new \InvalidArgumentException('err!');   
  }

  protected function process($object)
  {
    switch($object->element) {
      case 'item':
        $postType = $object->post_type;
        switch($postType) {
          case 'post':
            echo "\n".'[ii] post: '.$object->title."\n";
            break;
        }
      break;
      
      case 'comment':
        echo '[ii] comment by '.$object->comment_author."\n";
      break;

    }

  }

}
