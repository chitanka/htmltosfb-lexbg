<?php namespace Chitanka\Converter\Lexbg;

use Chitanka\Converter\XmlIsInvalid;
use \SimpleXMLElement;
use Chitanka\Converter\XmlErrorHandler;

class HtmlToXmlConverter {

	public function __construct() {
		XmlErrorHandler::register();
	}

	public function convert(string $html): string {
		return $this->getCoreXmlStructure($this->clearInput($html));
	}

	private function clearInput($input) {
		$output = iconv('windows-1251', 'utf-8', $input);
		$output = strtr($output, array(
			'\\"' => '"',
			"\t" => ' ',
			"\r\n" => "\n", "\r" => "\n",
			' xmlns=""' => '',
			'<p class=buttons>' => '',
			' style="display:block;"' => '',
			'<br><div>' => '<div>',
			'<br>' => '<br/>',
			'<BR>' => '<br/>',
			'<HR>' => '<hr/>',
			' id="DocumentTitle"' => '',
			'&nbsp;' => '',
			'&copy;' => '',
			'<br clear="all">' => '',
			'<BR clear=all>' => '',
			'<BR style="PAGE-BREAK-BEFORE: always" clear=all>' => '',
			'<BR style="PAGE-BREAK-BEFORE: auto" clear=all>' => '',
			'>/span>' => '></span>', // fix broken code
			'>/SPAN>' => '></SPAN>', // fix broken code
			'</b>SPAN ' => '</b><SPAN ', // fix broken code
			'</b>/B>' => '</b></B>',
			'</b>/STRONG>' => '</b></STRONG>',
			'</b>/P>' => '</b></P>',
			'>SUP>' => '><SUP>', // fix broken code
			'>/TD>' => '></TD>', // fix broken code
			'& ' => '&amp; ',
			' >=' => ' &gt;=',
			'=<C<' => '=&lt;C&lt;',
			'<...>' => '&lt;...&gt;',
			' style="VERTICAL-ALIGN: baseline; punctuation-wrap: simple"' => '', // part of a p tag
			' style="VERTICAL-ALIGN: middle"' => '', // part of a p tag
			' style="FONT-SIZE: 12pt"' => '', // part of a span tag
			' style="COLOR: black"' => '', // part of a span tag
			'noWrap>' => '>', // Specification mandates value for attribute
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
			'#<noscript>.+</noscript>#Ums' => '',
			'#&(\w)#' => '&amp;$1',
			'#<(\d)#' => '&lt;$1',
			// put quotes around attributes without any
			'# (id|class|width|height|cellSpacing|cellPadding|border|align|vAlign|rowSpan|colSpan|lang|color|size|SIZE|dateTime|name|type|face)=([^"][^ >]*)#' => ' $1="$2"',

			'#</(b|i)>([^ ,.-])#' => '</$1> $2', // ensure whitespace
			'#<(img|hr) ([^>]+[^/])>#i' => '<$1 $2/>', // all tags must close
			'#<([^a-zA-Z/])#' => '&lt;$1',
			'#<BR [^>]+>#' => '<br/>',
			'#<P>(<SUP><SPAN>\d+</SPAN></SUP>[^<]+)</SPAN></P>#U' => '<p>$1</p>', // fix a superfluous span
			'#<SPAN>([^<]+)</p>#' => '$1</p>', // fix a superfluous span
			'#<P>([^<]+)</SPAN>#' => '<P>', // fix a superfluous span
		);
		foreach ($replacements as $regexp => $replacement) {
			$output = preg_replace($regexp, $replacement, $output);
		}

		return $output;
	}

	private function getCoreXmlStructure($input) {
		try {
			$html = new SimpleXMLElement($input);
		} catch (\Exception $e) {
			throw new XmlIsInvalid($input, $e);
		}
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
