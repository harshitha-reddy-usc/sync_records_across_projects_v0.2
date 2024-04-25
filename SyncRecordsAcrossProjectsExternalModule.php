<?php
namespace Vanderbilt\SyncRecordsAcrossProjectsExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once dirname(__FILE__) . '/hooks_common.php';

class SyncRecordsAcrossProjectsExternalModule extends AbstractExternalModule
{
	public $modSettings;

	function __construct() {
		parent::__construct();
		if(defined("PROJECT_ID")) {
			$this->modSettings = $this->getPipingSettings(PROJECT_ID);
		}
	}
	
	function redcap_every_page_before_render($project_id) {
		$user_is_at_record_status_dashboard = $_SERVER['SCRIPT_NAME'] == APP_PATH_WEBROOT . "DataEntry/record_status_dashboard.php";
		$sync_all_button_configured = $this->getProjectSetting('sync-all-records-button');
		
		if ($user_is_at_record_status_dashboard && $sync_all_button_configured) {
			// add 'Sync All Records' button to record status dashboard screen (should appear next to the '+Add New Record' button
			$sync_all_records_ajax_url = $this->getUrl('php/sync_all_records.php');
			$css_url = $this->getUrl('css/sync_all_data_ajax.css');
			$javascript_file_contents = file_get_contents($this->getModulePath() . 'js/record_status_dashboard.js');
			$javascript_file_contents = str_replace("AJAX_ENDPOINT", $sync_all_records_ajax_url, $javascript_file_contents);
			
			echo "<script type='text/javascript'>$javascript_file_contents</script>";
			echo "<link rel='stylesheet' href='$css_url'>";
		}
	}

	/**
	 * Generates nested array of settings keys. Used for multi-level sub_settings.
	 */
	function getKeysFromConfig($config) {
		foreach($config['sub_settings'] as $subSetting){
			if(!empty($subSetting['sub_settings'])) {
				$keys[] = array('key' => $subSetting['key'], 'sub_settings' => $this->getKeysFromConfig($subSetting));
			} else {
				$keys[] = array('key' => $subSetting['key'], 'sub_settings' => array());
			}
		}
		return $keys;
	}

	/**
	 * Used for processing nested sub_settings while generating settings data array.
	 */
	function processSubSettings($rawSettings, $key, $inc, $depth = 0) {
		$returnArr = array();
		$eachData = $rawSettings[$key['key']]['value'];
		foreach($inc AS $i) {
			$eachData = $eachData[$i];
		}
		foreach($eachData AS $k => $v) {
			foreach($key['sub_settings'] AS $skey => $sval) {
				if(!empty($sval['sub_settings'])) {
					$sinc = $inc;
					$sinc[] = $k;
					$depth++;
					$returnArr[$k][$sval['key']] = $this->processSubSettings($rawSettings, $sval, $sinc, $depth);
					$depth--;
				} else {
					$retData = $rawSettings[$sval['key']]['value'];
					foreach($inc AS $i) {
						$retData = $retData[$i];
					}
					$returnArr[$k][$sval['key']] = $retData[$k];
				}
			}
		}
		return $returnArr;
	} 

	/**
	 * Get full nested settings/sub_settings data.
	 */
	function getPipingSettings($project_id) {
		$keys = [];
		$config = $this->getSettingConfig('sync-projects');
		$keys = $this->getKeysFromConfig($config);
		$subSettings = [];
		$rawSettings = ExternalModules::getProjectSettingsAsArray([$this->PREFIX], $project_id);
		$subSettingCount = count((array)$rawSettings[$keys[0]['key']]['value']);
		$this->syncOnStatus = (isset($rawSettings['sync-on-status']['value'])) ? $rawSettings['sync-on-status']['value'] : 0 ;
		for($i=0; $i<$subSettingCount; $i++){
			$subSetting = [];
			foreach($keys as $key){
				if(!empty($key['sub_settings'])) {
					$subSetting[$key['key']] = $this->processSubSettings($rawSettings, $key, array($i));
				} else {
					$subSetting[$key['key']] = $rawSettings[$key['key']]['value'][$i];
				}
			}
			$subSettings[] = $subSetting;
		}
		return $subSettings;
	}

