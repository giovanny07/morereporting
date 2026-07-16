<?php

/**
 * @var CView $this
 * @var array $data
 */

$this->addJsFile('class.calendar.js');

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
			new CLabel(_('Item name contains'), 'filter_pattern'),
			new CFormField(
				(new CTextBox('filter_pattern', $data['filter']['pattern']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
];

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
					(new CDateSelector('filter_date_from', $data['filter']['date_from']))
						->setDateFormat(ZBX_DATE_TIME)
						->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				),
				new CLabel(_('To'), 'filter_date_to'),
				new CFormField(
					(new CDateSelector('filter_date_to', $data['filter']['date_to']))
						->setDateFormat(ZBX_DATE_TIME)
						->setPlaceholder(_('YYYY-MM-DD hh:mm'))
				)
			])
	]));

if ($data['definition'] !== null) {
	$filter->addVar('reportid', $data['definition']['reportid']);
}

$title = $data['definition'] !== null ? $data['definition']['name'] : _('Item percentiles');

$html_page = (new CHtmlPage())->setTitle($title);

if ($data['definition'] !== null) {
	$html_page->setControls(
		(new CTag('nav', true,
			(new CList())->addItem(
				new CLink(_('Edit report'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'morereporting.report.edit')
						->setArgument('reportid', $data['definition']['reportid'])
						->getUrl()
				)
			)
		))->setAttribute('aria-label', _('Content controls'))
	);
}

$html_page->addItem($filter);

if ($data['graph'] !== null) {
	// CTag::addItem() htmlspecialchars-escapes plain strings; wrapping in CObject makes it use
	// CObject::toString() instead, so the server-rendered SVG markup is embedded unescaped.
	$html_page->addItem(new CDiv(new CObject($data['graph'])));
}

$table = (new CTableInfo())
	->setHeader([_('Host'), _('Item'), _('Count'), _('Min'), _('Avg'), _('Max'), _('P50'), _('P90'), _('P95'), _('P99')]);

foreach ($data['rows'] as $row) {
	$table->addRow([
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
	]);
}

$html_page
	->addItem($table)
	->show();
