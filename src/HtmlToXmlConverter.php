<?php

class HtmlToXmlConverter {

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
			' id="DocumentTitle"' => '',
			'&nbsp;' => '',
			'&copy;' => '',
			'<br clear="all">' => '',
			'>/span>' => '></span>', // fix broken code
			'& ' => '&amp; ',
			'<=' => '&lt;=',
			' >=' => ' &gt;=',
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
			'# (class|width|cellSpacing|cellPadding|border|align|rowSpan|colSpan|lang)=([^"][^ >]*)#' => ' $1="$2"', // put quotes around attributes without any

			'#</(b|i)>([^ ,.-])#' => '</$1> $2', // ensure whitespace
			'#<img ([^>]+[^/])>#' => '<img $1/>', // all tags must close
		);
		foreach ($replacements as $regexp => $replacement) {
			$output = preg_replace($regexp, $replacement, $output);
		}

		return $output;
	}

	private function getCoreXmlStructure($input) {
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