	function getFormStatusAllRecords($active_forms) {
		if (empty($active_forms)) {
			global $Proj;
			$active_forms = array_keys($Proj->forms);
		}

		$fields = [];
		foreach($active_forms as $form_name) {
			$fields[] = $form_name . "_complete";
		}
		$data = \REDCap::getData('array', null, $fields);

		return $data;
	}


	function validateSettings($settings){
		error_reporting(E_ALL);
		if(!SUPER_USER){
			if(defined('USERID')) {
				$userID = USERID;
			} else {
				return "No User ID Defined!";
			}
			$projectIds = $settings['project-id'];
			foreach($projectIds AS $proj_id) {
				if(!empty($proj_id) && $proj_id != 'null') {
					$rights = \UserRights::getPrivileges($proj_id, $userID);
					if(empty($rights) || $rights[$proj_id][$userID]['design'] != 1){
						return "You must have design rights for every source project in order to save this module's settings.";
					}
				}
			}
		}

		return parent::validateSettings($settings);
	}
	
	// the functions below are used by the Sync All Records button (only)
	
	function getProjects() {
		// prepare array that will be returned
		$projects = [
			'destination' => [],
			'source' => [],
		];
		
		global $Proj;
		$projects['destination']['project_id'] = $this->getProjectId();
		
		$project_ids = $this->getProjectSetting('project-id');
		$dest_match_fields = $this->getProjectSetting('field-match');
		$source_match_fields = $this->getProjectSetting('field-match-source');
		$dest_match_fields_secondary = $this->getProjectSetting('field-match-destination-secondary');
		$source_match_fields_secondary = $this->getProjectSetting('field-match-source-secondary');
		$number_secondary_matches = $this->getProjectSetting('number-secondary-matches');
		$dest_fields = $this->getProjectSetting('data-destination-field');
		$source_fields = $this->getProjectSetting('data-source-field');
		$cross_match_status = $this->getProjectSetting('cross-match-status');
		$cross_match_id = $this->getProjectSetting('cross-match-id');
		$cross_match_number = $this->getProjectSetting('cross-match-number');
		$cross_match_fields = $this->getProjectSetting('cross-match-fields');
		
		// fill $projects['source'] array with source project info arrays
		foreach ($project_ids as $project_index => $pid) {
			$source_project = [
				'project_id' => $pid,
				'source_match_field' => $source_match_fields[$project_index],
				'dest_match_field' => $dest_match_fields[$project_index],
				'dest_fields' => $dest_fields[$project_index],
				'source_match_field_secondary' => $source_match_fields_secondary[$project_index],
				'dest_match_field_secondary' => $dest_match_fields_secondary[$project_index],
				'number_secondary_matches' => $number_secondary_matches[$project_index],
				'source_fields' => $source_fields[$project_index],
				'cross_match_status' => $cross_match_status[$project_index],
				'cross_match_id' => $cross_match_id[$project_index],
				'cross_match_number' => $cross_match_number[$project_index],
				'cross_match_fields' => $cross_match_fields[$project_index],
				'dest_forms_by_field_name' => []
			];

			
			// where source data/match fields are empty, use destination match/data field names
			if (empty($source_project['source_match_field'])) {
				$source_project['source_match_field'] = $source_project['dest_match_field'];
			}
			foreach ($source_project['source_fields'] as $list_index => $field_name) {
				// set to destination name if no alternate name used for source project
				$matching_destination_field_name = $source_project['dest_fields'][$list_index];
				if (empty($field_name)) {
					$source_project['source_fields'][$list_index] = $matching_destination_field_name;
				}

				foreach ($source_project['source_match_field_secondary'] as $list_ind => $sec_field_name) {
					if (empty($sec_field_name)) {
						$source_project['source_match_field_secondary'][$list_ind] = $source_project['dest_match_field_secondary'][$list_ind];
					}
				}
				
				// add an entry to dest_forms_by_field_name for this source field
				$actual_field_name = $source_project['dest_fields'][$list_index];
				$source_project['dest_forms_by_field_name'][$actual_field_name] = $Proj->metadata[$matching_destination_field_name]['form_name'];
			}
			
			// add event id/name pairs
			$source_project['events'] = [];
			$project_obj = new \Project($pid);
			foreach ($project_obj->events[1]['events'] as $event_id => $event_array) {
				$source_project['events'][$event_id] = $event_array['descrip'];
			}
			
			// add 'valid_match_events' array to this source project -- this will contain the event_id values associated with each form that contains the destinatiion match field
			$valid_match_event_ids = [];
			$dest_match_field_form = $Proj->metadata[$source_project['dest_match_field']]['form_name'] ?? null;
			foreach ($Proj->eventsForms as $eid => $formlist) {
				if (in_array($dest_match_field_form, $formlist) !== false) {
					$dst_event_name = $Proj->eventInfo[$eid]['name_ext'];
					if (!empty($dst_event_name)) {
						foreach ($project_obj->eventInfo as $eid2 => $info) {
							if ($info['name_ext'] === $dst_event_name)
								$src_eid = $eid2;
						}
						if (!empty($src_eid)) {
							$valid_match_event_ids[] = $src_eid;
						}
					}
				}
			}
			$source_project['valid_match_event_ids'] = $valid_match_event_ids;
			
			// store project info array in projects['source'] array
			$projects['source'][] = $source_project;
			unset($project_obj);
		}
		
		// for destination project, prepare list of forms to limit piping to
		// and remember which form statuses are ok to sync on (incomplete, complete, etc)
		$active_forms = $this->getProjectSetting('active-forms');
		if (!empty($active_forms)) {
			$projects['destination']['active_forms'] = $active_forms;
		}

		$projects['destination']['sync_on_status'] = $this->getProjectSetting('sync-on-status');
		
		// add event id/names to destination project from global Project instance ($Proj is the destination/host project)
		foreach($Proj->events as $arm_number => $arm_details) {
			foreach ($arm_details['events'] as $event_id => $event_array) {
				$projects['destination']['events'][$event_id] = $event_array['descrip'];
				$projects['destination']['event_details'][$event_id] = [
					"arm"=>$arm_number,
					"name"=>$event_array['descrip'],
					"unique_name"=>strtolower(str_replace(" ", "_", $event_array['descrip']) . "_arm_$arm_number")
				];
			}
		}
		return $projects;
	}
	

