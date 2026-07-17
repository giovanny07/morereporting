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
			new CLabel(_('Item name patterns'), 'filter_patterns__ms'),
			new CFormField(
				(new CPatternSelect([
					'name' => 'filter_patterns[]',
					'object_name' => 'items',
					'data' => $data['filter']['patterns'],
					'placeholder' => _('item patterns'),
					'wildcard_allowed' => true,
					'popup' => [
						'filter_preselect' => [
							'id' => 'filter_hostids_',
							'submit_as' => 'hostid'
						],
						'parameters' => [
							'srctbl' => 'items',
							'srcfld1' => 'name',
							'dstfrm' => 'zbx_filter',
							'dstfld1' => 'filter_patterns_',
							'real_hosts' => true,
							'numeric' => true
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
		->addItem([
			new CLabel(_('Baseline (days before period)'), 'filter_baseline_days'),
			new CFormField(
				(new CTextBox('filter_baseline_days', $data['filter']['baseline_days']))->setWidth(100)
			)
		])
		->addItem([
			new CLabel(_('Z-score threshold'), 'filter_zscore'),
			new CFormField(
				(new CTextBox('filter_zscore', $data['filter']['zscore']))->setWidth(100)
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
	->addVar('action', 'morereporting.anomaly')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.anomaly'))
	->setProfile('web.morereporting.anomaly.filter')
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

$title = $data['definition'] !== null ? $data['definition']['name'] : _('Anomaly detection');

$html_page = (new CHtmlPage())->setTitle($title);

$controls = new CList();

foreach (
	['json' => _('Export JSON'), 'csv' => _('Export CSV'), 'yaml' => _('Export YAML'), 'pdf' => _('Export PDF')]
	as $format => $label
) {
	$export_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'morereporting.anomaly.'.$format)
		->setArgument('filter_groupids', $data['filter']['groupids'])
		->setArgument('filter_hostids', $data['filter']['hostids'])
		->setArgument('filter_patterns', $data['filter']['patterns'])
		->setArgument('filter_baseline_days', $data['filter']['baseline_days'])
		->setArgument('filter_zscore', $data['filter']['zscore'])
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

$table = (new CTableInfo())->setHeader([
	_('Host'), _('Item'), _('Baseline mean'), _('Baseline stddev'), _('Analysis points'), _('Anomalies'),
	_('Anomaly rate'), _('Max |z|'), _('Worst value'), _('Worst value time')
]);

foreach ($data['rows'] as $row) {
	$anomalies_tag = (new CSpan($row['anomalies']))
		->addClass($row['anomalies'] > 0 ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	$table->addRow([
		$row['host'],
		$row['name'],
		round($row['baseline_mean'], 4),
		round($row['baseline_stddev'], 4),
		$row['analysis_count'],
		$anomalies_tag,
		round($row['anomaly_rate'], 2).'%',
		round($row['max_zscore'], 4),
		$row['worst_value'] !== null ? round($row['worst_value'], 4) : (new CSpan(_('N/A')))->addClass(ZBX_STYLE_GREY),
		$row['worst_clock'] !== null ? date('Y-m-d H:i:s', $row['worst_clock']) : ''
	]);
}

$html_page
	->addItem($table)
	->show();
