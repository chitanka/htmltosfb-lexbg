<?php
$inputFile = $argv[1];
$input = file_get_contents($inputFile);
echo clearInput($input);

function clearInput($input) {
	$output = iconv('windows-1251', 'utf-8', $input);
	$output = strtr($output, array(
		'\\"' => '"',
		"\t" => ' ',
		"\r\n" => "\n", "\r" => "\n",
		' xmlns=""' => '',
		'<p class=buttons>' => '',
		' style="display:block;"' => '',
		'<br>' => '<br/>',
		' id="DocumentTitle"' => '',
		'&nbsp;' => '',
		'&copy;' => '',
		'<br clear="all">' => '',
		'>/span>' => '></span>', // fix broken code
		'& ' => '&amp; ',
	));

	//$output = substr_replace($output, '', 0, strpos($output, '<div class="TitleDocument"')-1);
	$replacements = array(
		//'#</div></div></div>.+#ms' => '',
		//'#<div id="colright">.+#ms' => '',

		'#<!DOCTYPE.+<html .+>#Ums' => '<html>',
		'#  +#' => ' ',
		'#<head>.+</head>#Ums' => '',
		'#<div id="tl".+</div>#Ums' => '',
		'#<a href="javascript.+</a>#Ums' => '',
		'#<!--.+-->#Ums' => '',
		'#(onclick|onmouseover|onmouseout)="[^"]+"#' => '',
		'#<div class="picHasEditions".+</div>#U' => '',
		'#<div class="picRefsFromActs".+</div>#U' => '',
		'#<div class="picRefsFromPractices".+</div>#U' => '',
		'#<div class="picHasEditions".+</div>#U' => '',
		'#<div +id="buttons_.+</div>#Ums' => '',
		'#<script.+</script>#Ums' => '',
		'#<STYLE.+</STYLE>#Ums' => '',
		'#<style.+</style>#Ums' => '',
		// clear some stuff which throws xml errors
		'#<link.+>#U' => '',
		'#<input .+>#U' => '',
		'#<form.+</form>#Ums' => '',
		'#<a class="rlink.+</a>#U' => '',
		'#<g:plus.+</g:plus>#' => '',
		'#<font.+</font>#Ums' => '',
		'#<!- NACHALO NA TYXO.BG.+KRAI NA TYXO.BG BROYACH -->#ms' => '',
		'#<noscript>.+</noscript>#ms' => '',
		'#&(\w)#' => '&amp;$1',

		'#</(b|i)>([^ ,.-])#' => '</$1> $2', // ensure whitespace
	);
	foreach ($replacements as $regexp => $replacement) {
		$output = preg_replace($regexp, $replacement, $output);
	}

	$output = fixDates($output);
	$output = getImportantXmlStructure($output);

	return $output;
}

function getImportantXmlStructure($input) {
	$html = new SimpleXMLElement($input);
	$container = $html->xpath('//div[@class="boxi boxinb"]');

	$output = '';
	$isCoreReached = false;
	foreach ($container[0]->children() as $child) {
		if (!$isCoreReached && empty($child['class'])) {
			continue;
		} else {
			$isCoreReached = true; // everything else is a valid element
		}
		$output .= $child->asXML()."\n";
	}
	return '<doc>'.$output.'</doc>';
}

function fixDates($input) {
	$months = array(
		'/(\d) Януари/' => '$1 януари',
		'/(\d) Февруари/' => '$1 февруари',
		'/(\d) Март/' => '$1 март',
		'/(\d) Април/' => '$1 април',
		'/(\d) Май/' => '$1 май',
		'/(\d) Юни/' => '$1 юни',
		'/(\d) Юли/' => '$1 юли',
		'/(\d) Август/' => '$1 август',
		'/(\d) Септември/' => '$1 септември',
		'/(\d) Октомври/' => '$1 октомври',
		'/(\d) Ноември/' => '$1 ноември',
		'/(\d) Декември/' => '$1 декември',
		'/(\d{4})г\./' => '$1 г.',
	);
	$output = $input;
	foreach ($months as $regexp => $replacement) {
		$output = preg_replace($regexp, $replacement, $output);
	}

	return $output;
}

/*
<!DOCTYPE html>
<style>
.Title {
	font-size: 110%;
	font-weight: bold;
	background-color: black;
	color: white;
}
.TitleDocument .Title {
	font-size: 180%;
	padding-left: 0;
	color: black;
	background-color: white;
}
.Heading .Title {
	font-size: 150%;
	background-color: #069;
	color: white;
}
.Section .Title {
	font-size: 120%;
	padding-left: 50px;
	background-color: #069;
	color: white;
}
</style>
*/
