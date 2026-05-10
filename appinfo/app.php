<?php

declare(strict_types=1);

use OCA\NCExtrak\AppInfo\Application;
use OCP\Util;

require_once __DIR__ . '/../vendor/autoload.php';

new Application();
Util::addScript(Application::APP_ID, 'ncextrak-main');
