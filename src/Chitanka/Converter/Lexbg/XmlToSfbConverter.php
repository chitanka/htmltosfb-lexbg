<?php namespace Chitanka\Converter\Lexbg;

use Chitanka\Converter\XmlIsInvalid;
use \SimpleXMLElement;
use Chitanka\Converter\XmlErrorHandler;

class XmlToSfbConverter {

	private $headingGlava = '>';
	private $prevTitleType = ''; // one of част, глава, раздел
	private $lastRowWithGlava;
	private $cyrUpper = 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЮЯ';
	private $cyrLower = 'абвгдежзийклмнопрстуфхцчшщъюя';
	private $cyrUpperSpaced = 'А Б В Г Д Е Ж З И Й К Л М Н О П Р С Т У Ф Х Ц Ч Ш Щ Ъ Ю Я';
	private $cyrLowerSpaced = 'а б в г д е ж з и й к л м н о п р с т у ф х ц ч ш щ ъ ю я';
	private $outputDir = '';

	public function __construct(string $outputDir = null) {
		if ($outputDir) {
			$this->outputDir = rtrim($outputDir, '/') . '/';
		}
		XmlErrorHandler::register();
	}

	public function convert(string $xml) {
		try {
			$doc = new SimpleXMLElement($xml, LIBXML_PARSEHUGE);
		} catch (\Exception $e) {
			throw new XmlIsInvalid($xml, $e);
		}
		$this->lastRowWithGlava = $this->findLastRowWithGlava($doc);
		$sfb = '';

		$counter = 0;
		foreach ($doc->children() as $elm) {
			$counter++;
			switch ($elm['class']) {
				case 'TitleDocument':
					$sfb .= $this->extractDocumentTitle($elm);
					break;
				case 'PreHistory':
					$sfb .= $this->extractPrehistory($elm);
					break;
				case 'HistoryOfDocument':
					$sfb .= $this->extractDocumentHistory($elm);
					break;
				case 'Heading':
					$sfb .= $this->extractHeading($elm, $counter);
					break;
				case 'Section':
					$sfb .= $this->extractSectionTitle($elm->p);
					break;
				case 'Article':
					$sfb .= $this->extractArticle($elm, $counter);
					break;
				case 'AdditionalEdicts':
				case 'TransitionalFinalEdicts':
				case 'FinalEdicts':
					$sfb .= $this->extractFinalEdicts($elm, $counter);
					break;
				case 'FinalEdictsArticle':
					$sfb .= $this->extractFinalEdictsArticle($elm, $counter);
					break;
				default:
					$sfb .= $this->extractUnknownElement($elm);
			}
		}

		$sfb = $this->fixDates($sfb);

		$sfb = rtrim($sfb, "\n") . "\n";
		return $sfb;
	}


	private function findLastRowWithGlava($doc) {
		$lastRow = -1;
		$counter = 0;
		foreach ($doc->children() as $elm) {
			$counter++;
			if ($elm['class'] == 'Heading' && $this->contains($elm->asXML(), 'Глава')) {
				$lastRow = $counter;
			}
		}
		return $lastRow;
	}

	private function extractDocumentTitle(SimpleXMLElement $elm) {
		$text = strip_tags($elm->p->asXML());
		$text = $this->normalizeTextTitle($text);
		return "|\t{$text}\n\n";
	}


	private function extractHeading(SimpleXMLElement $elm, $row) {
		$sfb = "";
		$titlesVisited = 0;
		foreach ($elm->children() as $child) {
			switch ($child->getName()) {
				case 'p':
					if ($child['class'] == 'Title') {
						$sfb .= $titlesVisited == 0
							? $this->extractHeadingTitle($child, $row)
							: $this->extractSectionTitle($child);
						$titlesVisited++;
					} else {
						$sfb .= $this->extractArticleDiv($child, $row);
					}
					break;
				case 'div':
				default:
					$sfb .= $this->extractArticle($child, $row);
					break;
				case 'br':
					$sfb .= "\n";
					break;
			}
		}
		return $sfb;
	}

