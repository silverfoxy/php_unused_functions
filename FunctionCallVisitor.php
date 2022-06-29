<?php

namespace php_unused_functions;

include __DIR__ . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

class FunctionCallVisitor extends NodeVisitorAbstract
{
    public int $verbosity = 0;
    public string $current_file;
    public ?string $current_class = null;
    public ?string $current_namespace = null;

    public array $methods = [];
    public array $functions = [];
    public array $action_tags = [];
    public array $filter_tags = [];
    public array $other_callbacks = [];
    public array $invoked_actions = [];
    public array $invoked_filters = [];

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

    protected function resolveArg(Node\Arg $arg) {
        if (isset($arg->value) && $arg->value instanceof Node\Scalar) {
            return $arg->value->value;
        }
        else {
            // Dynamic type
            return 'DynamicType';
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
        elseif ($node instanceof Node\Expr\FuncCall) {
            // extract name
            $name = $this->getName($node);
            $params_count = count($node->args);
            $ns_function_name = "{$this->current_namespace}\\$name({$params_count})";
            // check for special functions
            if (in_array($name, ['add_action', 'add_filter', '_deprecated_function'])) {
                $tag = $this->resolveArg($node->args[0]);
                $callback = $this->resolveArg($node->args[1]);
                if ($tag === 'DynamicType' || $callback === 'DynamicType') {
                    $a = 'whelp';
                    // Skip dynamic callbacks
                    return;
                }
                switch ($name) {
                    case 'add_action':
                        $this->action_tags[$tag][] = $callback;
                        break;
                    case 'add_filter':
                        $this->filter_tags[$tag][] = $callback;
                        break;
                    case '_deprecated_function':
                        $this->other_callbacks[] = $callback;
                        break;
                }

            }
            elseif (in_array($name, ['do_action', 'apply_filter'])) {
                $tag = $this->resolveArg($node->args[0]);
                if ($tag === 'DynamicType' || is_null($tag)) {
                    // Dynamic tags are not supported
                    return;
                }
                switch ($name) {
                    case 'do_action':
                        if (!in_array($this->invoked_actions, $tag)) {
                            $this->invoked_actions[] = $tag;
                        }
                        break;
                    case 'apply_filter':
                        if (!in_array($this->invoked_filters, $tag)) {
                            $this->invoked_filters[] = $tag;
                        }
                        break;
                }
            }
        }
    }
}