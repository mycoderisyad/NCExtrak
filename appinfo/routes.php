<?php

declare(strict_types=1);

return [
	'ocs' => [
		[
			'name' => 'extract.extract',
			'url' => '/api/v1/extract',
			'verb' => 'POST',
		],
		[
			'name' => 'extract.status',
			'url' => '/api/v1/jobs/{jobId}',
			'verb' => 'GET',
		],
	],
];
