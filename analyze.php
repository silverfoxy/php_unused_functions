<?php

namespace php_unused_functions;

include __DIR__ . "/vendor/autoload.php";

use Dariuszp\CliProgressBar;
use Garden\Cli\Cli;
use Phpparser\Error;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/*
* Helper functions to get local www directories
*/
function getDirContents($dir, &$results = array()){
    $files = scandir($dir);

    foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if(!is_dir($path)) {
            $results[] = $path;
        } else if($value != "." && $value != "..") {
            getDirContents($path, $results);
        }
    }

    return $results;
}
function getDirs($dir, &$results = array()){
    $files = scandir($dir);

    foreach($files as $key => $value){
        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
        if(is_dir($path) && $value != "." && $value != "..") {
            $results[] = $path;
        }
    }

    return $results;
}

// Parse CLI Args
$cli = new Cli();
$cli->description('Identify unused functions')
    ->opt('dir:d', 'Target web application directory', true);
$args = $cli->parse($argv, true);

$dirname = $args->getOpt('dir');
$files = getDirContents($dirname);

$progress_bar = new CliProgressBar(count($files));
$progress_bar->setDetails('Starting the first analysis pass.');
$progress_bar->display();

$mappings = [];

$serialized_function_mappings_file_name = 'function_mappings.ser';
if (file_exists($serialized_function_mappings_file_name)) {
    $mappings = unserialize(file_get_contents($serialized_function_mappings_file_name), [FunctionVisitor::class, _Function::class, _Method::class]);
}
else {
    foreach ($files as $file_name) {
        $progress_bar->setDetails("Analyzing ({$file_name}).");
        $progress_bar->progress();
        if (isset(pathinfo($file_name)['extension']) && pathinfo($file_name)['extension'] == 'php') {
            // Parse it
            $php_file_content = file_get_contents($file_name);
            $traverser = new NodeTraverser();
            $function_visitor = new FunctionVisitor($file_name);
            $traverser->addVisitor($function_visitor);
            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            try {
                $ast = $parser->parse($php_file_content);
            } catch (\Error $error) {
                echo "Parse error at ({$file_name}): {$error->getMessage()}" . PHP_EOL;
                continue;
            }

            $traverser->traverse($ast);
            $mappings[$file_name] = $function_visitor;

            // $new_ast = $traverser->traverse($ast);
            //
            // $printer = new Standard();
            // $new_code = $printer->prettyPrintFile($new_ast);
            //
            // // Write new file to disk
            // try {
            //     $handle = fopen($file_name, 'w');
            //     fwrite($handle, $new_code);
            //     fclose($handle);
            // }
            // catch(Exception $e){
            //     echo "Failed to write new code to ({$file_name})" . PHP_EOL;
            // }
        }
        $progress_bar->end();
    }
    // Serialize results
    file_put_contents('function_mappings.ser', serialize($mappings));
}

// Second pass for function call analysis
$progress_bar->setProgressTo(0);
$progress_bar->setDetails('Starting the second analysis pass.');
$progress_bar->display();

$callbacks = [];

foreach ($files as $file_name) {
    $progress_bar->setDetails("Analyzing ({$file_name}).");
    $progress_bar->progress();
    if (isset(pathinfo($file_name)['extension']) && pathinfo($file_name)['extension'] == 'php') {
        // Parse it
        $php_file_content = file_get_contents($file_name);
        $traverser = new NodeTraverser();
        $function_call_visitor = new FunctionCallVisitor($file_name);
        $traverser->addVisitor($function_call_visitor);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        try {
            $ast = $parser->parse($php_file_content);
        }
        catch(\Error $error) {
            echo "Parse error at ({$file_name}): {$error->getMessage()}" . PHP_EOL;
            continue;
        }

        $traverser->traverse($ast);

        $callbacks[$file_name] = $function_call_visitor;

        // $new_ast = $traverser->traverse($ast);
        //
        // $printer = new Standard();
        // $new_code = $printer->prettyPrintFile($new_ast);
        //
        // // Write new file to disk
        // try {
        //     $handle = fopen($file_name, 'w');
        //     fwrite($handle, $new_code);
        //     fclose($handle);
        // }
        // catch(Exception $e){
        //     echo "Failed to write new code to ({$file_name})" . PHP_EOL;
        // }
    }
    $progress_bar->end();
}

$a = $callbacks;

$actions = [];
$filters = [];
$misc_callbacks = [];
$invoked_actions = [];
$invoked_filters = [];

foreach($callbacks as $function_call_visitor) {
    if (count($function_call_visitor->action_tags) > 0) {
        // Get callbacks
        foreach($function_call_visitor->action_tags as $tag => $values) {
            foreach($function_call_visitor->action_tags[$tag] as $action_callback) {
                if (!array_key_exists($tag, $actions) || !in_array($action_callback, $actions[$tag])) {
                    $actions[$tag][] = $action_callback;
                }
            }
        }
    }
    if (count($function_call_visitor->filter_tags) > 0) {
        // Get callbacks
        foreach($function_call_visitor->filter_tags as $tag => $values) {
            foreach($function_call_visitor->filter_tags[$tag] as $filter_callback) {
                if (!array_key_exists($tag, $filters) || !in_array($filter_callback, $filters[$tag])) {
                    $filters[$tag][] = $filter_callback;
                }
            }
        }
    }
    if (count($function_call_visitor->other_callbacks) > 0) {
        // Get callbacks
        foreach($function_call_visitor->other_callbacks as $misc_callback) {
            if (!in_array($misc_callback, $misc_callbacks)) {
                $misc_callbacks[] = $misc_callback;
            }
        }
    }
    // Check for invoked actions and filters
    if (count($function_call_visitor->invoked_actions) > 0) {
        foreach ($function_call_visitor->invoked_actions as $invoked_action) {
            if (!in_array($invoked_action, $invoked_actions)) {
                $invoked_actions[] = $invoked_action;
            }
        }
    }
    if (count($function_call_visitor->invoked_filters) > 0) {
        foreach ($function_call_visitor->invoked_filters as $invoked_filter) {
            if (!in_array($invoked_filter, $invoked_actions)) {
                $invoked_filters[] = $invoked_filter;
            }
        }
    }
}

$covered_functions = [];
foreach ($invoked_actions as $invoked_action) {
    $invoked_callbacks = $actions[$invoked_action];
    if ($invoked_callbacks === null) {
        continue;
    }
    foreach ($invoked_callbacks as $callback) {
        if (!in_array($callback, $covered_functions)) {
            $covered_functions[] = $callback;
        }
    }
}
foreach ($invoked_filters as $invoked_filter) {
    $invoked_callbacks = $filters[$invoked_filter];
    foreach ($invoked_callbacks as $callback) {
        if (!in_array($callback, $covered_functions)) {
            $covered_functions[] = $callback;
        }
    }
}
foreach ($misc_callbacks as $callback) {
    if (!in_array($callback, $covered_functions)) {
        $covered_functions[] = $callback;
    }
}

$a = $covered_functions;