<?php

use Modules\MoreReporting\Includes\ReportTypeRegistry;

/**
 * @var CView $this
 * @var array $data
 */

$filter = (new CFilter())
	->addVar('action', 'morereporting.list')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list'))
	->setProfile('web.morereporting.list.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Name'), 'filter_name'),
				new CFormField(
					(new CTextBox('filter_name', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('autofocus', 'autofocus')
				)
			])
			->addItem([
				new CLabel(_('Type')),
				new CFormField(
					(new CSelect('filter_report_type'))
						->setValue($data['filter']['report_type'])
						->addOption(new CSelectOption('', _('Any')))
						->addOptions(CSelect::createOptionsFromArray($data['report_type_labels']))
				)
			]),
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('Status')),
				new CFormField(
					(new CRadioButtonList('filter_status', $data['filter']['status'] === ''
						? -1 : (int) $data['filter']['status']
					))
						->addValue(_('Any'), -1)
						->addValue(_('Enabled'), ZBX_REPORT_STATUS_ENABLED)
						->addValue(_('Disabled'), ZBX_REPORT_STATUS_DISABLED)
						->setModern(true)
				)
			])
			->addItem([
				new CLabel(_('Show')),
				new CFormField(
					(new CRadioButtonList('filter_show', $data['filter']['show']))
						->addValue(_('All'), ZBX_REPORT_FILTER_SHOW_ALL)
						->addValue(_('Created by me'), ZBX_REPORT_FILTER_SHOW_MY)
						->setModern(true)
				)
			])
	]);

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
	)
	->addItem($filter);

$table = (new CTableInfo())->setHeader([_('Name'), _('Type'), _('Status'), _('Created by'), _('Actions')]);

foreach ($data['reports'] as $report) {
	$run_action = ReportTypeRegistry::action($report['report_type']);

	$run_url = (new CUrl('zabbix.php'))
		->setArgument('action', $run_action)
		->setArgument('reportid', $report['reportid']);

	$edit_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'morereporting.report.edit')
		->setArgument('reportid', $report['reportid']);

	$is_enabled = (int) $report['status'] === ZBX_REPORT_STATUS_ENABLED;

	$status_form = (new CForm('post'))
		->cleanItems()
		->setAction((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.report.status')->getUrl())
		->addVar('_csrf_token', CCsrfTokenHelper::get('morereporting.report.status'))
		->addVar('reportid', $report['reportid'])
		->addVar('status', $is_enabled ? ZBX_REPORT_STATUS_DISABLED : ZBX_REPORT_STATUS_ENABLED)
		->addItem(
			(new CSubmitButton($is_enabled ? _('Enabled') : _('Disabled')))
				->addClass(ZBX_STYLE_BTN_LINK)
				->addClass($is_enabled ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY)
		);

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

	$creator = $data['users'][$report['userid']] ?? null;

	$table->addRow([
		new CLink($report['name'], $run_url->getUrl()),
		$data['report_type_labels'][$report['report_type']] ?? $report['report_type'],
		$status_form,
		$creator !== null ? getUserFullname($creator) : '',
		[
			new CLink(_('Edit'), $edit_url->getUrl()),
			' ',
			$delete_form
		]
	]);
}

$html_page
	->addItem($table)
	->show();