	function getDestinationProjectData() {
		if (gettype($this->projects) == 'Array') {
			throw new \Exception("The Sync Records Across Projects module expected \$module->projects to be an array before calling syncToRecord()");
		}
		
		// get all destination match field names
		$match_field_names = [];
		foreach ($this->projects['source'] as $project_index => $project) {
			$match_field_names[] = $project['dest_match_field'];
		}

		$match_field_names = array_merge($match_field_names, $project['dest_match_field_secondary']);
		
		$params = [
			'project_id' => $this->projects['destination']['project_id'],
			'return_format' => 'array',
			'fields' => $match_field_names
		];
		$data = \REDCap::getData($params);
		
		// extract match field info from event arrays
		foreach($data as $rid => $events) {
			$match_info = [];
			foreach ($events as $eid => $recdata) {
				foreach($recdata as $field => $value) {
					$match_info["$field"] = $value;
				}
			}
			$data[$rid] = $match_info;
		}
		
		$this->projects['destination']['records_match_fields'] = $data;
	}

	
	function getSourceProjectsData() {
		if (gettype($this->projects['source']) == 'Array') {
			throw new \Exception("The Sync Records Across Projects module expected \$module->projects['source'] to be an array before calling syncToRecord()");
		}

		// fetch sync and match data for all records in each source project
		foreach ($this->projects['source'] as $project_index => $project) {
			$project_id = $project['project_id'];

			$match_field = $project['source_match_field'];
			$fields = array_merge($project['source_fields'], $project['source_match_field_secondary']);
			if (!in_array($match_field, $fields)) {
				$fields[] = $match_field;
			}

			$params = [
				'project_id' => $project_id,
				'return_format' => 'array',
				'fields' => $fields
			];
			$this->projects['source'][$project_index]['source_data'] = \REDCap::getData($params);

		}
	}


