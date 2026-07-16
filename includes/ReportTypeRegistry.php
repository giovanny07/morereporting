<?php

namespace Modules\MoreReporting\Includes;

/**
 * Maps a saved report definition's `report_type` key to its display label and the
 * action that runs it. New ReportType implementations register here so they become
 * selectable in the report builder without any other part of the module knowing about
 * them.
 */
class ReportTypeRegistry {

	public const PERCENTILES = 'percentiles';
	public const AVAILABILITY = 'availability';

	private const TYPES = [
		self::PERCENTILES => [
			'action' => 'morereporting.percentiles'
		],
		self::AVAILABILITY => [
			'action' => 'morereporting.availability'
		]
	];

	public static function labels(): array {
		return [
			self::PERCENTILES => _('Item percentiles'),
			self::AVAILABILITY => _('Trigger availability')
		];
	}

	public static function exists(string $type): bool {
		return array_key_exists($type, self::TYPES);
	}

	public static function action(string $type): ?string {
		return self::TYPES[$type]['action'] ?? null;
	}
}
