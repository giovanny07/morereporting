<?php

/**
 * @var CView $this
 * @var array $data
 */

$csv = [
	[_('Host'), _('Item'), _('Count'), _('Min'), _('Avg'), _('Max'), _('P50'), _('P90'), _('P95'), _('P99')]
];

foreach ($data['rows'] as $row) {
	$csv[] = [
		$row['host'],
		$row['name'],
		$row['count'],
		round($row['min'], 4),
		round($row['avg'], 4),
		round($row['max'], 4),
		round($row['p50'], 4),
		round($row['p90'], 4),
		round($row['p95'], 4),
		round($row['p99'], 4)
	];
}

echo zbx_toCSV($csv);
