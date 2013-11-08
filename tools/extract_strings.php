<?php
require 'vendor/autoload.php';

$translations = array();

class MyNodeVisitor extends PHPParser_NodeVisitorAbstract
{
    public function enterNode(PHPParser_Node $node) {
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
$traverser     = new PHPParser_NodeTraverser;
$traverser->addVisitor(new MyNodeVisitor);

try {
    $stmts = $parser->parse($file);
    $stmts = $traverser->traverse($stmts);
} catch (PHPParser_Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}

foreach(glob('templates/*.twig') as $file) {
    $content = file_get_contents($file);
    preg_match_all('/{% trans %}(.*){% endtrans %}/',$content,$matches);
    $translations = array_merge($translations,$matches[1]);
}

$translations = array_unique($translations);

print_r($translations);