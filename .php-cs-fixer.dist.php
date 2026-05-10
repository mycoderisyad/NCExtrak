<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
	->in(__DIR__ . '/lib')
	->name('*.php');

return (new PhpCsFixer\Config())
	->setRiskyAllowed(true)
	->setRules([
		'@PSR12' => true,
		'strict_param' => true,
		'declare_strict_types' => true,
		'array_syntax' => ['syntax' => 'short'],
	])
	->setFinder($finder);
