<?php
/**
 * 
 * 
 * TODO: Additional business searches:
 * 	Hydropower or power factory or sub station or power grid or hydro or hydro station or generator or 
 *  Building could also include....: "wood OR lumber OR hardware OR garden??? OR brick"
 *  
 * 
 * TODO: If using local locations rather than regional (i.e. all lat/lng pairs), then do not "drill" down using radar search?
 * This is because there are ~4,000 or so local locations.
 * 
 * UPDATES: May4: Add "lat" and "lng" individual fields to CSV. This is to easily have the ability to "filter" results using lat/lng borders.
 * UPDATES: May4: Add Kolkata and regions east of Nepal.
 */
class google_places  {
	
	private $arr_bad_str = array( // Array bad strings
		'This API project is not authorized to use this API. Please ensure that this API is activated in the APIs Console: Learn more: https://code.google.com/apis/console', // google
		'You have exceeded your daily request quota for this API.', // google
		'"message":"ERROR: canceling statement due to statement timeout"' // geonames
	);
	
	private $gapi_key;
	
	private $geonames_username;
	
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
		'location-lat',
		'location-lng',
		'permanently_closed',
		'rating',
		'types', // Returns an array of types
		'url', // Google page
		'website', // Business website, if available
		'place_id'
	);
	
	//~ private $skip_biz_types = array( //...
		//~ 'bank',
		//~ 'finance'
	//~ );
	
	/**
	 * function __construct
	 */
	function __construct($regional_locations_array, $gapi_key, $tmp_folder, $geonames_username) {
		$this->regional_locations_array = $regional_locations_array;
		$this->gapi_key = $gapi_key;
		$this->tmp_folder = $tmp_folder;
		$this->geonames_username = $geonames_username;
		
		// Building excavating
		//
		//
		$fn = 'businesses-on-google-places.csv';
		$types = [
			'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		];
		$keywords = [
			// TOOLS // ABOUT 1,250 of 4,375 are attributable to keywords
			'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering'
			
			// There is limit about here to number of keywords or length of string...
			// ...'OR maintenance OR welder OR "heavy equipment" OR tractor OR "heavy truck" OR "semi truck" OR "dump truck" OR digger OR deere OR backhoe OR shovel OR wheelbarrow OR excavator OR ditch'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		// Building excavating (types)
		//
		//
		$fn = 'businesses-on-google-places-types_only.csv';
		$types = [
			'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		];
		$keywords = [
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		// Building excavating (keywords)
		//
		//
		$fn = 'businesses-on-google-places-keywords_only.csv';
		$types = [
		];
		$keywords = [
			'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		
		
		// Tent materials
		// Only 260 results.
		// 
		$fn = 'businesses-on-google-places-tent.csv';
		$types = [
		];
		$keywords = [
			// TENT & MATERIALS (limit after last word here).
			'tent OR tents OR tenting OR tarp OR tarps OR tarpaulin OR tarpauline OR "building supplies" OR material OR fabric OR fabrics OR outdoor OR upholstery OR cotton OR textile OR textiles OR quilt OR shelter OR survival OR backpacking OR nylon OR silnylon OR hammock OR stakes OR camping OR tarptent OR canopy OR canopies OR weatherproof'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		
		
		/**
		 * Add Kolkata to geo
		 * Do another set of searching.
		 * A good way to visualize boxes is e.g. http://www.darrinward.com/lat-long/
		 * 
		 * $geonames_username
		 * $this->arr_bad_str_geonames
		 * 
		 * E.g. x4
		 * 144 results:
		 * 'http://api.geonames.org/citiesJSON?north=24.439560&south=20.912610&east=89.558872&west=85.559849&lang=en&username='..'&maxRows=600'
		 * E.g. x5 (incl. Kolkata)
		 * 172 results:
		 * http://api.geonames.org/citiesJSON?north=27.276516&south=24.439560&east=89.558872&west=85.559849&lang=en&username='..'&maxRows=600
		 * 
		 * POINTS:
		 * a  b  C
		 * --------
		 * d  x1 e
		 * --------
		 * x2 x3 x4
		 * --------
		 *       x5
		 * --------
		 */
		$add_locn = array(
			['north'=>'24.439560','south'=>'20.912610','east'=>'89.558872','west'=>'85.559849'], // 5
			['north'=>'27.276516','south'=>'24.439560','east'=>'89.558872','west'=>'85.559849'], // 4
			['north'=>'27.276516','south'=>'24.439560','east'=>'81.560826','west'=>'77.561803'], // 3
			['north'=>'27.276516','south'=>'24.439560','east'=>'85.559849','west'=>'81.560826'], // 2
			['north'=>'30.113472','south'=>'27.276516','east'=>'85.559849','west'=>'81.560826']
		);
		$this->geonames_cities($add_locn); // Adds to $this->regional_locations_array
		
		// Building excavating (types)
		//
		//
		$fn = 'businesses-on-google-places-plus_India-types_only.csv';
		$types = [
			'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		];
		$keywords = [
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		// Building excavating (keywords)
		//
		//
		$fn = 'businesses-on-google-places-plus_India-keywords_only.csv';
		$types = [
		];
		$keywords = [
			'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		// Building excavating (full)
		//
		//
		$fn = 'businesses-on-google-places-plus_India.csv';
		$types = [
			'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		];
		$keywords = [
			'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		
		// Tent materials
		//
		//
		$fn = 'businesses-on-google-places-tent-plus_India.csv';
		$types = [
		];
		$keywords = [
			// TENT & MATERIALS, (limit after last word here).
			'tent OR tents OR tenting OR tarp OR tarps OR tarpaulin OR tarpauline OR "building supplies" OR material OR fabric OR fabrics OR outdoor OR upholstery OR cotton OR textile OR textiles OR quilt OR shelter OR survival OR backpacking OR nylon OR silnylon OR hammock OR stakes OR camping OR tarptent OR canopy OR canopies OR weatherproof'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		
		// DEBUG All lat/lng pairs
		
		/**
		 * ADD TIBET TO THIS
		 * ....
		 */
		$add_locn = array(
			// Add abde (NOT C!)
			['north'=>'30.113472','south'=>'27.276516','east'=>'81.560826','west'=>'77.561803'],  // d
			['north'=>'32.950428','south'=>'30.113472','east'=>'81.560826','west'=>'77.561803'],  // a 2.836956 NORTH
			['north'=>'32.950428','south'=>'30.113472','east'=>'85.559849','west'=>'81.560826'],  // b
			['north'=>'30.113472','south'=>'27.276516','east'=>'89.558872','west'=>'85.559849'],  // e 2.836956 NORTH
		);
		$this->geonames_cities($add_locn); // Adds to $this->regional_locations_array
		
		// Cardboard, Signage
		// 
		//
		$fn = 'businesses-on-google-places-cardboard-plus_India.csv';
		$types = [
		];
		$keywords = [
			// TENT & MATERIALS, (limit after last word here).
			'"cardboard box" OR "cardboard manufacturers" OR "corrugated plastic" OR "paper mill" OR cardboard OR "corrugated packaging" OR corrugated OR packaging OR "paper manufacturers" OR containerboard OR laminated OR flex OR "corrugated plastic packaging" OR "packaging supplies" OR "cardboard manufacturers" OR "corrugated plastic signs"'
		];
		$this->google_places($fn, $types, $keywords, $this->regional_locations_array);
		
		//
		$this->debug_csv_show_locations();
	}
	/**
	 * function debug_csv_show_locations
	 * 
	 */
	function debug_csv_show_locations() {
		// Open
		$fp = fopen('debug_show_addresses.csv', 'w');
		$hd = array(
			'Address',
			'Lat/Lng Geocode'
		);
		// Write header to CSV.
		fputcsv($fp, $hd);
		// Loop addresses
		foreach ($this->regional_locations_array as $address => $geo) {
			$fields = array(
				$address,
				$geo
			);
			fputcsv($fp, $fields);
		}
		// Close fp.
		fclose($fp);
	}
	/**
	 * function geonames_cities
	 * 
	 * Returns cities ordered by population desc.
	 * 
	 * Ex: {"geonames":[{city1_detail},...{cityN_detail}]}
	 */
	function geonames_cities($array_bounding_areas) {
		if (!$this->geonames_username) {
			echo 'Skipping geonames_cities search.'."\n";
			return;
		}
		// If still here then start
		echo 'Starting geonames_cities search.'."\n";
		$formatted_cty = array( // This is for "nice" console output plus $this->regional_locations_array[ "address" ] is unique.
			'NP'=>'Nepal',
			'IN'=>'India',
			'CN'=>'China',
			'BD'=>'Bangladesh',
			'BT'=>'Bhutan',
			'BTN'=>'Bhutan'
			//~ 'TB'=> 'Tibet' // Geonames does not use Tibet.
		);
		foreach ($array_bounding_areas as $key=>$bounding_array) {
			$tmp_fn =
				'http://api.geonames.org/citiesJSON?'.
				'north='.urlencode($bounding_array['north']).
				'&south='.urlencode($bounding_array['south']).
				'&east='.urlencode($bounding_array['east']).
				'&west='.urlencode($bounding_array['west']).
				'&lang=en'.
				'&maxRows=800';
			$u = $tmp_fn . '&username='.urlencode($this->geonames_username);
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
			// Has results?
			$d = json_decode($content->html);
			check_response_bad_str($content->html); // Check for bad strings
			if (!property_exists($d, 'geonames') || count($d->geonames) == 0) {
				die('NO GEONAMES RESULTS!');
			} else {
				echo 'Adding '.count($d->geonames).' addn\'l lat/lng search pairs.'."\n";
			}
			// Looks okay
			foreach ($d->geonames as $key2=>$locn) {
				/**
				 * e.g. {"fcodeName":"populated place","toponymName":"Shiliguri","countrycode":"IN","fcl":"P","fclName":"city, village,...","name":"Siliguri","wikipedia":"en.wikipedia.org/wiki/Siliguri","lng":88.428512,"fcode":"PPL","geonameId":1256525,"lat":26.710035,"population":515574}*/
				$cty = (array_key_exists($locn->countrycode, $formatted_cty)) ? $formatted_cty[$locn->countrycode] : $locn->countrycode;
				$pretty_name = $locn->name . ',' . $cty;
				$this->regional_locations_array[ $pretty_name ] = $locn->lat . ',' . $locn->lng;
			}
		}
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
	function google_places($filename, $types, $keywords, $lat_lng_pairs) { // filename, types, keywords, lat_lng_pairs
		if (!$this->gapi_key) {
			echo 'Skipping Google Places search.'."\n";
			return;
		}
		// If still  here then start Google Place
		echo 'Starting Google Places search.'."\n";
		
		// Reset the key index
		$this->assoc_results_gpid = array();
		
		//~ // CSV
		//~ // ??? 'establishment','store','electronics_store'
		//~ 
		//~ $csv = 'businesses-on-google-places.csv';
		//~ $fp = fopen($csv, 'w');
		//~ $types1 = [
			//~ 'car_repair','electrician','general_contractor','hardware_store','home_goods_store','locksmith','moving_company','plumber','roofing_contractor'
		//~ ];
		//~ $keywords = [
			//~ // TOOLS SEARCH:
			//~ 'construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering OR maintenance OR welder OR "heavy equipment" OR tractor OR "heavy truck" OR "semi truck" OR "dump truck" OR digger OR deere OR backhoe OR shovel OR wheelbarrow OR excavator OR ditch'//,
			// TENT & MATERIALS SEARCH:
			//~ 'tent OR tents OR tenting OR tarp OR tarps OR tarpaulin OR tarpauline OR "building supplies" OR material OR fabric OR fabrics OR outdoor OR upholstery OR cotton OR textile OR textiles OR quilt OR shelter OR survival OR backpacking OR nylon OR silnylon OR hammock OR stakes OR camping OR tarptent OR canopy OR canopies OR weatherproof'
		//~ ];
		
		// Open
		$fp = fopen($filename, 'w');
		// Write header to CSV.
		fputcsv($fp, $this->place_details_fields);
		
		// Loop regional lat/lng pairs
		// $this->regional_locations_array
		// String lat/lng.
		foreach ($lat_lng_pairs as $address => $str_lat_lng) {
			// Loop types
			foreach ($types as $key => $type) {
				
				// Search for places
				$d1 = $this->google_places_init_search($fp, $str_lat_lng, $type, $address, false); // 
				// Get the biz details for each search result, and write
				$this->get_biz_and_write($d1, $fp);
			}
			// Loop keyword searches
			foreach ($keywords as $key => $keyword) {
				
				// Search for places
				$d1 = $this->google_places_init_search($fp, $str_lat_lng, '', $address, $keyword); // Set $keyword argument
				// Get the biz details for each search result, and write
				$this->get_biz_and_write($d1, $fp);
			}
		}
		// Close fp.
		fclose($fp);
	}
	/**
	 * function get_biz_and_write
	 */
	function get_biz_and_write($search_results, $file_handle) { //=Array of objects {place_id:}
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
			// Has result?
			if (!property_exists($biz, 'result')) {
				print_r($biz);
				echo 'No result!';
				continue; // Try skipping?
				//~ exit;
			}
			$biz = $biz->result;
			$arr_csv = array();// Output array
			// Loop headers
			foreach ($this->place_details_fields as $key2 => $header_type) {
				switch($header_type) {
					case 'location-lat':
						if 	(
							property_exists($biz, 'geometry') && // Has geometry
							property_exists($biz->geometry, 'location') // Has location
							)
						{
							array_push($arr_csv, $biz->geometry->location->lat);
						} else
							array_push($arr_csv, ''); // Push blank here.
						break;
					case 'location-lng':
						if 	(
							property_exists($biz, 'geometry') && // Has geometry
							property_exists($biz->geometry, 'location') // Has location
							)
						{
							array_push($arr_csv, $biz->geometry->location->lng);
						} else
							array_push($arr_csv, ''); // Push blank here.
						break;
					case 'location-lat-lng':
						if 	(
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
			fputcsv($file_handle, $arr_csv); // Write CSV row to file.
		}
	}
	/**
	 * function google_places_init_search
	 * 
	 * CURL requests.
	 */
	function google_places_init_search($file_handle, $str_lat_lng, $type, $address, $keywords = false, $radius=50000, $last_search=false) {
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
		//debug
		//if (strpos($keywords, 'tenting') !== false) { echo $u;print_r($content);exit;}
		//~ echo $u;print_r($content);exit;
		$d = json_decode($content->html);
		check_response_bad_str($content->html); // Check for bad strings
		$num_results = count($d->results);
		$d = $d->results;
		echo "\n".'Number of businesses near "'.$address.'" (r='.$radius.') ('.$str_lat_lng.') (t='.$type.') is '.$num_results."\n";
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
					$this->google_places_init_search($file_handle, $str_lat_lng, $type, $address, $keywords, $radius, true),
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
		}
		return $d;
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
				echo $u . "\n";
				echo $content->html . "\n";
				die('-1-Status != 200. Status='.$content->status);
			}
		}
		$d = json_decode($content->html);
		check_response_bad_str($content->html); // Check for bad strings
		return $d;
	}
}
?>
