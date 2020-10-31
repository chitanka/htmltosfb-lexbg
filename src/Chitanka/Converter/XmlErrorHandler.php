<?php namespace Chitanka\Converter;

class XmlErrorHandler {

	const EOL = "\n";
	const EOL2 = self::EOL.self::EOL;

	public static function register() {
		// Tell PHP to not print any warnings if XML is invalid
		// We will do the error handling.
		libxml_use_internal_errors(true);
	}

	public static function formatErrors(string $xml) {
		return self::EOL
			. self::lineSeparator('*') . self::EOL
			. 'XML ERRORS:' . self::EOL
			. implode(self::EOL2, self::getErrors($xml)) . self::EOL2;
	}

	public static function getErrors(string $xml): array {
		$errors = array_map(function(\LibXMLError $error) use ($xml) {
			return self::formatError($error, $xml);
		}, libxml_get_errors());
		libxml_clear_errors();
		return $errors;
	}

	public static function formatError(\LibXMLError $error, string $xml) {
		$errorMap = [
			LIBXML_ERR_WARNING => 'Warning',
			LIBXML_ERR_ERROR => 'Error',
			LIBXML_ERR_FATAL => 'Fatal Error',
		];
		$lines = array_filter([
			self::lineSeparator(),
			explode("\n", $xml)[$error->line - 1],
			str_repeat(' ', $error->column - 2) . '^',
			$errorMap[$error->level] . ' ' . $error->code . ': '. trim($error->message),
			"  Line: $error->line",
		]);
		return implode(self::EOL, $lines);
	}

	private static function lineSeparator(string $char = null, int $multiplier = null) {
		return str_repeat($char ?? '~', $multiplier ?? 80);
	}
}
