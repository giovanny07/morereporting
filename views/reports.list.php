<?php

use Modules\MoreReporting\Includes\ReportTypeRegistry;

/**
 * @var CView $this
 * @var array $data
 */

$html_page = (new CHtmlPage())
	->setTitle($data['title'])
	->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CRedirectButton(_('Create report'),
					(new CUrl('zabbix.php'))->setArgument('action', 'morereporting.report.edit')
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);

$table = (new CTableInfo())->setHeader([_('Name'), _('Type'), _('Actions')]);

foreach ($data['reports'] as $report) {
	$run_action = ReportTypeRegistry::action($report['report_type']);

	$run_url = (new CUrl('zabbix.php'))
		->setArgument('action', $run_action)
		->setArgument('reportid', $report['reportid']);

	$edit_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'morereporting.report.edit')
		->setArgument('reportid', $report['reportid']);

	$delete_form = (new CForm('post'))
		->cleanItems()
		->setAction((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.report.delete')->getUrl())
		->addVar('_csrf_token', CCsrfTokenHelper::get('morereporting.report.delete'))
		->addVar('reportid', $report['reportid'])
		->addItem(
			(new CSubmitButton(_('Delete')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->setAttribute('onclick', 'return confirm('.
					json_encode(_s('Delete report "%1$s"?', $report['name'])).');'
				)
		);

	$table->addRow([
		new CLink($report['name'], $run_url->getUrl()),
		$data['report_type_labels'][$report['report_type']] ?? $report['report_type'],
		[
			new CLink(_('Edit'), $edit_url->getUrl()),
			' ',
			$delete_form
		]
	]);
}

$html_page->addItem($table);

$html_page->show();
