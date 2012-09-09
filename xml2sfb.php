<?php
$file = $argv[1];
$xmlContents = file_get_contents($file);
$doc = new SimpleXMLElement($xmlContents);
$sfb = '';

$headingGlava = '>';
$prevTitleType = ''; // one of част, глава, раздел
$lastRowWithGlava = findLastRowWithGlava($doc);
$cyrUpper = 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЮЯ';
$cyrLower = 'абвгдежзийклмнопрстуфхцчшщъюя';
$cyrUpperSpaced = 'А Б В Г Д Е Ж З И Й К Л М Н О П Р С Т У Ф Х Ц Ч Ш Щ Ъ Ю Я';
$cyrLowerSpaced = 'а б в г д е ж з и й к л м н о п р с т у ф х ц ч ш щ ъ ю я';

$counter = 0;
foreach ($doc->children() as $elm) {
	$counter++;
	switch ($elm['class']) {
		case 'TitleDocument':
			$sfb .= extractDocumentTitle($elm);
			break;
		case 'PreHistory':
			$sfb .= extractPrehistory($elm);
			break;
		case 'HistoryOfDocument':
			$sfb .= extractDocumentHistory($elm);
			break;
		case 'Heading':
			$sfb .= extractHeading($elm, $counter);
			break;
		case 'Section':
			$sfb .= extractSectionTitle($elm->p);
			break;
		case 'Article':
			$sfb .= extractArticle($elm, $counter);
			break;
		case 'AdditionalEdicts':
		case 'TransitionalFinalEdicts':
		case 'FinalEdicts':
			$sfb .= extractFinalEdicts($elm, $counter);
			break;
		case 'FinalEdictsArticle':
			$sfb .= extractFinalEdictsArticle($elm, $counter);
			break;
		default:
			$sfb .= extractUnknownElement($elm);
	}
}

$sfb = rtrim($sfb, "\n") . "\n";
echo $sfb;


function findLastRowWithGlava($doc) {
	$lastRow = -1;
	$counter = 0;
	foreach ($doc->children() as $elm) {
		$counter++;
		if ($elm['class'] == 'Heading' && contains($elm->asXML(), 'Глава')) {
			$lastRow = $counter;
		}
	}
	return $lastRow;
}

function extractDocumentTitle(SimpleXMLElement $elm) {
	$text = strip_tags($elm->p->asXML());
	$text = normalizeTextTitle($text);
	return "|\t{$text}\n\n";
}


function extractHeading(SimpleXMLElement $elm, $row) {
	$sfb = "";
	$titlesVisited = 0;
	foreach ($elm->children() as $child) {
		switch ($child->getName()) {
			case 'p':
				if ($child['class'] == 'Title') {
					$sfb .= $titlesVisited == 0
						? extractHeadingTitle($child, $row)
						: extractSectionTitle($child);
					$titlesVisited++;
				} else {
					$sfb .= extractArticleDiv($child, $row);
				}
				break;
			case 'div':
			default:
				$sfb .= extractArticle($child, $row);
				break;
			case 'br':
				$sfb .= "\n";
				break;
		}
	}
	return $sfb;
}

function extractHeadingTitle(SimpleXMLElement $par, $row) {
	global $headingGlava, $prevTitleType, $lastRowWithGlava;

	$heading = $headingGlava;
	$text = strip_tags($par->asXML());
	if (contains($text, 'ЧАСТ')) {
		$headingGlava = '>>';
		$heading = '>';
	} else if (contains($text, 'разпоредб')) {
		switch ($prevTitleType) {
			case 'част':
				break;
			case 'глава':
				break;
			case 'раздел':
				$heading = '>>>';
				break;
		}
		if ($lastRowWithGlava < $row) {
			// разпоредбите в края минават към най-горното ниво
			$heading = '>';
		}
	} else if (in_array($text, array('ИЗМЕНЕНИЯ НА ДРУГИ ЗАКОНИ', 'ПРЕХОДНИ ПРАВИЛА'))) {
		$heading = '>';
	} else  if ($text == 'КОНСТИТУЦИЯ') {
		// pseudo heading
		$heading = '#';
	} else {
		switch (getSectionHeadingType($par)) {
			case 'roman':
			default:
				$heading = $headingGlava;
				break;
			case 'arabic':
				$heading = $headingGlava.'>';
				break;
			case 'letter':
				$heading = $headingGlava.'>>';
				break;
		}
	}
	return "\n" . extractTitle($par, $heading) . "\n";
}

