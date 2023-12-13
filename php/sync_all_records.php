<?php
header('Content-Type: application/json');

// get information about configured source projects
$module->projects = $module->getProjects();
$module->getSourceProjectsData();
$module->getDestinationProjectData();

// prepare the information necessary to implement active form filtering and form status filtering (as configured in module)
$module->active_forms = $module->getProjectSetting('active-forms');
if (count($module->active_forms) == 1 && empty($module->active_forms[0])) {		// framework-version 2 can return an array that's not quite empty ([[0] => null])
	$module->active_forms = [];
}
$module->sync_on_status = $module->getProjectSetting('sync-on-status');
$module->formStatuses = $module->getFormStatusAllRecords($module->active_forms);
$verbose_failure_logging = $module->getProjectSetting("verbose-sync-all-failure-logging");

$failures = 0;
$successes = 0;
$sync_attempts = 0;

foreach ($module->projects['destination']['records_match_fields'] as $rid => $info) {
	$save_result = $module->syncToRecord($rid);
	$sync_attempts++;
	# Quick-Fix for PHP8 Support
	$ids = (array) $save_result['ids'];
	if (reset($ids) == $rid) {
		$successes++;
	} elseif (!empty($save_result['errors'])) {
		$failures++;
		if (!empty($verbose_failure_logging)) {
			\REDCap::logEvent("Sync Records Across Projects Module", "Verbose Sync-All piping failure information for record $rid:\n" . implode($save_result['errors'], "\n"));
		}
	}
}

$no_change_records = $sync_attempts - $successes - $failures;
$changed_records = $sync_attempts - $no_change_records;

\REDCap::logEvent("Sync Records Across Projects: Sync All Records",
	"Records synced: $sync_attempts.
	Successes: $successes.
	Failures: $failures.
	Changed / Unchanged records: $changed_records / $no_change_records");

$response = [];
if (empty($errors)) {
	$response['success'] = true;
} else {
	$response['error'] = implode('. ', $errors);
}

echo json_encode($response);