	private function extractHeadingTitle(SimpleXMLElement $par, $row) {
		$heading = $this->headingGlava;
		$text = strip_tags($par->asXML());
		if ($this->contains($text, 'ЧАСТ')) {
			$this->headingGlava = '>>';
			$heading = '>';
		} else if ($this->contains($text, 'разпоредб')) {
			switch ($this->prevTitleType) {
				case 'част':
					break;
				case 'глава':
					break;
				case 'раздел':
					$heading = '>>>';
					break;
			}
			if ($this->lastRowWithGlava < $row) {
				// разпоредбите в края минават към най-горното ниво
				$heading = '>';
			}
		} else if (in_array($text, array('ИЗМЕНЕНИЯ НА ДРУГИ ЗАКОНИ', 'ПРЕХОДНИ ПРАВИЛА'))) {
			$heading = '>';
		} else  if ($text == 'КОНСТИТУЦИЯ') {
			// pseudo heading
			$heading = '#';
		} else {
			switch ($this->getSectionHeadingType($par)) {
				case 'roman':
				default:
					$heading = $this->headingGlava;
					break;
				case 'arabic':
					$heading = $this->headingGlava.'>';
					break;
				case 'letter':
					$heading = $this->headingGlava.'>>';
					break;
			}
		}
		return "\n" . $this->extractTitle($par, $heading) . "\n";
	}

	private function getSectionHeadingType(SimpleXMLElement $par) {
		$text = strip_tags($par->asXML());
		if (preg_match('/^[IXV]+\./', $text)) {
			return 'roman';
		}
		if (preg_match('/^\d+\./', $text)) {
			return 'arabic';
		}
		if (preg_match("/^[{$this->cyrUpper}]\)/u", $text)) {
			return 'letter';
		}
		return '';
	}

	private function extractSectionTitle(SimpleXMLElement $par) {
		return $this->extractTitle($par, $this->headingGlava.'>') . "\n";
	}

	private function extractEdictsTitle(SimpleXMLElement $elm, $row) {
		$heading = $this->headingGlava.'>';
		$text = $elm->p->asXML();
		if ($this->lastRowWithGlava < $row || $this->contains($text, 'КЪМ ')) {
			// разпоредбите в края минават към най-горното ниво
			$heading = '>';
		}
		return $this->extractTitle($elm->p, $heading) . "\n";
	}

	private function extractTitle(SimpleXMLElement $par, $sfbMarker) {
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
		$this->savePrevTitleType($title);
		return $title;
	}

	private function savePrevTitleType($text) {
		if ($this->contains($text, 'ЧАСТ')) {
			$this->prevTitleType = 'част';
		} else if ($this->contains($text, 'Глава')) {
			$this->prevTitleType = 'глава';
		} else if ($this->contains($text, 'Раздел')) {
			$this->prevTitleType = 'раздел';
		} else if ($this->contains($text, 'разпоредб')) {
			// no change
		} else {
			$this->prevTitleType = '';
		}
	}

	private function extractPrehistory(SimpleXMLElement $elm) {
		return "\t" . $elm . "\n";
	}

	private function extractDocumentHistory(SimpleXMLElement $elm) {
		// TODO put links to ДВ
		$sfb = "\n\t" . strip_tags($elm->p->asXML()) . "\n\n";
		$sfb = strtr($sfb, array(
			'ДВ. бр.' => 'ДВ, бр. ',
		));
		$sfb = preg_replace('/  +/', ' ', $sfb);
		return $sfb;
	}

	private function extractArticle(SimpleXMLElement $elm, $row) {
		$sfb = "";
		foreach ($elm->children() as $child) {
			switch ($child->getName()) {
				case 'div':
				default:
					$sfb .= $this->extractArticleDiv($child, $row);
					break;
				case 'table':
					$sfb .= $this->extractTable($child);
					break;
				case 'p':
					if ($child['class'] == 'Title') {
						$sfb .= $this->extractArticleTitle($child);
					} else {
						$sfb .= $this->extractArticleDiv($child, $row);
					}
					break;
				case 'br':
					$sfb .= "\n";
					break;
			}
		}
		$sfb = $this->removeTableMargins($sfb);
		return $sfb;
	}