function getSectionHeadingType(SimpleXMLElement $par) {
	$text = strip_tags($par->asXML());
	if (preg_match('/^[IXV]+\./', $text)) {
		return 'roman';
	}
	if (preg_match('/^\d+\./', $text)) {
		return 'arabic';
	}
	global $cyrUpper;
	if (preg_match("/^[$cyrUpper]\)/u", $text)) {
		return 'letter';
	}
	return '';
}

function extractSectionTitle(SimpleXMLElement $par) {
	global $headingGlava;

	return extractTitle($par, $headingGlava.'>') . "\n";
}

function extractEdictsTitle(SimpleXMLElement $elm, $row) {
	global $headingGlava, $lastRowWithGlava;

	$heading = $headingGlava.'>';
	$text = $elm->p->asXML();
	if ($lastRowWithGlava < $row || contains($text, 'КЪМ ')) {
		// разпоредбите в края минават към най-горното ниво
		$heading = '>';
	}
	return extractTitle($elm->p, $heading) . "\n";
}

function extractTitle(SimpleXMLElement $par, $sfbMarker) {
	$title = '';
	$text = strtr(strip_tags($par->asXML(), '<br>'), array(
		'Г.' => 'Г.', // да остане точката след XXXX г.
		'.<br/>' => "<br/>",
	));
	foreach (explode("<br/>", $text) as $line) {
		$line = trim($line);
		if ($line) {
			$title .= $sfbMarker . "\t" . trim($line) . "\n";
		}
	}
	savePrevTitleType($title);
	return $title;
}

function savePrevTitleType($text) {
	global $prevTitleType;

	if (contains($text, 'ЧАСТ')) {
		$prevTitleType = 'част';
	} else if (contains($text, 'Глава')) {
		$prevTitleType = 'глава';
	} else if (contains($text, 'Раздел')) {
		$prevTitleType = 'раздел';
	} else if (contains($text, 'разпоредб')) {
		// no change
	} else {
		$prevTitleType = '';
	}
}

function extractPrehistory(SimpleXMLElement $elm) {
	return "\t" . $elm . "\n";
}

function extractDocumentHistory(SimpleXMLElement $elm) {
	// TODO put links to ДВ
	$sfb = "\n\t" . strip_tags($elm->p->asXML()) . "\n\n";
	$sfb = strtr($sfb, array(
		'ДВ. бр.' => 'ДВ, бр. ',
	));
	$sfb = preg_replace('/  +/', ' ', $sfb);
	return $sfb;
}

function extractArticle(SimpleXMLElement $elm, $row) {
	$sfb = "";
	foreach ($elm->children() as $child) {
		switch ($child->getName()) {
			case 'div':
			default:
				$sfb .= extractArticleDiv($child, $row);
				break;
			case 'table':
				$sfb .= extractTable($child);
				break;
			case 'p':
				if ($child['class'] == 'Title') {
					$sfb .= extractArticleTitle($child);
				} else {
					$sfb .= extractArticleDiv($child, $row);
				}
				break;
			case 'br':
				$sfb .= "\n";
				break;
		}
	}
	$sfb = removeTableMargins($sfb);
	return $sfb;
}

function extractArticleTitle(SimpleXMLElement $par) {
	$text = $par->asXML();
	if (contains($text, 'разпоредб')) {
		return extractSectionTitle($par);
	}
	if (contains($text, 'Релевантни актове')) {
		return extractTitle($par, '>') . "\n";
	}
	return extractTitle($par, '#') . "\n";
}

function removeTableMargins($sfb) {
	$sfb = preg_replace('/\n\n+T>/', "\nT>", $sfb);
	$sfb = preg_replace('/T\$\n\n+/', "T$\n", $sfb);
	return $sfb;
}

