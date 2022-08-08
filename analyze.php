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
    ->opt('dir:d', 'Target web application directory', true)
    ->opt('cache:c', 'Reuse cache', false);
$args = $cli->parse($argv, true);

$dirname = $args->getOpt('dir');
$files = getDirContents($dirname);
$reuse_cache = $args->getOpt('cache');

$progress_bar = new CliProgressBar(count($files));
$progress_bar->setDetails('Starting the first analysis pass.');
$progress_bar->display();

$mappings = [];

$serialized_function_mappings_file_name = 'function_mappings.ser';
if (file_exists($serialized_function_mappings_file_name) && $reuse_cache) {
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
    if ($reuse_cache) {
        file_put_contents('function_mappings.ser', serialize($mappings));
    }
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