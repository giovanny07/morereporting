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

$general_tab = (new CFormGrid())
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
		new CLabel(_('Enabled'), 'status_enabled'),
		new CFormField(
			(new CCheckBox('status_enabled'))->setChecked($data['status'] == ZBX_REPORT_STATUS_ENABLED)
		)
	]);

$scope_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
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
		new CLabel(_('Hosts'), 'hostids__ms'),
		new CFormField(
			(new CMultiSelect([
				'name' => 'hostids[]',
				'object_name' => 'hosts',
				'data' => $data['hosts'],
				'popup' => [
					'parameters' => [
						'srctbl' => 'hosts',
						'srcfld1' => 'hostid',
						'dstfrm' => 'morereporting-report-edit',
						'dstfld1' => 'hostids_'
					]
				]
			]))->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
		)
	])
	->addItem([
		new CLabel(_('Name pattern'), 'pattern'),
		new CFormField([
			(new CTextBox('pattern', $data['pattern']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('e.g. CPU* or *disk*, leave empty to match everything')),
			(new CDiv(_('Matches item names (Item percentiles) or trigger names (Trigger availability). Use * as a wildcard for any number of characters; without *, it matches names containing this text.')))
				->addClass(ZBX_STYLE_GREY)
		])
	])
	->addItem([
		(new CLabel(_('SLO %'), 'slo'))->setId('slo-label'),
		(new CFormField(
			(new CTextBox('slo', $data['slo']))->setWidth(100)
		))->setId('slo-field')
	]);

$period_presets = new CList();

foreach ($data['time_presets'] as $label => $range) {
	$period_presets->addItem(
		(new CLink($label, '#'))
			->addClass(ZBX_STYLE_BTN_LINK)
			->setAttribute('onclick',
				'document.getElementsByName("period_from")[0].value='.json_encode($range[0]).';'.
				'document.getElementsByName("period_to")[0].value='.json_encode($range[1]).';'.
				'return false;'
			)
	);
}

$period_tab = (new CFormGrid())
	->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
	->addItem([
		new CLabel(_('From'), 'period_from'),
		new CFormField(
			(new CTextBox('period_from', $data['period_from']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('YYYY-MM-DD hh:mm or now-7d'))
		)
	])
	->addItem([
		new CLabel(_('To'), 'period_to'),
		new CFormField(
			(new CTextBox('period_to', $data['period_to']))
				->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
				->setAttribute('placeholder', _('YYYY-MM-DD hh:mm or now'))
		)
	])
	->addItem([
		new CLabel(_('Quick ranges')),
		new CFormField($period_presets)
	]);

$tabs = (new CTabView())
	->setSelected(0)
	->addTab('general-tab', _('General'), $general_tab)
	->addTab('scope-tab', _('Scope'), $scope_tab)
	->addTab('period-tab', _('Default period'), $period_tab);

$form->addItem($tabs);

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

(new CScriptTag('
	const type_fields = '.json_encode($data['report_type_fields']).';
	const report_type = document.getElementById("report_type");

	const toggleTypeFields = () => {
		const visible = (type_fields[report_type.value] || []).includes("slo");

		document.getElementById("slo-label").style.display = visible ? "" : "none";
		document.getElementById("slo-field").style.display = visible ? "" : "none";
	};

	report_type.addEventListener("change", toggleTypeFields);
	toggleTypeFields();
'))
	->setOnDocumentReady()
	->show();
