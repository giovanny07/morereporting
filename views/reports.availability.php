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
			new CLabel(_('Trigger name contains'), 'filter_pattern'),
			new CFormField(
				(new CTextBox('filter_pattern', $data['filter']['pattern']))
					->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
			)
		])
		->addItem([
			new CLabel(_('SLO %'), 'filter_slo'),
			new CFormField(
				(new CTextBox('filter_slo', $data['filter']['slo']))
					->setWidth(100)
			)
		])
];

$filter = (new CFilter())
	->addVar('action', 'morereporting.availability')
	->setResetUrl((new CUrl('zabbix.php'))->setArgument('action', 'morereporting.availability'))
	->setProfile('web.morereporting.availability.filter')
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

$title = $data['definition'] !== null ? $data['definition']['name'] : _('Trigger availability');

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

$table = (new CTableInfo())
	->setHeader([_('Host'), _('Trigger'), _('Severity'), _('Availability'), _('Downtime')]);

foreach ($data['rows'] as $row) {
	$availability_tag = (new CSpan(round($row['availability'], 4).'%'))
		->addClass($row['availability'] >= $data['slo'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED);

	$table->addRow([
		$row['host'],
		$row['description'],
		CSeverityHelper::makeSeverityCell($row['priority']),
		$availability_tag,
		convertUnitsS($row['downtime_seconds'], true)
	]);
}

$html_page
	->addItem($table)
	->show();
