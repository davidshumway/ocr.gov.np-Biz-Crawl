<?php
class google_places  {
	
	private $arr_bad_str = array( // Array bad strings
		'This API project is not authorized to use this API. Please ensure that this API is activated in the APIs Console: Learn more: https://code.google.com/apis/console',
		'This API project is not authorized to use this API. Please ensure that this API is activated in the APIs Console: Learn more: https://code.google.com/apis/console',
		'You have exceeded your daily request quota for this API.'
	);
	
	private $gapi_key;
	
	private $assoc_results_gpid = array(); // Results associative array, for unique results. By Google Places place_id.
	
	private $regions; // Parent regions object. Contains country-wide businesses and locations of regions/zones/districts.
	
	private $regional_locations_array = array(); // Assoc. array
	
	//~ private $all_lat_lng_pairs_array = array(); // Assoc. array
	
	private $tmp_folder;
	
	private $fp; // Global file pointer
	
	private $place_details_fields = array(
		'name',
		'formatted_address',
		'vicinity',
		'formatted_phone_number',
		'international_phone_number',
		'location-lat-lng',
		'permanently_closed',
		'rating',
		'types', // Returns an array of types
		'url', // Google page
		'website', // Business website, if available
		'place_id'
	);
	
	/**
	 * 
	 */
	function __construct($regional_locations_array, $gapi_key, $tmp_folder) {
		$this->regional_locations_array = $regional_locations_array;
		$this->gapi_key = $gapi_key;
		$this->tmp_folder = $tmp_folder;
		$this->google_places();
	}
	/**
	 * function google_places
	 * 
	 * Reference is at:
	 * https://developers.google.com/maps/documentation/javascript/places
	 * 
	 * NOTE:
	 * "keyword — A term to be matched against all content that Google has indexed for this place, including but not limited to name, type, and address, as well as customer reviews and other third-party content."
	 * "name — One or more terms to be matched against the names of places, separated by a space character. Results will be restricted to those containing the passed name values. Note that a place may have additional names associated with it, beyond its listed name. The API will try to match the passed name value against all of these names. As a result, places may be returned in the results whose listed names do not match the search term, but whose associated names do."
	 * "types — Restricts the results to places matching at least one of the specified types. Types should be separated with a pipe symbol (type1|type2|etc). "
	 * 
	 * TODO: Add search keyword, e.g. "construction", and then search with no types.
	 */
	function google_places() {
		if (!$this->gapi_key) {
			echo 'Skipping Google Places search.'."\n";
			return;
		}
		// If still  here then start Google Place
		echo 'Starting Google Places search.'."\n";
		
		// CSV
		// ??? 'establishment','store','electronics_store'
		
		$csv = 'businesses-on-google-places.csv';
		$this->fp = fopen($csv, 'w');
		$types1 = [
			'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		];
		$keywords = [
			// TOOLS SEARCH:
			'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building","green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering OR maintenance OR welder OR "heavy equipment" OR tractor OR "heavy truck" OR "semi truck" OR "dump truck" OR digger OR deere OR backhoe OR shovel OR wheelbarrow OR excavator OR ditch'//,
			//~ // TENT & MATERIALS SEARCH:
			//~ 'tent OR tents OR tenting OR tarp OR tarps OR tarpaulin OR tarpauline OR "building supplies" OR material OR fabric OR fabrics OR outdoor OR upholstery OR cotton OR textile OR textiles OR quilt OR shelter OR survival OR backpacking OR nylon OR silnylon OR hammock OR stakes OR camping OR tarptent OR canopy OR canopies OR weatherproof'
		];

		// Write header to CSV.
		fputcsv($this->fp, $this->place_details_fields);
		
		// Loop regional lat/lng pairs
		// $this->regional_locations_array
		// String lat/lng.
		foreach ($this->regional_locations_array as $address => $str_lat_lng) {
			// Loop types
			foreach ($types1 as $key => $type) {
				
				// Search for places
				$d1 = $this->google_places_init_search($str_lat_lng, $type, $address, false); // 
				// Get the biz details for each search result, and write
				$this->get_biz_and_write($d1);
			}
			// Loop keyword searches
			foreach ($keywords as $key => $keyword) {
				
				// Search for places
				$d1 = $this->google_places_init_search($str_lat_lng, '', $address, $keyword); // Set $keyword argument
				// Get the biz details for each search result, and write
				$this->get_biz_and_write($d1);
			}
		}
		// Close fp.
		fclose($this->fp);
	}
	/**
	 * function get_biz_and_write
	 */
	function get_biz_and_write($search_results) { //=Array of objects {place_id:}
		// Loop search results
		// Retrieve details on each place using its place_id
		foreach ($search_results as $key1 => $gp) {
			// Biz exists in output array?
			if (array_key_exists($gp->place_id, $this->assoc_results_gpid))
				continue;
			else
				$this->assoc_results_gpid[ $gp->place_id ] = true;
			// Get biz
			$biz = $this->google_places_biz_profile($gp->place_id);
			$biz = $biz->result;
			$arr_csv = array();// Output array
			// Loop headers
			foreach ($this->place_details_fields as $key2 => $header_type) {
				switch($header_type) {
					case 'location-lat-lng':
						if 	(
							$header_type == 'location-lat-lng' &&
							property_exists($biz, 'geometry') && // Has geometry
							property_exists($biz->geometry, 'location') // Has location
							)
						{
							$ll = $biz->geometry->location->lat . ',' . $biz->geometry->location->lng;
							array_push($arr_csv, $ll);
						} else
							array_push($arr_csv, ''); // Push blank here.
						break;
					case 'types':
						if (property_exists($biz, 'types')) { // Array
							array_push($arr_csv, implode(' / ', $biz->types)); // Push value.
						} else
							array_push($arr_csv, ''); // Push blank here.
						break;
					default:
						if (property_exists($biz, $header_type)) 
							array_push($arr_csv, $biz->$header_type); // Push value.
						else
							array_push($arr_csv, ''); // Push blank here.
				}
			}
			fputcsv($this->fp, $arr_csv); // Write CSV row to file.
		}
	}
	/**
	 * function google_places_init_search
	 * 
	 * CURL requests.
	 */
	function google_places_init_search($str_lat_lng, $type, $address, $keywords = false, $radius=50000, $last_search=false) {
		// Curl get businesses
		// Opts
		$tmp_fn =
			'https://maps.googleapis.com/maps/api/place/radarsearch/json'.
			'?sensor=false'.
			'&radius='.urlencode($radius).
			'&types='.urlencode($type).//implode('|',$types1).
			'&location='.urlencode($str_lat_lng);
		$tmp_fn = ($keywords && $keywords != '') ? $tmp_fn.'&keyword='.urlencode($keywords) : $tmp_fn; // If keywords != '' then add it to the string.
		$u = $tmp_fn . '&key='.urlencode($this->gapi_key);//echo $u."\n";
		$tmp_fn = md5($tmp_fn);
		$opts = new StdClass;
		$opts->url = $u;
		$opts->filename = $tmp_fn;
		$opts->overwrite_if_strpos = $this->arr_bad_str;
		$opts->do_not_save_if_strpos = $this->arr_bad_str;
		$opts->request_to_file = true;
		$opts->request_from_file = true;
		$opts->folder = $this->tmp_folder;
		$content = curl_get($opts); // status,html
		// Result
		if (property_exists($content, 'isCached')) { // No output.
		} else {
			echo '.';
			if ($content->status != 200) {
				echo $content->html."\n";
				echo $u."\n";
				die('-2-Status != 200. Status='.$content->status);
			}
		}
		$d = json_decode($content->html);
		$this->check_response_bad_str($content->html); // Check for bad strings
		$num_results = count($d->results);
		$d = $d->results;
		echo "\n".'Number of businesses near "'.$address.'" (r='.$radius.') is '.$num_results."\n";
		// Are there more than 200 results? If so then
		// the only way to see more is split this query up.
		// One method to possibly get more is to use smaller radius.
		//
		if ($num_results == 200) { // Perform a second search at a small radius.
			echo 'WARNING: Results > 200'."\n";
			if ($radius <= 1000) { // Is below 1km
				$radius -= 100;
			} else if ($radius <= 5000) { // Is below 5km, Is above 1km
				$radius -= 1000;
			} else { // Is above 50km
				$radius -= 5000;
			}
			//~ $radius -= ($radius > 5000) ? 5000 : 1000;
			//~ $radius -= ($radius > 5000) ? 5000 : 1000;
			if ($radius > 0) {
				$d = array_merge(
					$this->google_places_init_search($str_lat_lng, $type, $address, $keywords, $radius, true),
					$d
				);
			}
			// Return here if a child of parent search.
			// If this is the parent search then it will continue to the bottom
			// and return final.
			if ($last_search) {
				return $d;
			} else { // Original "parent" search
				//~ print_r($d);
				//~ echo count($d);
				//~ exit;
			}
			
			//~ echo 'WARNING: Results > 200'."\n";
			//~ $tmp_fn =
				//~ 'https://maps.googleapis.com/maps/api/place/radarsearch/json'.
				//~ '?sensor=false'.
				//~ '&radius=10000'.
				//~ '&types='.urlencode($type).
				//~ '&location='.urlencode($str_lat_lng);
			//~ // If keywords != '' then add it to the string.
			//~ $tmp_fn = ($keywords && $keywords != '') ? $tmp_fn.'&keyword='.urlencode($keywords) : $tmp_fn; // If keywords != '' then add it to the string.
			//~ $u = $tmp_fn . '&key='.urlencode($this->gapi_key);
			//~ $tmp_fn = md5($tmp_fn);
			//~ $opts = new StdClass;
			//~ $opts->url = $u;
			//~ $opts->filename = $tmp_fn;
			//~ $opts->overwrite_if_strpos = $this->arr_bad_str;
			//~ $opts->do_not_save_if_strpos = $this->arr_bad_str;
			//~ $opts->request_to_file = true;
			//~ $opts->request_from_file = true;
			//~ $opts->folder = $this->tmp_folder;
			//~ $content = curl_get($opts); // status,html
			//~ // Result
			//~ if (property_exists($content, 'isCached')) { // No output.
			//~ } else {
				//~ echo '.';
				//~ if ($content->status != 200) {
					//~ echo $content->html."\n";
					//~ echo $u."\n";
					//~ die('-3-Status != 200. Status='.$content->status);
				//~ }
			//~ }
			//~ $tmpd = json_decode($content->html);
			//~ $this->check_response_bad_str($content->html); // Check for bad strings
			//~ $num_results = count($tmpd->results);
			//~ $tmpd = $tmpd->results;
			//~ echo 'Number of (secondary) businesses near "'.$address.'" is '.$num_results."\n";
			//~ if ($num_results == 200) {
				//~ echo 'WARNING: Results > 200'."\n";
			//~ }
			//~ // array_merge
			//~ array_merge($tmpd, $d);
		}
		return $d;
	}
	/**
	 * 
	 */
	function check_response_bad_str($html) {
		// Has bad strings?
		foreach ($this->arr_bad_str as $key => $str) {
			if (strpos($html, $str) !== false) {
				echo 'ERROR: RESPONSE CONTAINS:'.$str;
				exit;
			}
		}
	}
	/**
	 * function google_places_biz_profile
	 * 
	 * CURL requests.
	 */
	function google_places_biz_profile($place_id) {
		// Curl get businesses
		// https://maps.googleapis.com/maps/api/place/details/json?placeid=ChIJN1t_tDeuEmsRUsoyG83frY4&key=AddYourOwnKeyHere
		// Opts
		$tmp_fn =
			'https://maps.googleapis.com/maps/api/place/details/json'.
			'?placeid='.urlencode($place_id);
		$u = $tmp_fn . '&key='.urlencode($this->gapi_key);
		$tmp_fn = md5($tmp_fn);
		$opts = new StdClass;
		$opts->url = $u;
		$opts->filename = $tmp_fn;
		$opts->overwrite_if_strpos = $this->arr_bad_str;
		$opts->do_not_save_if_strpos = $this->arr_bad_str;
		$opts->request_to_file = true;
		$opts->request_from_file = true;
		$opts->folder = $this->tmp_folder;
		$content = curl_get($opts); // status,html
		// Result
		if (property_exists($content, 'isCached')) { // No output.
		} else {
			echo '.';
			if ($content->status != 200) {
				echo $content->html;
				die('-1-Status != 200. Status='.$content->status);
			}
		}
		$d = json_decode($content->html);
		$this->check_response_bad_str($content->html); // Check for bad strings
		return $d;
	}
}
?>
