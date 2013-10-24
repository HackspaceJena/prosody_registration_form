<?php
require 'vendor/autoload.php';

class MyNodeVisitor extends PHPParser_NodeVisitorAbstract
{
    public function enterNode(PHPParser_Node $node) {
        if ($node instanceof PHPParser_Node_Expr_MethodCall) {
            if ($node->name == 'trans') {
                echo $node->args[0]->value->value."\n";
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
