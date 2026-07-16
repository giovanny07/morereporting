<?php

namespace Modules\MoreReporting\Actions;

use API;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CProfile;
use CWebUser;

use Modules\MoreReporting\Includes\ReportStorage;
use Modules\MoreReporting\Includes\ReportTypeRegistry;

class ReportsList extends CController {

	private const PROFILE_PREFIX = 'web.morereporting.list.filter.';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_name' =>			'string',
			'filter_report_type' =>	'string',
			'filter_status' =>			'in '.ZBX_REPORT_STATUS_ENABLED.','.ZBX_REPORT_STATUS_DISABLED,
			'filter_show' =>			'in '.ZBX_REPORT_FILTER_SHOW_ALL.','.ZBX_REPORT_FILTER_SHOW_MY,
			'filter_set' =>				'in 1',
			'filter_rst' =>				'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		if ($this->hasInput('filter_set')) {
			CProfile::update(self::PROFILE_PREFIX.'name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update(self::PROFILE_PREFIX.'report_type', $this->getInput('filter_report_type', ''),
				PROFILE_TYPE_STR
			);
			CProfile::update(self::PROFILE_PREFIX.'status', $this->getInput('filter_status', ''), PROFILE_TYPE_STR);
			CProfile::update(self::PROFILE_PREFIX.'show', $this->getInput('filter_show', ZBX_REPORT_FILTER_SHOW_ALL),
				PROFILE_TYPE_INT
			);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete(self::PROFILE_PREFIX.'name');
			CProfile::delete(self::PROFILE_PREFIX.'report_type');
			CProfile::delete(self::PROFILE_PREFIX.'status');
			CProfile::delete(self::PROFILE_PREFIX.'show');
		}

		$filter = [
			'name' => CProfile::get(self::PROFILE_PREFIX.'name', ''),
			'report_type' => CProfile::get(self::PROFILE_PREFIX.'report_type', ''),
			'status' => CProfile::get(self::PROFILE_PREFIX.'status', ''),
			'show' => (int) CProfile::get(self::PROFILE_PREFIX.'show', ZBX_REPORT_FILTER_SHOW_ALL)
		];

		$storage_filter = [
			'name' => $filter['name'],
			'report_type' => $filter['report_type'],
			'status' => $filter['status']
		];

		if ($filter['show'] == ZBX_REPORT_FILTER_SHOW_MY) {
			$storage_filter['userid'] = (int) CWebUser::$data['userid'];
		}

		$reports = ReportStorage::getAll($storage_filter);

		$userids = array_unique(array_column($reports, 'userid'));

		$users = $userids
			? API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => $userids,
				'preservekeys' => true
			])
			: [];

		$data = [
			'title' => _('MoreReporting'),
			'filter' => $filter,
			'active_tab' => CProfile::get(self::PROFILE_PREFIX.'active', 1),
			'reports' => $reports,
			'users' => $users,
			'report_type_labels' => ReportTypeRegistry::labels()
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('MoreReporting'));
		$this->setResponse($response);
	}
}
