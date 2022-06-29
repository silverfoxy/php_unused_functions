<?php

namespace php_unused_functions;

include __DIR__ . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

class FunctionVisitor extends NodeVisitorAbstract
{
    public int $verbosity = 0;
    public string $current_file;
    public ?string $current_class = null;
    public ?string $current_namespace = null;

    public array $methods = [];
    public array $functions = [];
    public array $action_tags = [];
    public array $filter_tags = [];

    public function __construct(string $current_file) {
        $this->current_file = $current_file;
    }

    protected function getName(Node $node) {
        if (is_string($node->name)) {
            return $node->name;
        }
        elseif (isset($node->parts)) {
            return implode('\\', $node->parts);
        }
        else {
            return $this->getName($node->name);
        }
    }

    public function enterNode(Node $node) {
        if ($node instanceof Namespace_) {
            // get namespace name and up date
            $this->current_namespace = $this->getName($node);
        }
        elseif ($node instanceof Class_ || $node instanceof Node\Stmt\Interface_) {
            // get name of the class
            $this->current_class = $this->getName($node);
        }
        elseif ($node instanceof Function_) {
            // extract name
            $name = $this->getName($node);
            $params_count = count($node->params);
            $ns_function_name = "{$this->current_namespace}\\$name({$params_count})";
            if (array_key_exists($ns_function_name, $this->functions)) {
                if ($this->verbosity > 0) {
                    echo "Function name ({$ns_function_name}} already exists".PHP_EOL;
                }
                return;
            }
            $this->functions[$ns_function_name] = new _Function($name, $this->current_file, $node->getStartLine(), $node->getEndLine(), $params_count);
            // // check for special functions
            // if (in_array($name, ['add_action', 'add_filter', '_deprecated_function'])) {
            //     $a = 1;
            // }
            // elseif (in_array($name, ['do_action', 'apply_filter'])) {
            //     $a = 2;
            // }
        }
        elseif ($node instanceof ClassMethod) {
            // extract name
            $name = $this->getName($node);
            $params_count = count($node->params);
            $ns_class_method_name = "{$this->current_namespace}\\{$this->current_class}::{$name}({$params_count})";
            if (array_key_exists($ns_class_method_name, $this->methods)) {
                if ($this->verbosity > 0) {
                    echo "Method name ({$ns_class_method_name}} already exists" . PHP_EOL;
                }
                return;
            }
            $this->methods[$ns_class_method_name] = new _Method($this->current_class, $name, $this->current_file, $node->getStartLine(), $node->getEndLine(), $params_count, $this->current_namespace);
        }
    }
}