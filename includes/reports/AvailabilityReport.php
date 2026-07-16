<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Trigger availability report: for triggers matching the filter, computes the percentage
 * of the requested time window spent in OK vs PROBLEM state using Zabbix's own
 * calculateAvailability() (include/triggers.inc.php) - the same algorithm behind the
 * classic "Availability report", so results match what the rest of Zabbix would show.
 */
class AvailabilityReport extends ReportType {

	private const TRIGGER_LIMIT = 25;

	public function getData(array $filter): array {
		$triggers = API::Trigger()->get([
			'output' => ['triggerid', 'description', 'priority'],
			'selectHosts' => ['hostid', 'name'],
			'groupids' => $filter['groupids'] ?: null,
			'search' => $filter['pattern'] !== '' ? ['description' => $filter['pattern']] : null,
			'monitored' => true,
			'sortfield' => 'description',
			'limit' => self::TRIGGER_LIMIT
		]);

		return [
			'triggers' => $triggers,
			'time_from' => $filter['time_from'],
			'time_to' => $filter['time_to']
		];
	}

	public function compute(array $data): array {
		$rows = [];

		foreach ($data['triggers'] as $trigger) {
			$availability = calculateAvailability($trigger['triggerid'], $data['time_from'], $data['time_to']);

			$rows[] = [
				'triggerid' => $trigger['triggerid'],
				'host' => $trigger['hosts'][0]['name'] ?? '',
				'description' => $trigger['description'],
				'priority' => (int) $trigger['priority'],
				'availability' => $availability['false'],
				'downtime' => $availability['true'],
				'downtime_seconds' => (int) $availability['true_time']
			];
		}

		usort($rows, static fn(array $a, array $b) => $a['availability'] <=> $b['availability']);

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		// Export/PDF channels land in a later phase (see ROADMAP.md).
		return $channel === 'interactive' ? $result : null;
	}
}
