<?php

namespace Modules\MoreReporting\Includes;

/**
 * Maps a saved report definition's `report_type` key to its display label, the action
 * that runs it, and which optional config fields it actually uses (so the builder form
 * can hide fields that are meaningless for the selected type, e.g. SLO only applies to
 * availability, and the item-pattern vs trigger-pattern field only applies to its own
 * type since they browse different source tables). New ReportType implementations
 * register here so they become selectable without any other part of the module knowing
 * about them.
 */
class ReportTypeRegistry {

	public const PERCENTILES = 'percentiles';
	public const AVAILABILITY = 'availability';

	private const TYPES = [
		self::PERCENTILES => [
			'action' => 'morereporting.percentiles',
			'fields' => ['item_pattern']
		],
		self::AVAILABILITY => [
			'action' => 'morereporting.availability',
			'fields' => ['trigger_pattern', 'slo']
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

	/**
	 * @return array  Optional config field keys used by this type (e.g. ['item_pattern']).
	 */
	public static function fields(string $type): array {
		return self::TYPES[$type]['fields'] ?? [];
	}

	/**
	 * @return array  report_type => fields, for client-side field toggling.
	 */
	public static function fieldsMap(): array {
		return array_map(static fn(array $type) => $type['fields'], self::TYPES);
	}
}