function extractArticleDiv(SimpleXMLElement $elm, $row) {
	global $lastRowWithGlava;

	$children = $elm->children();
	if (count($children) == 1 && $children[0]->getName() == 'img') {
		return extractArticleImg($children[0]);
	}
	$text = trim(strip_tags($elm->asXML(), '<b><i>'));
	global $cyrUpper, $cyrLower;
	if (preg_match("/^[$cyrUpper][$cyrLower].+[$cyrLower]$/u", $text)) {
		$text = "__{$text}__";
	}
	$sfb = "\t" . $text . "\n";
	if (preg_match('/^[IXV]+\..+[^.]$/', $text)) {
		// a subheading without proper markup
		$sfb = '#'.$sfb;
	}
	$repl = array(
		'<b>' => '__', '</b>' => '__',
		'<i>' => '{e}', '</i>' => '{/e}',
		'<' => '{', '>' => '}',
		"\tПриложение към" => ">\tПриложение към",
		'(ОБН.' => '(Обн.',
		', БР.' => ', бр.',
		' Г.,' => ' г.,',
		' Г.)' => ' г.)',
		'В СИЛА ОТ' => 'в сила от',
		'ИЗМ. И ДОП. ' => 'изм. и доп. ',
		'ИЗМ. ' => 'изм. ',
		'ДОП. ' => 'доп. ',
	);
	if ($row > $lastRowWithGlava) {
		$repl["\tПриложение №"] = ">\tПриложение №";
	}
	$sfb = strtr($sfb, $repl);
	// бр. 1 ОТ 2011 г.
	$sfb = preg_replace('/(\d) ОТ (\d)/', '$1 от $2', $sfb);
	$sfb = preg_replace('/(\d) - (\d)/', '$1–$2', $sfb);
	/*
	span classes:
		SameDocReference
		LegalDocReference
		NewDocReference
	*/
	return $sfb;
}

function extractArticleImg(SimpleXMLElement $imgElm) {
	if (strpos($imgElm['src'], 'data:') == 0) {
		$imgName = $imgElm['name'];
		$delim = ';base64,';
		$imgContents = base64_decode(substr($imgElm['src'], strpos($imgElm['src'], $delim)+strlen($delim)));
		file_put_contents($imgName, $imgContents);
	} else {
		$imgName = basename($imgElm['src']);
		file_get_contents($imgElm['src']);
	}
	return "\t{img:$imgName}\n";
}

function extractFinalEdicts(SimpleXMLElement $elm, $row) {
	$sfb = "";
	foreach ($elm->children() as $child) {
		switch ($child->getName()) {
			case 'p':
				if ($child['class'] == 'Title') {
					$sfb .= extractEdictsTitle($elm, $row);
				} else {
					$sfb .= extractArticleDiv($child, $row);
				}
				break;
			case 'div':
			default:
				$sfb .= extractFinalEdictsArticle($child, $row);
				break;
		}
	}
	return $sfb;
}


function extractFinalEdictsArticle(SimpleXMLElement $elm, $row) {
	return extractArticle($elm, $row);
}

function extractUnknownElement(SimpleXMLElement $elm) {
	return "\tUNKNOWN: " . $elm . "\n";
}

function extractTable(SimpleXMLElement $table) {
	if (count($table->children()) == 0) {
		return '';
	}
	$sfb = "T>\n";
	foreach ($table->tr as $tr) {
		$sfb .= "\t";
		foreach ($tr->td as $td) {
			$sfb .= '| '.strip_tags($td->asXML(), '<b><i>') . ' ';
		}
		$sfb .= "|\n";
	}
	$sfb .= "T$\n";
	return $sfb;
}


function contains($text, $value) {
	return strpos($text, $value) !== false;
}


function normalizeTextTitle($title) {
	$title = mystrtolower($title);
	$title = myucfirst($title);
	$title = strtr($title, array(
		' закона ' => ' Закона ',
		'република' => 'Република',
		'българия' => 'България',
		'сметната палата' => 'Сметната палата',
	));
	return $title;
}

function mystrtolower($s) {
	global $cyrUpperSpaced, $cyrLowerSpaced;
	$s = str_replace(explode(' ', $cyrUpperSpaced), explode(' ', $cyrLowerSpaced), $s);
	//$s = strtolower($s);
	return $s;
}

function mystrtoupper($s) {
	global $cyrUpperSpaced, $cyrLowerSpaced;
	$s = str_replace(explode(' ', $cyrLowerSpaced), explode(' ', $cyrUpperSpaced), $s);
	//$s = strtoupper($s);
	return $s;
}

function myucfirst($s) {
	global $cyrUpperSpaced, $cyrLowerSpaced;
	$ls = '#'. strtr($cyrLowerSpaced, array(' ' => ' #'));
	$s = str_replace(explode(' ', $ls), explode(' ', $cyrUpperSpaced), '#'.$s);
	return $s;
}