	function syncToRecord($dst_rid) {
		if (gettype($this->projects) == 'Array') {
			throw new \Exception("The Sync Records Across Projects module expected \$module->projects to be an array before calling syncToRecord()");
		}
		
		// return early if this record has all empty destination match field values
		$record_match_info = $this->projects['destination']['records_match_fields'][$dst_rid];
		if (empty($record_match_info)) {
			return;
		}
		
		// create the arrays that we'll eventually give to REDCap::saveData
		$data_to_save = [
			"$dst_rid" => []
		];

		#partial match population fields
		$match_status = ' ';
		$matched_ids = [];
		$matched_fields_number = [];
		$matched_fields_names = [];
		
		// for every source project:
		foreach ($this->projects['source'] as $p_index => $src_project) {
			// get the destination match field name
			$dest_match_field = $src_project['dest_match_field'];
			$record_match_value = $record_match_info[$dest_match_field];

			// is the source match field in the set of synced fields?
			$src_match_field = $src_project['source_match_field'];
			$source_match_field_is_in_sync_fields = in_array($src_match_field, $src_project['source_fields'], true) !== false;
			
			// copy sync values from source records whose match field value matches or add partial match details
			foreach ($src_project['source_data'] as $src_rid => $src_rec) {
				// iterate over each event in the source record, add/overwite data for sync fields along the way
				$secondary_data_dest = array_filter(array_intersect_key($record_match_info, array_flip($src_project['dest_match_field_secondary'])));
				foreach ($src_rec as $eid => $field_data) {
					// if this eid corresponds to a destination project event.. copy data to save to destination record
					$src_event_name = $src_project['events'][$eid];
					$dst_event_id = array_search($src_event_name, $this->projects['destination']['events'], true);
					
					// skip this event if a matching event name wasn't found in the destination project
					if (empty($dst_event_id)) {
						continue;
					}
					
					// skip this event if the event_id isn't valid (eid is only valid if it has the same name as the name of the event contains the form that contains the destination match field)
					if (in_array($eid, (array) $src_project['valid_match_event_ids']) === false) {
						continue;
					}
					
					if ($record_match_value != $field_data[$src_match_field]) {
						$secondary_data_source = array_filter(array_intersect_key($field_data, array_flip($src_project['source_match_field_secondary'])));
						$matched_values = array_uintersect($secondary_data_source, $secondary_data_dest, 'strcasecmp');
						$matched_keys = array_keys($matched_values);
						$match_count = count($matched_keys);

						if ($match_count >= intval($src_project['number_secondary_matches'])) {
							$data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_status']] = 'partial';
							$data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_id']] .= "$field_data[$src_match_field] \n";
							$data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_number']] .= "$match_count \n";
							$data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_fields']] .=  implode(",", $matched_keys) . "\n";
						}
					}
					else {
						$data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_status']] = 'exact';
						unset($data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_id']]);
						unset($data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_number']]);
						unset($data_to_save[$dst_rid][$dst_event_id][$src_project['cross_match_fields']]);

						foreach ($field_data as $field_name => $field_value) {
							// skip this field if it's the match field and match field isn't in the set of fields to be synced
							if ($field_name == $src_match_field && !$source_match_field_is_in_sync_fields) {
								continue;
							}
							if (in_array($field_name, $src_project['source_match_field_secondary'], true) !== false && in_array($field_name, $src_project['source_fields'], true) === false) {
								continue;
							}
	
							// get the destination project's name for this source sync field
							$sync_field_index = array_search($field_name, $src_project['source_fields'], true);
							$dst_name = $src_project['dest_fields'][$sync_field_index];
	
							// skip this field if the destination record's form status for the containing form is above the sync limit
							$form_name = $src_project['dest_forms_by_field_name'][$dst_name];
	
							if (intval($this->formStatuses[$dst_rid][$dst_event_id][$form_name . '_complete']) > $this->sync_on_status) {
								continue;
							}
							
							// skip if this field isn't in an 'active' form
							if (!empty($this->active_forms) && !in_array($form_name, $this->active_forms)) {
								continue;
							}
							
							if (!empty($dst_name)) {
								$data_to_save[$dst_rid][$dst_event_id][$dst_name] = $field_value;
							}
						}
						return \REDCap::saveData('array', $data_to_save);
					}
				}
			}
		}
		if (!empty($data_to_save[$dst_rid])) {
			return \REDCap::saveData('array', $data_to_save);
		}

	}

}
