<?php
require __DIR__.'/autoload.php';

$urls = require __DIR__.'/data/urls.php';
$tmpDir = __DIR__.'/tmp';
if (!file_exists($tmpDir)) {
	mkdir($tmpDir, 0775, true);
}
try {
	run($urls, $tmpDir);
} catch (XmlIsInvalid $e) {
	echo $e->getFormattedErrors();
	exit(1);
}


function run(array $urls, string $tmpDir) {
	foreach ($urls as $url) {
		echo '==> Converting ', $url, "\n";
		$htmlFile = fetchUrl($url, $tmpDir);
		echo "    File stored in {$htmlFile}\n";
		$sfb = test($htmlFile, $tmpDir);
		file_put_contents($htmlFile.'.sfb', $sfb);
	}
}

function fetchUrl(string $url, string $tmpDir) {
	$tmpHtmlFile = $tmpDir.'/'.basename($url).'.html';
	if (file_exists($tmpHtmlFile)) {
		return $tmpHtmlFile;
	}
	$html = file_get_contents($url);
	file_put_contents($tmpHtmlFile, $html);
	return $tmpHtmlFile;
}

function test(string $htmlFile, string $tmpDir) {
	$xml = (new HtmlToXmlConverter())->convert(file_get_contents($htmlFile));
	$sfb = (new XmlToSfbConverter($tmpDir))->convert($xml);
	return $sfb;
}
