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
	
	public function sendAPIRequest($api_source_argument, $api_date_argument) {
		// validate source value
		if (!in_array($api_source_argument, $this->data_source_allow_list, true)) {
			\REDCap::logEvent("Publication Match module", "Failed to send SRI API request -- 'api_source_argument': $api_source_argument -- is not a valid source name. See https://starbrite.app.vumc.org/s/sri/docs#publication-matching");
			return false;
		}
		
		// sanitize date value
		$date_array = explode("-", $api_date_argument);
		// checkdate takes args in month, day, year order
		if (!checkdate((int) $date_array[1], (int) $date_array[2], (int) $date_array[0])) {
			\REDCap::logEvent("Publication Match module", "Failed to send SRI API request -- 'api_date_argument': $api_date_argument -- is not a valid YEAR-MONTH-DAY date value.");
			return false;
		}
		$safe_date_argument = $date_array[1] . "-" . $date_array[2] . "-" . $date_array[0];
		
		// fetch credentials from server file
		$credentials = $this->getCredentials();
		$api_auth_token = base64_encode($credentials["username"] . ":" . $credentials["password"]);
		
		// declare url for API endpoint
		$api_endpoint_url = "https://starbrite.app.vumc.org/s/sri/api/pub-match/user-affiliation/{$api_source_argument}?createdDate={$safe_date_argument}";
		
		// create curl resource handle
		$ch = curl_init();
		
		// set curl options
		curl_setopt($ch, CURLOPT_URL, $api_endpoint_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
}
