<?php

/**
 * @var CView $this
 * @var array $data
 */

$table = (new CTableInfo())->setHeader([_('Report'), _('Description')]);

$table->addRow([
	new CLink(_('Item percentiles'),
		(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.percentiles')->getUrl()
	),
	_('p50/p90/p95/p99 over raw history values for numeric items, with a native trend graph.')
]);

$table->addRow([
	new CLink(_('Trigger availability'),
		(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.availability')->getUrl()
	),
	_('Availability % (SLI vs SLO) and downtime per trigger over a time window.')
]);

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem($table)
	->show();
