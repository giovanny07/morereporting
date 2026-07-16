<?php

/**
 * @var CView $this
 * @var array $data
 */

$form = (new CForm('post'))
	->setId('morereporting-report-edit')
	->setAction((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.report.update')->getUrl())
	->addVar('_csrf_token', CCsrfTokenHelper::get('morereporting.report.update'));

if ($data['reportid'] !== null) {
	$form->addVar('reportid', $data['reportid']);
}

$form->addItem(
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			(new CLabel(_('Name'), 'name'))->setAsteriskMark(),
			new CFormField(
				(new CTextBox('name', $data['name']))
					->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
					->setAriaRequired()
					->setAttribute('autofocus', 'autofocus')
			)
		])
		->addItem([
			(new CLabel(_('Report type'), 'report_type'))->setAsteriskMark(),
			new CFormField(
				(new CSelect('report_type'))
					->setId('report_type')
					->setValue($data['report_type'])
					->setFocusableElementId('report_type')
					->addOptions(CSelect::createOptionsFromArray($data['report_type_labels']))
			)
		])
		->addItem([
			new CLabel(_('Host groups'), 'groupids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'groupids[]',
					'object_name' => 'hostGroup',
					'data' => $data['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'morereporting-report-edit',
							'dstfld1' => 'groupids_',
							'with_hosts' => true,
							'enrich_parent_groups' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Name pattern'), 'pattern'),
			new CFormField(
				(new CTextBox('pattern', $data['pattern']))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('SLO %'), 'slo'),
			new CFormField(
				(new CTextBox('slo', $data['slo']))->setWidth(100)
			)
		])
);

$form->addItem(
	new CFormActions(
		new CSubmitButton(_('Save'), 'action', 'morereporting.report.update'),
		[new CRedirectButton(_('Cancel'), (new CUrl('zabbix.php'))->setArgument('action', 'morereporting.list'))]
	)
);

(new CHtmlPage())
	->setTitle($data['reportid'] === null ? _('New report') : _('Edit report'))
	->addItem($form)
	->show();
