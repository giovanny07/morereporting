<?php

namespace Modules\MoreReporting\Includes\Reports;

use API;
use Modules\MoreReporting\Includes\ReportType;

/**
 * Percentile report: for numeric items matching the filter, computes count/min/avg/max
 * and p50/p90/p95/p99 over the raw history values in the requested time window.
 */
class ItemPercentilesReport extends ReportType {

	private const ITEM_LIMIT = 25;

	public function getData(array $filter): array {
		$items = API::Item()->get([
			'output' => ['itemid', 'hostid', 'name', 'value_type'],
			'selectHosts' => ['hostid', 'name'],
			'groupids' => $filter['groupids'] ?: null,
			'hostids' => $filter['hostids'] ?: null,
			'search' => $filter['patterns'] ? ['name' => $filter['patterns']] : null,
			'searchWildcardsEnabled' => true,
			'searchByAny' => true,
			'filter' => [
				'value_type' => [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64],
				'status' => ITEM_STATUS_ACTIVE
			],
			'monitored' => true,
			'sortfield' => 'name',
			'limit' => self::ITEM_LIMIT
		]);

		$items_by_value_type = [];

		foreach ($items as $item) {
			$items_by_value_type[$item['value_type']][] = $item['itemid'];
		}

		$history = [];

		// Phase 1 reads raw history and computes percentiles in PHP. A time window longer than
		// the item's history retention should switch to API::Trend() instead; left for a later phase.
		foreach ($items_by_value_type as $value_type => $itemids) {
			$values = API::History()->get([
				'output' => 'extend',
				'history' => $value_type,
				'itemids' => $itemids,
				'time_from' => $filter['time_from'],
				'time_till' => $filter['time_to'],
				'sortfield' => 'clock',
				'sortorder' => 'ASC'
			]);

			foreach ($values as $value) {
				$history[$value['itemid']][] = (float) $value['value'];
			}
		}

		return [
			'items' => $items,
			'history' => $history
		];
	}

	public function compute(array $data): array {
		$rows = [];

		foreach ($data['items'] as $item) {
			$values = $data['history'][$item['itemid']] ?? [];

			if (!$values) {
				continue;
			}

			sort($values);

			$count = count($values);

			$rows[] = [
				'itemid' => $item['itemid'],
				'host' => $item['hosts'][0]['name'] ?? '',
				'name' => $item['name'],
				'count' => $count,
				'min' => $values[0],
				'avg' => array_sum($values) / $count,
				'max' => $values[$count - 1],
				'p50' => $this->percentile($values, 50),
				'p90' => $this->percentile($values, 90),
				'p95' => $this->percentile($values, 95),
				'p99' => $this->percentile($values, 99)
			];
		}

		usort($rows, static fn(array $a, array $b) => $b['p95'] <=> $a['p95']);

		return $rows;
	}

	public function render(array $result, string $channel): mixed {
		// Export/PDF channels land in a later phase (see ROADMAP.md).
		return $channel === 'interactive' ? $result : null;
	}

	private function percentile(array $sorted_values, float $p): float {
		$count = count($sorted_values);
		$index = (int) ceil(($p / 100) * $count) - 1;
		$index = max(0, min($count - 1, $index));

		return $sorted_values[$index];
	}
}
