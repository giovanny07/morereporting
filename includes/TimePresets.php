<?php

namespace Modules\MoreReporting\Includes;

/**
 * Shared list of relative time-range presets (Yesterday, Last 7 days, ...) used both to
 * default a saved report definition's period and as quick-pick links when running a
 * report. Values are relative time expressions parsed by CRangeTimeParser, the same
 * mechanism Zabbix's own time selector uses - so "now-7d" typed directly into a
 * CDateSelector text field works too, not just these presets.
 */
class TimePresets {

	public static function all(): array {
		return [
			_('Yesterday') => ['now-1d/d', 'now-1d/d'],
			_('Last 7 days') => ['now-7d', 'now'],
			_('Last 30 days') => ['now-30d', 'now'],
			_('Last 3 months') => ['now-3M', 'now'],
			_('Last year') => ['now-1y', 'now']
		];
	}
}
