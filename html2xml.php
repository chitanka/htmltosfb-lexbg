<?php
if (!isset($argv[1])) {
	echo <<<USAGE
php $argv[0] <FILE.html>
USAGE;
	exit(1);
}
require __DIR__.'/autoload.php';
echo (new \Chitanka\Converter\Lexbg\HtmlToXmlConverter())->convert(file_get_contents($argv[1]));
