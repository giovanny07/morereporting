<?php

/**
 * @var CView $this
 * @var array $data
 */

$csv = [
	[_('Host'), _('Trigger'), _('Severity'), _('Availability %'), _('Downtime'), _('Episodes'), _('MTTR'), _('MTBF')]
];

foreach ($data['rows'] as $row) {
	$csv[] = [
		$row['host'],
		$row['description'],
		CSeverityHelper::getName($row['priority']),
		round($row['availability'], 4),
		convertUnitsS($row['downtime_seconds'], true),
		$row['episodes'],
		$row['mttr_seconds'] !== null ? convertUnitsS($row['mttr_seconds'], true) : _('N/A'),
		$row['mtbf_seconds'] !== null ? convertUnitsS($row['mtbf_seconds'], true) : _('N/A')
	];
}

echo zbx_toCSV($csv);
