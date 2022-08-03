<?php
namespace Vanderbilt\PublicationMatch;

class PublicationMatch extends \ExternalModules\AbstractExternalModule {
	
	private $data_source_allow_list = [
		"act_network_inquiry_tools",
		"aou",
		"billing_plan",
		"biovu",
		"coeus",
		"community_engagement",
		"ddrc",
		"drtc",
		"drug_repurposing",
		"esmart",
		"imagevu",
		"irb",
		"lhs",
		"mycap",
		"myresearch_vanderbilt",
		"peer",
		"recruitment_notifications",
		"recruitment_support",
		"redcap",
		"redcap_impactt",
		"redcap_studio",
		"regulatory_support",
		"research_derivative",
		"research_match",
		"rocket",
		"subject_locator",
		"synthetic_derivative",
		"tncfar",
		"vicc",
		"vkc",
		"vrr"
	];
	
	public function getCredentials() {
		$cred_path1 = "/app001/credentials/con_redcap_ldap_user.php";
		$cred_path2 = $this->getModulePath() . "local_credentials.php";
		if (file_exists($cred_path1)) {
			include($cred_path1);
		} elseif (file_exists($cred_path2)) {
			include($cred_path2);
		}
		
		if (empty($ldapuser) or empty($ldappass)) {
			throw new Exception("The Publication Match module couldn't get the LDAP service account credentials from the credentials file!");
		}
		
		$credentials = [
			"username" => $ldapuser,
			"password" => $ldappass
		];
		return $credentials;
	}
	
	public function sendAPIRequest($api_source_argument) {
		// validate source value
		if (!in_array($api_source_argument, $this->data_source_allow_list, true)) {
			\REDCap::logEvent("Publication Match module", "Failed to send SRI API request -- 'api_source_argument': $api_source_argument -- is not a valid source name. See https://starbrite.app.vumc.org/s/sri/docs#publication-matching");
			return false;
		}
		
		// fetch credentials from server file
		$credentials = $this->getCredentials();
		$api_auth_token = base64_encode($credentials["username"] . ":" . $credentials["password"]);
		
		// declare url for API endpoint;
		$api_endpoint_url = "https://starbrite.app.vumc.org/s/sri/api/pub-match/source/{$api_source_argument}";
		
		// create curl resource handle
		$ch = curl_init();
		
		// set curl options
		curl_setopt($ch, CURLOPT_URL, $api_endpoint_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json',
			'Authorization: Basic ' . $api_auth_token
		]);
		
		// send request
		$result = curl_exec($ch);
		
		// close curl handle
		curl_close($ch);
		
		// return result (string)
		return $result;
	}
	
	public function sendAllAPIRequests() {
		$return_data = [];
		$source_names = $this->getProjectSetting("api_source_names");
		
		foreach($source_names as $source_name) {
			$return_data[$source_name] = $this->sendAPIRequest($source_name);
		}
		
		return $return_data;
	}
	
	public function dailyFetchCron($cronInfo) {
		$originalPid = $_GET['pid'];

		foreach($this->getProjectsWithModuleEnabled() as $localProjectId){
			$_GET['pid'] = $localProjectId;

			// send SRI API requests and store returned data in REDCap project
			$from_api = $this->sendAllAPIRequests();
			
			$project_id = $this->getProjectId();
			$new_rid = \REDCap::reserveNewRecordId($project_id);
			if ($new_rid != (int) $new_rid || $new_rid == 0) {
				\REDCap::logEvent("Publication Match module", "Couldn't pick a new record ID to save today's API results in -- cancelling cron job.");
				return;
			}
			
			$rid_field_name = $this->getRecordIdField();
			$data_to_save = json_encode([
				[
					$rid_field_name => $new_rid,
					"data" => json_encode($from_api)
				]
			]);
			
			$save_params = [
				"project_id" => $project_id,
				"dataFormat" => "json",
				"data" => $data_to_save,
				"overwriteBehavior" => "overwrite"
			];
			$save_result = \REDcap::saveData($save_params);
			
			$errors = $save_result["errors"];
			if (!empty($errors)) {
				\REDCap::logEvent("Publication Match module", "REDCap encountered issues trying to save today's API data pull: " . print_r($errors, true) . "\n -- REDCap::saveData arguments: " . print_r($save_params, true));
			}
		}

		// Put the pid back the way it was before this cron job (likely doesn't matter, but is good housekeeping practice)
		$_GET['pid'] = $originalPid;

		return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
	}
}
