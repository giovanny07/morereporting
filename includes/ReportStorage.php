<?php

namespace Modules\MoreReporting\Includes;

/**
 * Persistence for saved report definitions (name + report type + scope/config + status).
 * Uses raw DBexecute()/DBselect() rather than the DB:: OOP wrapper, because DB::insert()/
 * DB::update()/DB::reserveIds() all require the table to be registered in core's
 * schema.inc.php, which a module cannot do. Schema installs and migrates lazily on first
 * use - no separate bootstrap step required. MySQL only for now (matches the test
 * environment); PostgreSQL support is not verified yet.
 */
class ReportStorage {

	private const TABLE = 'morereporting_report';

	private static bool $schema_checked = false;

	/**
	 * @param array $filter  Optional: 'name' (substring), 'report_type', 'status', 'userid'.
	 */
	public static function getAll(array $filter = []): array {
		self::ensureSchema();

		$where = [];

		if (array_key_exists('name', $filter) && $filter['name'] !== '') {
			$where[] = 'name LIKE '.zbx_dbstr('%'.$filter['name'].'%');
		}

		if (array_key_exists('report_type', $filter) && $filter['report_type'] !== '') {
			$where[] = 'report_type='.zbx_dbstr($filter['report_type']);
		}

		if (array_key_exists('status', $filter) && $filter['status'] !== '') {
			$where[] = 'status='.(int) $filter['status'];
		}

		if (array_key_exists('userid', $filter) && $filter['userid'] !== '') {
			$where[] = 'userid='.(int) $filter['userid'];
		}

		$sql = 'SELECT reportid,name,report_type,config,status,userid,created_at,updated_at'.
			' FROM '.self::TABLE;

		if ($where) {
			$sql .= ' WHERE '.implode(' AND ', $where);
		}

		$sql .= ' ORDER BY name';

		$rows = DBfetchArray(DBselect($sql));

		foreach ($rows as &$row) {
			$row['config'] = json_decode($row['config'], true) ?: [];
		}
		unset($row);

		return $rows;
	}

	public static function get(int $reportid): ?array {
		self::ensureSchema();

		$row = DBfetch(DBselect(
			'SELECT reportid,name,report_type,config,status,userid,created_at,updated_at'.
			' FROM '.self::TABLE.
			' WHERE reportid='.$reportid
		));

		if (!$row) {
			return null;
		}

		$row['config'] = json_decode($row['config'], true) ?: [];

		return $row;
	}

	public static function create(string $name, string $report_type, array $config, int $status, int $userid): int {
		self::ensureSchema();

		$now = time();

		DBexecute(
			'INSERT INTO '.self::TABLE.' (name,report_type,config,status,userid,created_at,updated_at)'.
			' VALUES ('.
				zbx_dbstr($name).','.
				zbx_dbstr($report_type).','.
				zbx_dbstr(json_encode($config)).','.
				$status.','.
				$userid.','.
				$now.','.
				$now.
			')'
		);

		$row = DBfetch(DBselect('SELECT LAST_INSERT_ID() AS id'));

		return (int) $row['id'];
	}

	public static function update(int $reportid, string $name, string $report_type, array $config, int $status): void {
		self::ensureSchema();

		DBexecute(
			'UPDATE '.self::TABLE.
			' SET name='.zbx_dbstr($name).
				',report_type='.zbx_dbstr($report_type).
				',config='.zbx_dbstr(json_encode($config)).
				',status='.$status.
				',updated_at='.time().
			' WHERE reportid='.$reportid
		);
	}

	public static function setStatus(int $reportid, int $status): void {
		self::ensureSchema();

		DBexecute(
			'UPDATE '.self::TABLE.
			' SET status='.$status.',updated_at='.time().
			' WHERE reportid='.$reportid
		);
	}

	public static function delete(int $reportid): void {
		self::ensureSchema();

		DBexecute('DELETE FROM '.self::TABLE.' WHERE reportid='.$reportid);
	}

	private static function ensureSchema(): void {
		if (self::$schema_checked) {
			return;
		}

		self::$schema_checked = true;

		if (!DBfetch(DBselect("SHOW TABLES LIKE ".zbx_dbstr(self::TABLE)))) {
			DBexecute(
				'CREATE TABLE '.self::TABLE.' ('.
					'reportid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,'.
					'name VARCHAR(255) NOT NULL,'.
					'report_type VARCHAR(64) NOT NULL,'.
					'config MEDIUMTEXT NOT NULL,'.
					'status TINYINT UNSIGNED NOT NULL DEFAULT 0,'.
					'userid BIGINT UNSIGNED NOT NULL,'.
					'created_at INT UNSIGNED NOT NULL,'.
					'updated_at INT UNSIGNED NOT NULL,'.
					'PRIMARY KEY (reportid)'.
				') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
			);

			return;
		}

		// Migration for installations created before the `status` column existed.
		if (!DBfetch(DBselect("SHOW COLUMNS FROM ".self::TABLE." LIKE 'status'"))) {
			DBexecute('ALTER TABLE '.self::TABLE.' ADD COLUMN status TINYINT UNSIGNED NOT NULL DEFAULT 0');
		}
	}
}
