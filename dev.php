<?php
require __DIR__.'/autoload.php';

$urls = [
	'https://www.lex.bg/bg/laws/ldoc/521957377',
	'https://www.lex.bg/bg/laws/ldoc/2135521015',
	'https://www.lex.bg/bg/laws/ldoc/2135558368',
	'https://www.lex.bg/bg/laws/ldoc/2135514513',
	'https://www.lex.bg/bg/laws/ldoc/2136291652',
	'https://www.lex.bg/bg/laws/ldoc/2135507578',
	'https://www.lex.bg/bg/laws/ldoc/2137190515',
	'https://www.lex.bg/bg/laws/ldoc/2135951391',
	'https://www.lex.bg/bg/laws/ldoc/2136112596',
	'https://www.lex.bg/bg/laws/ldoc/2137192587',
	'https://www.lex.bg/bg/laws/ldoc/2135951392',
	'https://www.lex.bg/bg/laws/ldoc/2136717797',
	'https://www.lex.bg/bg/laws/ldoc/2137201712',
	'https://www.lex.bg/bg/laws/ldoc/1598070784',
	'https://www.lex.bg/bg/laws/ldoc/2135896489',
	'https://www.lex.bg/bg/laws/ldoc/2135896487',
	'https://www.lex.bg/bg/laws/ldoc/2136548777',
	'https://www.lex.bg/bg/laws/ldoc/1597824512',
	'https://www.lex.bg/bg/laws/ldoc/2135503651',
	'https://www.lex.bg/bg/laws/ldoc/1594373121',
	'https://www.lex.bg/bg/laws/ldoc/1590193665',
	'https://www.lex.bg/bg/laws/ldoc/1589654529',
	'https://www.lex.bg/bg/laws/ldoc/2135512224',
	'https://www.lex.bg/bg/laws/ldoc/2135637484',
];
$tmpDir = __DIR__.'/tmp';

if (!file_exists($tmpDir)) {
	mkdir($tmpDir, 0775, true);
}

foreach ($urls as $url) {
	echo '==> Converting ', $url, "\n";
	$htmlFile = fetchUrl($url, $tmpDir);
	echo "    File stored in {$htmlFile}\n";
	test($htmlFile, $tmpDir);
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