	private function extractArticleTitle(SimpleXMLElement $par) {
		$text = $par->asXML();
		if ($this->contains($text, 'разпоредб')) {
			return $this->extractSectionTitle($par);
		}
		if ($this->contains($text, 'Релевантни актове')) {
			return $this->extractTitle($par, '>') . "\n";
		}
		return $this->extractTitle($par, '#') . "\n";
	}

	private function removeTableMargins($sfb) {
		$sfb = preg_replace('/\n\n+T>/', "\nT>", $sfb);
		$sfb = preg_replace('/T\$\n\n+/', "T$\n", $sfb);
		return $sfb;
	}

	private function extractArticleDiv(SimpleXMLElement $elm, $row) {
		$children = $elm->children();
		if (count($children) == 1 && $children[0]->getName() == 'img') {
			return $this->extractArticleImg($children[0]);
		}
		$text = trim(strip_tags($elm->asXML(), '<b><i>'));
		if (preg_match("/^[{$this->cyrUpper}][{$this->cyrLower}].+[{$this->cyrLower}]$/u", $text)) {
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
		if ($row > $this->lastRowWithGlava) {
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

	private function extractArticleImg(SimpleXMLElement $imgElm) {
		if (strpos($imgElm['src'], 'data:') == 0) {
			$imgName = $imgElm['name'];
			$delim = ';base64,';
			$imgContents = base64_decode(substr($imgElm['src'], strpos($imgElm['src'], $delim)+strlen($delim)));
			file_put_contents($this->outputDir . $imgName, $imgContents);
		} else {
			$imgName = basename($imgElm['src']);
			file_get_contents($imgElm['src']);
		}
		return "\t{img:$imgName}\n";
	}

	private function extractFinalEdicts(SimpleXMLElement $elm, $row) {
		$sfb = "";
		foreach ($elm->children() as $child) {
			switch ($child->getName()) {
				case 'p':
					if ($child['class'] == 'Title') {
						$sfb .= $this->extractEdictsTitle($elm, $row);
					} else {
						$sfb .= $this->extractArticleDiv($child, $row);
					}
					break;
				case 'div':
				default:
					$sfb .= $this->extractFinalEdictsArticle($child, $row);
					break;
			}
		}
		return $sfb;
	}


	private function extractFinalEdictsArticle(SimpleXMLElement $elm, $row) {
		return $this->extractArticle($elm, $row);
	}

	private function extractUnknownElement(SimpleXMLElement $elm) {
		return "\tUNKNOWN: " . $elm . "\n";
	}

	private function extractTable(SimpleXMLElement $table) {
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


	private function contains($text, $value) {
		return strpos($text, $value) !== false;
	}


	private function normalizeTextTitle($title) {
		$title = $this->mystrtolower($title);
		$title = $this->myucfirst($title);
		$title = strtr($title, array(
			' закона ' => ' Закона ',
			'република' => 'Република',
			'българия' => 'България',
			'сметната палата' => 'Сметната палата',
		));
		return $title;
	}

	private function fixDates($input) {
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

	private function mystrtolower($s) {
		$s = str_replace(explode(' ', $this->cyrUpperSpaced), explode(' ', $this->cyrLowerSpaced), $s);
		//$s = strtolower($s);
		return $s;
	}

	private function mystrtoupper($s) {
		$s = str_replace(explode(' ', $this->cyrLowerSpaced), explode(' ', $this->cyrUpperSpaced), $s);
		//$s = strtoupper($s);
		return $s;
	}

	private function myucfirst($s) {
		$ls = '#'. strtr($this->cyrLowerSpaced, array(' ' => ' #'));
		$s = str_replace(explode(' ', $ls), explode(' ', $this->cyrUpperSpaced), '#'.$s);
		return $s;
	}

}
