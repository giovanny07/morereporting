<?php

namespace Modules\MoreReporting;

use Zabbix\Core\CModule, APP, CMenu, CMenuItem;

// Zabbix's own CAutoloader only knows about this module's Modules\MoreReporting\* namespace;
// third-party dependencies (e.g. symfony/yaml for YAML export) need Composer's autoloader
// registered separately. This module requires `composer install` in its own directory to
// work once any such dependency is in use - not just to run its test suite.
require_once __DIR__.'/vendor/autoload.php';

class Module extends CModule {

	public function init(): void {
		APP::Component()->get('menu.main')
			->findOrAdd(_('Reports'))
			->getSubmenu()
			->insertAfter(_('Scheduled reports'),
				(new CMenuItem(_('MoreReporting')))->setAction('morereporting.list')
			);
	}
}
