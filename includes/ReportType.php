<?php

namespace Modules\MoreReporting\Includes;

/**
 * Base contract for catalog report types (Phase 1+).
 * Each concrete report type implements 3 layers: data selection, compute/formula
 * and render, so a single computation can feed all 3 output channels
 * (interactive view, export, PDF via Scheduled reports).
 */
abstract class ReportType {

	/**
	 * Fetches raw data from the Zabbix API (history/trend) according to the filter.
	 */
	abstract public function getData(array $filter): array;

	/**
	 * Applies the report's formula/statistic on top of the fetched data.
	 */
	abstract public function compute(array $data): array;

	/**
	 * Formats the computed result for the given output channel
	 * ('interactive', 'export', 'pdf').
	 */
	abstract public function render(array $result, string $channel): mixed;
}
