<?php

namespace Modules\MoreReporting;

use Zabbix\Core\CModule, APP, CMenu, CMenuItem;

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
