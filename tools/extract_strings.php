<?php
require 'vendor/autoload.php';

$translations = array();

function format_xml(&$simpleXmlObject)
{

    if (!is_object($simpleXmlObject)) {
        return "";
    }
    //Format XML to save indented tree rather than one line
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($simpleXmlObject->asXML());

    return $dom->saveXML();
}

class MyNodeVisitor extends PHPParser_NodeVisitorAbstract
{
    public function enterNode(PHPParser_Node $node)
    {
        global $translations;
        if ($node instanceof PHPParser_Node_Expr_MethodCall) {
            if ($node->name == 'trans') {
                $translations[] = $node->args[0]->value->value;
            }
        }
    }
}

$file = file_get_contents('index.php');

$parser = new PHPParser_Parser(new PHPParser_Lexer);
$traverser = new PHPParser_NodeTraverser;
$traverser->addVisitor(new MyNodeVisitor);

try {
    $stmts = $parser->parse($file);
    $stmts = $traverser->traverse($stmts);
} catch (PHPParser_Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}

foreach (glob('templates/*.twig') as $file) {
    $content = file_get_contents($file);
    preg_match_all('/{% trans %}(.*){% endtrans %}/', $content, $matches);
    $translations = array_merge($translations, $matches[1]);
}

$translations = array_unique($translations);

/*$xml = new SimpleXMLElement('');*/

$xml = <<<EOF
<?xml version='1.0' standalone='yes'?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:schemaLocation="urn:oasis:names:tc:xliff:document:1.2
       xliff-core-1.2-transitional.xsd"
       version="1.2">
 <file source-language="de" datatype="plaintext" original="file.ext">
  <body>
  </body>
  </file>
  </xliff>
EOF;

$xml = new SimpleXMLElement($xml);

//print_r($xml);

$id = 1;

foreach ($translations as $translation) {
    /** @var SimpleXMLElement $element */
    $element = $xml->file->body;
    $element = $element->addChild('trans-unit');
    $element->addAttribute('id', $id);
    $id++;
    $element->addChild('source', $translation);
}


print_r(format_xml($xml));