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
	->addVar('action', 'morereporting.percentiles')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.percentiles'))
	->setProfile('web.morereporting.percentiles.filter')
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
				new CFormField($period_presets),
				new CLabel(_('Compare with previous period'), 'filter_compare'),
				new CFormField(
					(new CCheckBox('filter_compare'))->setChecked($data['filter']['compare'])
				)
			])
	]));

if ($data['definition'] !== null) {
	$filter->addVar('reportid', $data['definition']['reportid']);
}

$title = $data['definition'] !== null ? $data['definition']['name'] : _('Item percentiles');

$html_page = (new CHtmlPage())->setTitle($title);

$controls = new CList();

foreach (
	['json' => _('Export JSON'), 'csv' => _('Export CSV'), 'yaml' => _('Export YAML'), 'pdf' => _('Export PDF')]
	as $format => $label
) {
	$export_url = (new CUrl('zabbix.php'))
		->setArgument('action', 'morereporting.percentiles.'.$format)
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

if ($data['graph'] !== null) {
	// CTag::addItem() htmlspecialchars-escapes plain strings; wrapping in CObject makes it use
	// CObject::toString() instead, so the server-rendered SVG markup is embedded unescaped.
	$html_page->addItem(new CDiv(new CObject($data['graph'])));
}

$is_comparing = $data['compare_pairs'] !== null;

$header = [_('Host'), _('Item'), _('Count'), _('Min'), _('Avg'), _('Max'), _('P50'), _('P90'), _('P95'), _('P99')];

if ($is_comparing) {
	$header[] = _('P95 (previous period)');
	$header[] = _('P95 Δ');
}

$table = (new CTableInfo())->setHeader($header);

foreach ($data['rows'] as $i => $row) {
	$cells = [
		$row['host'],
		$row['name'],
		$row['count'],
		round($row['min'], 4),
		round($row['avg'], 4),
		round($row['max'], 4),
		round($row['p50'], 4),
		round($row['p90'], 4),
		(new CSpan(round($row['p95'], 4)))->addClass(ZBX_STYLE_ORANGE),
		(new CSpan(round($row['p99'], 4)))->addClass(ZBX_STYLE_RED)
	];

	if ($is_comparing) {
		$previous = $data['compare_pairs'][$i]['previous'];

		if ($previous !== null) {
			$delta = $row['p95'] - $previous['p95'];

			$cells[] = round($previous['p95'], 4);
			$cells[] = (new CSpan(($delta >= 0 ? '+' : '').round($delta, 4)))
				->addClass($delta > 0 ? ZBX_STYLE_RED : ($delta < 0 ? ZBX_STYLE_GREEN : ZBX_STYLE_GREY));
		}
		else {
			$cells[] = (new CSpan(_('No data')))->addClass(ZBX_STYLE_GREY);
			$cells[] = '';
		}
	}

	$table->addRow($cells);
}

$html_page
	->addItem($table)
	->show();
