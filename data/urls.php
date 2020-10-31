<?php
$files = [
	__DIR__.'/url_constitution.php',
	__DIR__.'/url_code.php',
	__DIR__.'/url_laws.php',
	__DIR__.'/url_ords.php',
	__DIR__.'/url_regs.php',
	__DIR__.'/url_reg_laws.php',
];
return array_merge(...array_map(function(string $file) {
	return require $file;
}, $files));
