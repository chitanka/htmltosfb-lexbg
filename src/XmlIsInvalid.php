<?php

class XmlIsInvalid extends \Exception {

	private $formattedErrors;

	public function __construct(string $xml, \Exception $previous) {
		file_put_contents(sys_get_temp_dir() . '/invalid.xml', $xml);
		$this->formattedErrors = XmlErrorHandler::formatErrors($xml);
		parent::__construct($this->formattedErrors);
	}

	public function getFormattedErrors(): string {
		return $this->formattedErrors;
	}
}
