<?php

/**
 * @var CView $this
 * @var array $data
 */

$scope_fields = $data['definition'] !== null ? [] : [
	(new CFormGrid())
		->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
		->addItem([
			new CLabel(_('Host groups'), 'filter_groupids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'filter_groupids[]',
					'object_name' => 'hostGroup',
					'data' => $data['groups'],
					'popup' => [
						'parameters' => [
							'srctbl' => 'host_groups',
							'srcfld1' => 'groupid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_groupids_',
							'with_hosts' => true,
							'enrich_parent_groups' => true
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Hosts'), 'filter_hostids__ms'),
			new CFormField(
				(new CMultiSelect([
					'name' => 'filter_hostids[]',
					'object_name' => 'hosts',
					'data' => $data['hosts'],
					'popup' => [
						'filter_preselect' => [
							'id' => 'filter_groupids_',
							'submit_as' => 'groupid'
						],
						'parameters' => [
							'srctbl' => 'hosts',
							'srcfld1' => 'hostid',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_hostids_'
						]
					],
					'autosuggest' => [
						'filter_preselect' => [
							'id' => 'filter_groupids_',
							'submit_as' => 'groupid'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('Trigger name patterns'), 'filter_patterns__ms'),
			new CFormField(
				(new CPatternSelect([
					'name' => 'filter_patterns[]',
					'object_name' => 'triggers',
					'data' => $data['filter']['patterns'],
					'placeholder' => _('trigger patterns'),
					'wildcard_allowed' => true,
					'popup' => [
						'filter_preselect' => [
							'id' => 'filter_hostids_',
							'submit_as' => 'hostid'
						],
						'parameters' => [
							'srctbl' => 'triggers',
							'srcfld1' => 'description',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_patterns_'
						]
					],
					'autosuggest' => [
						'filter_preselect' => [
							'id' => 'filter_hostids_',
							'submit_as' => 'hostid'
						]
					]
				]))->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
];

$period_presets = new CList();

foreach ($data['time_presets'] as $label => $range) {
	$period_presets->addItem(
		(new CLink($label, '#'))
			->addClass(ZBX_STYLE_BTN_LINK)
			->setAttribute('onclick',
				'document.getElementsByName("filter_date_from")[0].value='.json_encode($range[0]).';'.
				'document.getElementsByName("filter_date_to")[0].value='.json_encode($range[1]).';'.
				'return false;'
			)
	);
}

$filter = (new CFilter())
	->addVar('action', 'morereporting.heatmap')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.heatmap'))
	->setProfile('web.morereporting.heatmap.filter')
	->setActiveTab($data['active_tab'])
	->addFilterTab(_('Filter'), array_merge($scope_fields, [
		(new CFormGrid())
			->addClass(CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE)
			->addItem([
				new CLabel(_('From'), 'filter_date_from'),
				new CFormField(
					(new CTextBox('filter_date_from', $data['filter']['date_from']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('placeholder', _('YYYY-MM-DD hh:mm or now-7d'))
				),
				new CLabel(_('To'), 'filter_date_to'),
				new CFormField(
					(new CTextBox('filter_date_to', $data['filter']['date_to']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
						->setAttribute('placeholder', _('YYYY-MM-DD hh:mm or now'))
				),
				new CLabel(_('Quick ranges')),
				new CFormField($period_presets)
			])
	]));

if ($data['definition'] !== null) {
	$filter->addVar('reportid', $data['definition']['reportid']);
}

$title = $data['definition'] !== null ? $data['definition']['name'] : _('Severity heatmap');

$html_page = (new CHtmlPage())->setTitle($title);

$controls = new CList();

foreach (
	['json' => _('Export JSON'), 'csv' => _('Export CSV'), 'yaml' => _('Export YAML'), 'pdf' => _('Export PDF')]
	as $format => $label
) {
	$export_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'morereporting.heatmap.'.$format)
		->setArgument('filter_groupids', $data['filter']['groupids'])
		->setArgument('filter_hostids', $data['filter']['hostids'])
		->setArgument('filter_patterns', $data['filter']['patterns'])
		->setArgument('filter_date_from', $data['filter']['date_from'])
		->setArgument('filter_date_to', $data['filter']['date_to']);

	if ($data['definition'] !== null) {
		$export_url->setArgument('reportid', $data['definition']['reportid']);
	}

	$controls->addItem(new CLink($label, $export_url->getUrl()));
}

if ($data['definition'] !== null) {
	$controls->addItem(
		new CLink(_('Edit report'),
			(new CUrl('zabbix.php'))
				->setArgument('action', 'morereporting.report.edit')
				->setArgument('reportid', $data['definition']['reportid'])
				->getUrl()
		)
	);
}

$html_page->setControls((new CTag('nav', true, $controls))->setAttribute('aria-label', _('Content controls')));

$html_page->addItem($filter);

$header = [_('Host')];

foreach ($data['severities'] as $severity) {
	$header[] = $severity['label'];
}

$header[] = _('Total');

$table = (new CTableInfo())->setHeader($header);

foreach ($data['rows'] as $row) {
	$cells = [$row['host']];

	foreach ($data['severities'] as $severity) {
		$count = $row['counts'][$severity['value']] ?? 0;

		$cells[] = $count > 0
			? CSeverityHelper::makeSeverityCell($severity['value'], (string) $count)
			: (new CCol('0'))->addClass(ZBX_STYLE_GREY);
	}

	$cells[] = $row['total'];

	$table->addRow($cells);
}

$html_page
	->addItem($table)
	->show();
