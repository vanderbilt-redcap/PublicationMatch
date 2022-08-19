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
	
	public function sendAPIRequest($api_source_type, $api_source_argument) {
		// validate source value
		if ($api_source_type == "grant" && !in_array($api_source_argument, $this->data_source_allow_list, true)) {
			\REDCap::logEvent("Publication Match module", "Failed to send SRI API request -- 'api_source_argument': $api_source_argument -- is not a valid source name. See https://starbrite.app.vumc.org/s/sri/docs#publication-matching");
			return false;
		}
		
		// fetch credentials from server file
		$credentials = $this->getCredentials();
		$api_auth_token = base64_encode($credentials["username"] . ":" . $credentials["password"]);
		
		if($api_source_type == "grant") {
			// declare url for API endpoint;
			$api_endpoint_url = "https://starbrite.app.vumc.org/s/sri/api/pub-match/source/{$api_source_argument}";
		}
		else if($api_source_type == "vunet") {
			// declare url for API endpoint;
			$api_endpoint_url = "https://starbrite.app.vumc.org/s/sri/api/pub-match/vunet/{$api_source_argument}";
		}
		else {
			\REDCap::logEvent("Publication Match module", "Failed to send SRI API request -- 'api_source_type': $api_source_type -- is not a valid source type.");
			return false;
		}
		
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
	
	public function sendAllAPIRequests($projectId) {
		$return_data = [];
		$source_names = $this->getProjectSetting("api_source_names",$projectId);
		$source_type = $this->getProjectSetting("api_source_type",$projectId);
		
		foreach($source_names as $source_name) {
			$return_data[$source_name] = $this->sendAPIRequest($source_type,$source_name);
		}
		
		return $return_data;
	}
	
	public function runProjectCron($projectId) {
		$_GET['pid'] = $projectId;
		
		// send SRI API requests and store returned data in REDCap project
		$from_api = $this->sendAllAPIRequests($projectId);
		
		$rid_field_name = $this->getRecordIdField($projectId);
		$dateField = $this->getProjectSetting("publication_save_field",$projectId);
		$vunetField = $this->getProjectSetting("vunet_save_field",$projectId);
		$pmidField = $this->getProjectSetting("pmid_save_field",$projectId);
		$titleField = $this->getProjectSetting("title_save_field",$projectId);
		$data_to_save = [];
		
		foreach($from_api as $thisSource => $apiData) {
			$apiData = json_decode($apiData,true);
			foreach($apiData["data"][0]["publications"] as $thisPublication) {
				
				$data_to_save[$thisPublication["pubMedId"]] = [
					$rid_field_name => $thisPublication["pubMedId"],
					$dateField => $thisPublication["publishedDate"],
					$vunetField => $thisPublication["matchedVunet"],
					$pmidField => $thisPublication["pubMedId"],
					$titleField => $thisPublication["title"]
				];
			}
		}
		
		$save_params = [
			"project_id" => $projectId,
			"dataFormat" => "json",
			"data" => json_encode($data_to_save),
			"overwriteBehavior" => "overwrite"
		];
		$save_result = \REDCap::saveData($save_params);
		
		$errors = $save_result["errors"];
		if (!empty($errors)) {
			\REDCap::logEvent("Publication Match module", "REDCap encountered issues trying to save today's API data pull: " . print_r($errors, true) . "\n -- REDCap::saveData arguments: " . print_r($save_params, true));
		}
	}
	
	public function dailyFetchCron($cronInfo) {
		$originalPid = $_GET['pid'];

		foreach($this->getProjectsWithModuleEnabled() as $localProjectId){
			echo "<br />Running PubMed Update for $localProjectId ";
			$this->runProjectCron($localProjectId);
		}

		// Put the pid back the way it was before this cron job (likely doesn't matter, but is good housekeeping practice)
		$_GET['pid'] = $originalPid;

		return "The \"{$cronInfo['cron_description']}\" cron job completed successfully.";
	}
}
