<?php

namespace php_unused_functions;

include __DIR__ . '/vendor/autoload.php';

class _Function
{
    public string $name;

    public string $file_name;
    public int $start_line;
    public int $end_line;
    public int $params_count;

    public function __construct($name, $file_name, $start_line, $end_line, $params_count) {
        $this->name = $name;
        $this->file_name = $file_name;
        $this->start_line = $start_line;
        $this->end_line = $end_line;
        $this->params_count = $params_count;
    }
}