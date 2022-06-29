<?php

namespace php_unused_functions;

include __DIR__ . '/vendor/autoload.php';

class _Method
{
    public ?string $namespace;
    public string $class_name;
    public string $name;
    public int $params_count;

    public string $file_name;
    public int $start_line;
    public int $end_line;

    public function __construct($class_name, $name, $file_name, $start_line, $end_line, $params_count, $namespace = '') {
        $this->class_name = $class_name;
        $this->name = $name;
        $this->params_count = $params_count;
        $this->file_name = $file_name;
        $this->start_line = $start_line;
        $this->end_line = $end_line;
        $this->namespace = $namespace;
    }
}