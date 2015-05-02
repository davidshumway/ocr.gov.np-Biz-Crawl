<?php
/**
 * ocr.gov.np
 * 
 * Init. with, e.g.: php -f /var/www/ocr.gov.np-Biz-Crawl/ocr.gov.np_biz_retrieval.php -- "/var/www/ocr.gov.np-Biz-Crawl/"
 */
 
// Settings
define('CURRENT_NP_YEAR', 2072); // Enter the current Nepal calendar year, Integer.

// Set max preg string size limit. 10 MB.
// A very long biz match causes regular expression to exceed the max length.
// Default is probably 500 KB to 1000 KB.
//
/**
 * From http://stackoverflow.com/questions/18296441/maximum-length-of-string-preg-match-all-can-match-and-acquire:
 * Set the ini_set('pcre.backtrack_limit', '1048576'); to whatever you want in your script or on your php.ini file for global use. (example is 1mb). Credit to: http://www.karlrixon.co.uk/writing/php-regular-expression-fails-silently-on-long-strings/.
 */
ini_set('pcre.backtrack_limit', 10485760);

// Init. 
if (isset($argv[1])) {
	// Folder = This is the location to save to, when necessary.
	// Google API key. This is for translations.
	$key = false;
	if (isset($argv[2])) {
		$key = $argv[2];
	}
	new regions($argv[1], $key);
}

/**
 * class regions
 * TODO: Save to a MySQL database.
 * TODO: Add businesses to a Google Map.
 * TODO: Consider integrating with the Google Places API.
 */
class regions {
	private $regions;
	
	private $folder; // Root folder
	
	private $data_folder; // Folder for translations and scrape of ocr.gov.np.
	
	private $gapi_key; // Google API key for translate.
	
	private $translations_array = array(); // Translations associative array.
	
	private $locations_array = array(); // Locations associative array.
	
	private $categories; // Categories object
	
	//~ private $geo_folder; // Save geocoded addresses to this folder.
	
	function __construct($folder, $gapi_key = false) {
		
		// Set $this->folder
		$this->folder = $folder; 
		
		// Check that folder ends with "/".
		if ($folder[ strlen($folder)-1 ] != '/') {
			die('ERROR: Folder must end with forward-slash "/":' . $this->folder);
		}
		
		// Make the folder if it does not exist.
		if (!file_exists($this->folder)) {
			if (!mkdir($this->folder)) {
				die('ERROR: Could not create the folder '.$this->folder);
			}
		}
		
		// Make a "tmp" data dir if it does not exist.
		$this->tmp_folder = $folder . 'data/';
		if (!file_exists($this->tmp_folder)) {
			if (!mkdir($this->tmp_folder)) {
				die('ERROR: Could not create the folder '.$this->tmp_folder);
			}
		}
		
		// Set gapi_key
		$this->gapi_key = $gapi_key;
		
		// Init. regions.
		$this->build_regions();
		
		// Initialize categories.
		// Do this after intializing all of the businesses. This is because
		// categories depend on businesses.
		$this->init_categories();
		
		// Tranlsate all business addresses. Uses disk cache whenever possible
		// by saving the CURL-get requests to the "data" folder.
		// Put all translations into the file translations.json.
		$this->translate();
		
		// Geocode approximate addresses.
		$this->geocode_approximate();
		
		// Save businesses to disk as CSV
		$this->csv();
		
		// Save 2-year-old or less businesses to disk as CSV
		$this->csv(2068);
		
		//~ // Make a "geo" data dir if it does not exist.
		//~ $this->geo_folder = $folder . 'geo/';
		//~ if (!file_exists($this->geo_folder)) {
			//~ if (!mkdir($this->geo_folder)) {
				//~ die('ERROR: Could not create the folder '.$this->geo_folder);
			//~ }
		//~ }
	}
	/**
	 * function build_regions
	 */
	function build_regions() {
		$this->regions = [
			['id'=>1,'title'=>'सुदुर-पश्चिमाञ्चल बिकास क्षेत्र','title_en'=>'Far-Western Development Region'],
			['id'=>2,'title'=>'मध्य-पश्चिमाञ्चल बिकास क्षेत्र','title_en'=>'Mid-Western Development Region'],
			['id'=>3,'title'=>'पश्चिमाञ्चल बिकास क्षेत्र','title_en'=>'Western Development Region'],
			['id'=>4,'title'=>'मध्यमाञ्चल बिकास क्षेत्र','title_en'=>'Central Development Region'],
			['id'=>5,'title'=>'पुर्वाञ्चल बिकाष क्षेत्र','title_en'=>'Eastern Development Region'],
			['id'=>6,'title'=>'थाहा नभएको','title_en'=>'Unknown']
		];
		foreach ($this->regions as $key => $region) {
			$this->regions[$key] = new region($region, $this->tmp_folder); // Set each region to a class object.
		}
	}
	/**
	 * function init_categories
	 * 
	 * Get the categories from the SELECT field at http://www.ocr.gov.np/search/advanced_search.php.
	 * JS: var all = {}; var x = document.getElementById('objective').getElementsByTagName('option'); for (var i in x) { if (i=='length')break; var n = x[i].innerText; var o = /^(\d+) - /.exec(n); if (!o) continue; n = n.replace(o[0], ''); o = o[1]; all[o] = n;}; console.log(JSON.stringify(all));
	 */
	function init_categories() {
		$fn = $this->folder . 'categories.json';
		$json = file_get_contents($fn);
		if (!$json) {
			echo 'Could not open categories.json, '.$fn.'!';
			exit;
		}
		$this->categories = json_decode($json);
		$this->translate_categories();
		$this->get_biz_categories();
		
		// Save categories to a .json file, including the translated title of the category, if available.
		$this->csv_categories_translated();
	}
	/**
	 * function csv_categories
	 * 
	 */
	function csv_categories_translated() {
		$csv = 'businesses-categories-translated.csv';
		$fp = fopen($csv, 'w');
		$header = array(
			'ID',
			'Title',
			'Title-English Translated'
		);
		fputcsv($fp, $header); // Header
		foreach($this->categories as $num_ctg => $category_obj) {
			// $fields CSV array
			$fields = array(
				$num_ctg,
				$category_obj->title,
				$category_obj->title_en
			);
			// Write array to CSV
			fputcsv($fp, $fields);
		}
		fclose($fp); // Close
	}
	/**
	 * function translate_categories
	 * 
	 */
	function translate_categories() {
		// Translate?
		if (!$this->gapi_key) {
			echo 'Skipping business address translations, no Google API key specified.'."\n";
			return;
		} else {
			echo
				'Downloading Google translations for business addresses.'."\n".
				'"." denotes a non-cached query. Cached and duplicate addresses are not shown.'."\n";
		}
		// The result is e.g.:
		//~ {
		//~ 	"data": {
		//~ 		"translations": [
		//~ 			{
		//~ 				"translatedText": "Ritachopata, 2, Darchula, Mahakali"
		//~ 			}
		//~ 		]
		//~ 	}
		//~ }
		
		foreach($this->categories as $num_ctg => $category_obj) {
			// Translate
			// Make sure the filename does not include the "key". (Is not dependent on this.)
			$ufilename = 
				'https://www.googleapis.com/language/translate/v2'.
				'&source=ne&target=en'.
				'&q='.urlencode($category_obj->title);
			$u =
				'https://www.googleapis.com/language/translate/v2'.
				'?key='.urlencode($this->gapi_key).
				'&source=ne&target=en'.
				'&q='.urlencode($category_obj->title);
			$opts = new StdClass;
			$opts->url = $u;
			$opts->filename = md5($opts->url);
			$opts->request_to_file = true;
			$opts->request_from_file = true;
			$opts->folder = $this->tmp_folder;
			$content = curl_get($opts); // status,html
			if (property_exists($content, 'isCached')) {
				// Is cached. No output here.
			} else {
				echo '.';
				if ($content->status != 200) {
					die('status != 200, =' . $content->status);
				}
			}
			$d = json_decode($content->html);
			// Add details to this object.
			try {
				$trans = $d->data->translations[0]->translatedText;
				$category_obj->title_en = $trans;
			} catch (Exception $e) {
				print_r($d);
				die('Caught exception: '.  $e->getMessage(). "\n");
			}
		}
	}
	/**
	 * function get_biz_categories_by_year
	 * 
	 * This is probably unnecessary. It seems that all search come through okay requiring
	 * the year-by-year search.
	 */
	function get_biz_categories_by_year($post_id, $num_ctg) {
		// Curl get businesses (POST)
		// Year by year.
		$d = array(); // Array complete businesses.
		// Loop
		for ($i = 1900; $i<=CURRENT_NP_YEAR; $i++) {
			// If i < 2000
			// Then skip forward by x years.
			$yrstart = $i;
			$yrend = $i + 1;
			if ($i < 2040) {
				$i += 20;
				$yrend = $i;
			}
			echo 'Categories by year: '.$yrstart.' to '.$yrend;
			$u =
				'region='.urlencode($post_id).
				'&zone='.
				'&district='.
				'&reg_no=&company_name='.
				'&reg_date_from='.$yrstart.'-01-02'.
				'&reg_date_to='.($yrend).'-01-01'.
				'&company_type='.
				'&objective='.urlencode($num_ctg).
				'&gender=&a_capital_from=&a_capital_to=&i_capital_from='.
				'&i_capital_to=&p_capital_from=&p_capital_to=&btn_submit=Search';
			$tmp_fn = md5('http://www.ocr.gov.np/search/advanced_search.php' . $u);
			// Opts
			$opts = new StdClass;
			$opts->url = 'http://www.ocr.gov.np/search/advanced_search.php';
			$opts->post = true;
			$opts->post_str = $u;
			$opts->filename = $tmp_fn;
			$opts->request_to_file = true;
			$opts->request_from_file = true;
			$opts->folder = $this->tmp_folder;
			$content = curl_get($opts); // status,html
			if (property_exists($content, 'isCached')) {
				// No output.
			} else {
				echo '.';
				if ($content->status != 200) {
					die('Status != 200. Status='.$content->status);
				}
			}
			// Turn results into an array [{biz1},{biz2},...]
			$tmp = array(); // Default is an empty array
			preg_match('/\$\("#list\d+"\)\.jqGrid\(\{ data: (.+?), height:\'auto\',datatype: "local",/', $content->html, $match);
			if ($match) { // Has results
				$tmp = json_decode($match[1]);
				echo '('.count($tmp).' found!)' . "\n";
				$d = array_merge($tmp, $d); // Merge d with tmp.
			} else { // Not on page so the search failed!
				die('0 BUSINESSES FOUND!');
			}
		}
		return $d;
	}
	/**
	 * function get_biz_categories
	 */
	function get_biz_categories() {
		$biz_assoc_array = array(); // Keys by biz. reg. #
		// For each zone retrieve the list of categories.
		// Then iterate through all categories and add to the businesses in the zones object.
		foreach ($this->regions as $key => $region) {
			// Echo
			echo "\n".'Retrieving categories[biz+category] in region '.$region->title_en."\n".
				'"." denotes a non-cached query. Cached and duplicate queries are not shown.'."\n";
			//
			foreach($this->categories as $num_ctg => $category_obj) {
				// Curl get businesses (POST)
				$u =
					'region='.urlencode($region->post_id).
					'&zone='.
					'&district='.
					'&reg_no=&company_name='.
					'&reg_date_from='.
					'&reg_date_to='.
					'&company_type='.
					'&objective='.urlencode($num_ctg).
					'&gender=&a_capital_from=&a_capital_to=&i_capital_from='.
					'&i_capital_to=&p_capital_from=&p_capital_to=&btn_submit=Search';
				$tmp_fn = md5('http://www.ocr.gov.np/search/advanced_search.php' . $u);
				// Opts
				$opts = new StdClass;
				$opts->url = 'http://www.ocr.gov.np/search/advanced_search.php';
				$opts->post = true;
				$opts->post_str = $u;
				$opts->filename = $tmp_fn;
				$opts->request_to_file = true;
				$opts->request_from_file = true;
				$opts->folder = $this->tmp_folder;
				$content = curl_get($opts); // status,html
				if (property_exists($content, 'isCached')) {
					// No output.
				} else {
					echo '.';
					if ($content->status != 200) {
						die('Status != 200. Status='.$content->status);
					}
				}
				// Turn results into an array [{biz1},{biz2},...]
				$d = array(); // Default is an empty array
				preg_match('/\$\("#list\d+"\)\.jqGrid\(\{ data: (.+?), height:\'auto\',datatype: "local",/', $content->html, $match);
				if ($match) { // Has results
					$d = json_decode($match[1]);
				} else { // Not on page so the search failed!
					//~ echo $content->html;
					//~ echo $tmp_fn;
					//~ echo $u;
					//~ die('0 BUSINESSES FOUND!');
					// Try year-by-year.
					echo 'Trying year-by-year for category "'.$category_obj->title_en.'"'."\n";
					die('Aborting year-by-year!');
					// This is the command for year-by-year search. But it seems unnecessary.
					// If for some reason the program dies here then there is another issue,
					// such as the search result being cut off unexpectedly.
					//$d = $this->get_biz_categories_by_year($region->post_id, $num_ctg);
				}
				foreach ($d as $k => $tmp_biz) {
					$rgn = $tmp_biz->registration_no;
					if (!array_key_exists($rgn, $biz_assoc_array)) {
						$biz_assoc_array[ $rgn ] = array();
					}
					// Push
					array_push($biz_assoc_array[ $rgn ], $num_ctg);
				}
			}
		}
		// DEBUG
		// Count the biz with the most categories.
		$most = 0;
		$the_one = null;
		foreach ($biz_assoc_array as $reg_no => $biz_categories) {
			if (count($biz_categories) > $most) {
				$most = count($biz_categories);
				$the_one = $biz_categories; // Array
			}
		}
		echo 'The most categories for one business is '.$most.', the categories being ('.implode(',', $the_one).').'."\n";
		// ...
		echo 'Number of businesses that have at least 1 category:' . count($biz_assoc_array)."\n";
		// Loop through the businesses and add their category IDs.
		// For each biz here, loop through the businesses in the parent region.
		// Set the category for businesses in parent region.
		foreach ($this->regions as $key => $region) {
			foreach ($region->zones as $key2 => $zone) {
				foreach ($zone->districts as $key3 => $district) {
					foreach ($district->businesses as $key3 => $business) {
						$rgn = $business->registration_no;
						if (array_key_exists($rgn, $biz_assoc_array)) {
							$business->category_id = implode(',', $biz_assoc_array[ $rgn ]); // Join by comma.
						}
					}
				}
			}
		}
	}
	function geocode_approximate() {
		// If MapQuest: Geocode up to 100 locations per request
		$addresses_to_geocode = array(); // Associative array.
		foreach ($this->regions as $key => $region) {
			if ($region->title_en == 'Unknown') continue; // Unnecessary to geocode this.
			foreach ($region->zones as $key2 => $zone) {
				foreach ($zone->districts as $key3 => $district) {
					foreach ($district->businesses as $key3 => $business) {
						// E.g. If biz address="Karkineta 4, Mountain, Dhaulagiri"
						// and Zone=Dhaulagiri and District=Parbat
						// then perform a geocode search for both "Karkineta,Parbat,Dhaulagiri"
						// and "Parbat,Dhaulagiri". Basically take up to the first space
						// of the address as this is typically the city/town.
						
						// District+Zone+Country
						$dz = $district->title_en.','.$zone->title_en.',Nepal';
						
						// By default, geocode the District+Zone+Country for every business.
						$addresses_to_geocode[ $dz ] = true;
						
						// Set the businesses regional and local location string IDs.
						$business->regional_location_string = $dz;
						$business->local_location_string = ''; // Default is "".
						
						// Next try a city geocode. Has translated?
						if ($business->address_api_translate != '') {
							$x = $business->address_api_translate;
							// By the first word from left with two or more characters. 
							preg_match('/^[^a-z]*([a-z]{2,})/i', $x, $m); // Use preg_match.
							if (!empty($m[1])) {
								$business->local_location_string = $m[1] . ',' . $dz; // Update the business' local location string ID.
								$addresses_to_geocode[ $business->local_location_string ] = true;
							}
							// By any number of words prior to a comma.
							//~ preg_match('/^[^a-z]*([^,]{3,})/i', $x, $m); // Use preg_match.
							//~ if (!empty($m[1])) {
								//~ $m[1] = preg_replace('/[^a-z ]/i', '', $m[1]); // Replace non-word, non-space characters.
								//~ $m[1] = trim($m[1]);
								//~ $addresses_to_geocode[ $m[1] . ',' . $dz ] = true;
							//~ }
							// By explode.
							//~ $x = preg_replace('/^[^a-z]*/i', '', $x); // Remove any non-word characters that start the address.
							//~ $c = explode(' ',$x); // Explode by space.
							//~ if (count($c) > 0) {
								//~ // Trim comma
								//~ // Add city. Only if strlen() is >= 3
								//~ if (strlen($c[0])
								//~ $addresses_to_geocode[ $c[0] . ',' . $dz ] = true;
							//~ }
						}
					}
				}
			}
		}
		echo 'Retrieving geocoding. "." denotes a new geocode to retrieve. Duplicate and cached geocoding not displayed.'.
			"\n".'Number of addresses to geocode:'.count($addresses_to_geocode)."\n";
		
		// CURL
		// Set $this->locations_array
		foreach ($addresses_to_geocode as $address => $v) {
			$ufilename =
				'https://maps.googleapis.com/maps/api/geocode/json'.
				'?address='.urlencode($address);
			$u = 'https://maps.googleapis.com/maps/api/geocode/json'.
				'?address='.urlencode($address).'&key='.urlencode($this->gapi_key); // This will allow using a key but to keep the same filename.
			$opts = new StdClass;
			$opts->url = $u;
			$opts->filename = md5($ufilename);
			$opts->request_to_file = true;
			$opts->request_from_file = true;
			$opts->folder = $this->tmp_folder;
			$content = curl_get($opts); // status,html
			if (property_exists($content, 'isCached')) {
				//~ echo '*'; // Is cached.
			} else {
				echo '.';
				if ($content->status != 200) {
					die('status != 200, =' . $content->status);
				}
			}
			$d = json_decode($content->html);
			if (count($d->results) > 0 && $d->results[0]->geometry && $d->results[0]->geometry->location) {
				$d = $d->results[0]->geometry->location; // Just the lat/lng.
			} else {
				$d = false;
			}
			$this->locations_array[ $address ] = $d;
		}
		
		// Set business lat/lng for Regional and Local, if available.
		foreach ($this->regions as $key => $region) {
			if ($region->title_en == 'Unknown') continue; // Unnecessary to geocode this.
			foreach ($region->zones as $key2 => $zone) {
				foreach ($zone->districts as $key3 => $district) {
					foreach ($district->businesses as $key3 => $business) {
						if 	(
							$business->regional_location_string != '' &&
							array_key_exists($business->regional_location_string, $this->locations_array) &&
							$this->locations_array[ $business->regional_location_string ]
							)
						{
							try {
								$loc = $this->locations_array[ $business->regional_location_string ];
								$business->regional_lat_lng = $loc->lat.','.$loc->lng;
							} catch (Exception $e) {
								$business->regional_lat_lng = ''; // None
							}
						}
						if 	(
							$business->local_location_string != '' &&
							array_key_exists($business->local_location_string, $this->locations_array) &&
							$this->locations_array[ $business->local_location_string ]
							)
						{
							try {
								$loc = $this->locations_array[ $business->local_location_string ];
								$business->local_lat_lng = $loc->lat.','.$loc->lng;
							} catch (Exception $e) {
								$business->local_lat_lng = ''; // None
							}
						}
					}
				}
			}
		}
		
		// Delete addresses_to_geocode & locations_array.
		unset($addresses_to_geocode);
		unset($this->locations_array);
		
		// https://maps.googleapis.com/maps/api/geocode/json?address=Karkineta,parbat,Dhaulagiri,nepal
		
		// 2 results. 2nd is last try if 1st is not good.
		// Url	http://open.mapquestapi.com/geocoding/v1/batch?key=Fmjtd%7Cluubnuuynl%2C7s%3Do5-9u1lh6&location=Pottsville,PA&location=Red%20Lion&location=19036&location=1090%20N%20Charlotte%20St,%20Lancaster,%20PA&thumbMaps=false&maxResults=2
		// Returns		{"street":"","adminArea6":"","adminArea6Type":"Neighborhood","adminArea5":"Pottsville","adminArea5Type":"City","adminArea4":"Schuylkill County","adminArea4Type":"County","adminArea3":"PA","adminArea3Type":"State","adminArea1":"US","adminArea1Type":"Country","postalCode":"","geocodeQualityCode":"A5XAX","geocodeQuality":"CITY","dragPoint":false,"sideOfStreet":"N","linkId":"0","unknownInput":"","type":"s","latLng":{"lat":40.685132,"lng":-76.19537},"displayLatLng":{"lat":40.685132,"lng":-76.19537}}]
		
		// Facebook
		//~ var u = 
			//~ 'https://graph.facebook.com/search'+
			//~ '?q='+encodeURIComponent(v)+'&type=page'+ // not &type=user
			//~ '&fields=category,id,name,likes,talking_about_count,location,link,website,cover,phone'+ //
			//~ '&limit=10'+
			//~ '&center='+lat+','+lng+ //
			//~ '&distance=30000'+ // 30k ~ 19mi
			//~ '&access_token='; //+KEY
	}
	function translate() {
		// Translate?
		if (!$this->gapi_key) {
			echo 'Skipping business address translations, no Google API key specified.'."\n";
			return;
		} else {
			echo
				'Downloading Google translations for business addresses.'."\n".
				'"." denotes a non-cached query. Cached and duplicate addresses are not shown.'."\n";
		}
		// Use an associative array: $this->translations_array = array();
		// The result is e.g.:
		//~ {
		//~ 	"data": {
		//~ 		"translations": [
		//~ 			{
		//~ 				"translatedText": "Ritachopata, 2, Darchula, Mahakali"
		//~ 			}
		//~ 		]
		//~ 	}
		//~ }
		
		$count_trans = 0;
		$count_dup_trans = 0;
		foreach ($this->regions as $key => $region) {
			foreach ($region->zones as $key2 => $zone) {
				echo "\n";
				echo 'Translating businesses in '.$region->title_en.'->'.$zone->title_en."\n";
				foreach ($zone->districts as $key3 => $district) {
					foreach ($district->businesses as $key3 => $business) {
						// Translate the following address: $business->address
						// CURL get api translation (GET)
						// "ne" is not on Google's list of languages (https://cloud.google.com/translate/v2/using_rest). However, 
						// it is returned when using the language detect API https://www.googleapis.com/language/translate/v2/detect?key=&q=.
						
						// Do not translate if key already exists.
						if (array_key_exists($business->address, $this->translations_array)) {
							// No output here.
							$count_dup_trans++;
							continue;
						}
						
						// Increment unique translations.
						$count_trans++;
						
						// Translate
						// TODO: Make the filename not include the "key". (Not dependent on this.)
						$u =
							'https://www.googleapis.com/language/translate/v2'.
							'?key='.urlencode($this->gapi_key).
							'&source=ne&target=en'.
							'&q='.urlencode($business->address);
						$opts = new StdClass;
						$opts->url = $u;
						$opts->filename = md5($opts->url);
						$opts->request_to_file = true;
						$opts->request_from_file = true;
						$opts->folder = $this->tmp_folder;
						$content = curl_get($opts); // status,html
						if (property_exists($content, 'isCached')) {
							// Is cached. No output here.
						} else {
							echo '.';
							if ($content->status != 200) {
								die('status != 200, =' . $content->status);
							}
						}
						$d = json_decode($content->html);
						$this->translations_array[ $business->address ] = $d;
						
						// Add details to this object.
						try {
							$trans = $this->translations_array[ $business->address ]->data->translations[0]->translatedText;
							$business->address_api_translate = $trans;
						} catch (Exception $e) {
							print_r($this->translations_array[ $business->address ]);
							die('Caught exception: '.  $e->getMessage(). "\n");
						}
					}
				}
			}
		}
		echo "\n";
		echo 'Number of unique addresses translated:' . $count_trans . "\n"; //15769
		echo 'Number of dupliate addresses found:' . $count_dup_trans . "\n";//111774
		// Delete the translations array to free memory
		unset($this->translations_array);
	}
	function csv($year = false) {
		$now = date('F.d.Y');
		$csv = 'businesses'.$now.'.csv';
		$csv = ($year) ? 'businesses'.$now.'(registered since '.$year.').csv' : $csv;
		$fp = fopen($csv, 'w');
		$header = array(
			'Region',
			'Region-English',
			'Zone',
			'Zone-English',
			'District',
			'District-English',
			'Registration Number',
			'Registration Date',
			'Name',
			'Name-English',
			'Address',
			'Type',
			'Category IDs',
			'Address-English Translated', // TRANSLATION IF AVAILABLE
			'Regional Lat./Lng.',
			'Local Lat./Lng.',
			'Regional Match String',
			'Local Match String'
		);
		fputcsv($fp, $header); // Header
		$count_biz = 0;
		foreach ($this->regions as $key => $region) {
			foreach ($region->zones as $key2 => $zone) {
				foreach ($zone->districts as $key3 => $district) {
					foreach ($district->businesses as $key3 => $business) {
						
						// If limit by year.
						if ($year) {
							$rd = explode('-', $business->registration_date);
							if (count($rd) != 3) continue; // Skip if the date is invalid.
							if (intval($rd[0]) < $year) { // Skip if year is prior to specified.
								continue;
							} 
						}
						
						// Increment count
						$count_biz++;
						
						// $fields CSV array
						$fields = array(
							$region->title,
							$region->title_en,
							$zone->title,
							$zone->title_en,
							$district->title,
							$district->title_en,
							$business->registration_no,
							$business->registration_date,
							$business->name,
							$business->name_eng,
							$business->address,
							$business->type_name,
							$business->category_id,
							$business->address_api_translate, // TRANSLATION IF AVAILABLE
							$business->regional_lat_lng, // RETRIEVED IF AVAILABLE
							$business->local_lat_lng, // RETRIEVED IF AVAILABLE
							$business->regional_location_string, // RETRIEVED IF AVAILABLE
							$business->local_location_string // RETRIEVED IF AVAILABLE
						);
						
						// Write array to CSV
						fputcsv($fp, $fields);
					}
				}
			}
		}
		// Echo num businesses
		if ($year)
			echo 'Total businesses (since '.$year.'):'.$count_biz."\n";
		else
			echo 'Total businesses:'.$count_biz."\n";
			
		fclose($fp); // Close
	}
}
/**
 * class region
 */
class region {
	public $id;
	public $post_id;
	public $name;
	public $zones; // fill in
	public $districts; // fill in, includes the businesses in this district
	private $tmp_folder; // tmp folder for cached CURL requests
	function __construct($region, $tmp_folder) {
		// Set this region's post id and other details
		$this->id = $region['id'];
		$this->post_id = 'region,'.$this->id;
		$this->title = $region['title'];
		$this->title_en = $region['title_en'];
		$this->tmp_folder = $tmp_folder; // This is the location to save to, when necessary.
		
		// Gets zones, and then get districts, and then get businesses.
		$this->get_zones();
		$this->get_districts();
		$this->get_biz_in_district();
	}
	function get_biz_in_district_curl($zone_obj, $dist_obj, $date_start = '', $date_end = '') {
		// Curl get businesses (POST)
		$u =
			'region='.urlencode($this->post_id).
			'&zone='.urlencode($zone_obj->id).
			'&district='.urlencode($dist_obj->id).
			'&reg_no=&company_name='.
			'&reg_date_from='.urlencode($date_start).
			'&reg_date_to='.urlencode($date_end).
			'&company_type=&objective=&gender=&a_capital_from=&a_capital_to=&i_capital_from='.
			'&i_capital_to=&p_capital_from=&p_capital_to=&btn_submit=Search';
		$tmp_fn = md5('http://www.ocr.gov.np/search/advanced_search.php' . $u);
		// Echo
		echo 'Retrieving businesses for '.$dist_obj->title_en . '('.$tmp_fn.')';
		// 
		if ($date_start != '' && $date_end != '') {
			echo '(including dates:'.$date_start.' to '.$date_end.')';
		}
		// Opts
		$opts = new StdClass;
		$opts->url = 'http://www.ocr.gov.np/search/advanced_search.php';
		$opts->post = true;
		$opts->post_str = $u;
		$opts->filename = $tmp_fn;
		$opts->request_to_file = true;
		$opts->request_from_file = true;
		$opts->folder = $this->tmp_folder;
		$content = curl_get($opts); // status,html
		if (property_exists($content, 'isCached')) {
			echo '(read from cache)';
		} else {
			echo '(s=' . $content->status . ')';
			if ($content->status != 200) {
				die('status != 200');
			}
		}
		return $content;
	}
	// Scrape the page to retrieve the items
	// Returns an array of Business objects.
	// [{biz1}, {biz2}, ...]
	/**
	 * {
	 * "sn_no":...,
	 * "registration_no":"...",
	 * "registration_date":"...",
	 * "name":"...",
	 * "name_eng":"...",
	 * "address":"...",
	 * "type_name":"..."
	 * }
	 */
	// 
	function reg_biz($html) {
		$d = array(); // Default is an empty array
		preg_match('/\$\("#list\d+"\)\.jqGrid\(\{ data: (.+?), height:\'auto\',datatype: "local",/', $html, $match);
		if ($match) { // Has results
			$d = json_decode($match[1]);
		}
		return $d;
	}
	function get_biz_in_district() {
		// Get businesses for each district
		foreach ($this->zones as $key => $zone_obj) {
			foreach ($zone_obj->districts as $key2 => $dist_obj) { // $zone_obj->districts is an array of district objects.
				
				$content = $this->get_biz_in_district_curl($zone_obj, $dist_obj);
				$d = $this->reg_biz($content->html);
				
				// If 0 businesses then the page probably did not load.
				// So break it up request into many pieces.
				if (count($d) == 0) {
					// Timeout on page is 30 seconds.
					// If blank, this must go through year-registered sets.
					echo '(Num. businesses=0! Going to try separating the query by registration year!)'."\n";
					
					// Loop through 1-year intervals. From 1900 to present.
					// Use array_merge to merge the resulting businesses.
					$tmp_biz;
					$content = $this->get_biz_in_district_curl($zone_obj, $dist_obj, '1900-01-01', '2040-01-01');
					$d = $this->reg_biz($content->html);
					if (count($d) == 0)
						die('0 BUSINESSES FOUND!');
					echo '(Num. businesses='.count($d).')'. "\n";
					$tmp_biz = $d;
					for ($i = 2040; $i<=CURRENT_NP_YEAR; $i++) { // Less than or equal to current year.
						$content = $this->get_biz_in_district_curl($zone_obj, $dist_obj, $i . '-01-02', ($i+1) . '-01-01');
						$d = $this->reg_biz($content->html);
						echo '(Num. businesses='.count($d).')'. "\n";
						if (count($d) == 0)
							die('0 BUSINESSES FOUND!');
						$tmp_biz = array_merge($tmp_biz, $d);
					}
					$d = $tmp_biz;
				}
				// Initialize each business as class `business`.
				foreach ($d as $key => $business) {
					$d[ $key ] = new business($business);
				}
				
				// Set $dist_obj->businesses
				$dist_obj->businesses = $d;
				echo '(Num. businesses='.count($d).')'. "\n";
			}
		}
	}
	function get_districts() {
		// Get districts in each zone.
		foreach ($this->zones as $key => $zone_obj) {
			// Get districts
			echo 'Retrieving districts for '.$zone_obj->title_en;
			
			// CURL get zone's districts (POST)
			$opts = new StdClass;
			$opts->url = 'http://www.ocr.gov.np/search/ajax_page.php';
			$opts->post = true;
			$opts->post_str = 'task=getDistrict&zone=' . $zone_obj->id; // 1..6
			$opts->filename = md5($opts->url . $opts->post_str);
			$opts->request_to_file = true;
			$opts->request_from_file = true;
			$opts->folder = $this->tmp_folder;
			$content = curl_get($opts); // status,html
			if (property_exists($content, 'isCached')) {
				echo '(read from cache)';
			} else {
				echo '(s=' . $content->status . ')';
				if ($content->status != 200) {
					die('status != 200');
				}
			}
			
			// Set zones->districts
			// e.g. [{"id":"5","title":"...","title_en":"..."},{"id":"6","title":"...","title_en":"..."}, ...]
			$d = json_decode($content->html);
			echo '(Num. districts='.count($d).')'. "\n";
			
			$zone_obj->districts = $d;
		}
	}
	function get_zones() {
		echo 'Retrieving region info for region '.$this->id;
		
		// CURL get region zones (POST)
		$opts = new StdClass;
		$opts->url = 'http://www.ocr.gov.np/search/ajax_page.php';
		$opts->post = true;
		$opts->post_str = 'task=getZone&region=' . $this->id; // 1..6
		$opts->filename = md5($opts->url . $opts->post_str);
		$opts->request_to_file = true;
		$opts->request_from_file = true;
		$opts->folder = $this->tmp_folder;
		$content = curl_get($opts); // status,html
		if (property_exists($content, 'isCached')) {
			echo '(read from cache)';
		} else {
			echo '(s=' . $content->status . ')';
			if ($content->status != 200) {
				die('status != 200');
			}
		}
		
		// Set $this->zones
		// This is an array of objects.
		// e.g. [{"id":"12","title":"...","title_en":"..."},{"id":"13","title":"...","title_en":"..."}}, ...]
		$this->zones = json_decode($content->html);
		echo '(Num. zones='.count($this->zones).')'. "\n";
	}
}
/**
 * class business
 */
class business {
	public $registration_no;
	public $registration_date;
	public $name;
	public $name_eng;
	public $address;
	public $type_name;
	public $address_api_translate = ''; // FILLED IN LATER
	public $regional_lat_lng = ''; // FILLED IN LATER
	public $local_lat_lng = ''; // FILLED IN LATER
	public $regional_location_string = ''; // FILLED IN LATER
	public $local_location_string = ''; // FILLED IN LATER
	public $category_id = ''; // ID. FILLED IN LATER
	//~ public $category = ''; // Title in Nepalese. FILLED IN LATER
	//~ public $category_en = ''; // Title auto-translated into English. FILLED IN LATER
	
	function __construct($business) {
		
		// Add the following properties to each business:
		// address_api_translate
		// regional_lat,regional_lng
		// local_lat,local_lng
		// regional_location_string,local_location_string
		
		$this->registration_no = $business->registration_no;
		$this->registration_date = $business->registration_date;
		$this->name = $business->name;
		$this->name_eng = $business->name_eng;
		$this->address = $business->address;
		$this->type_name = $business->type_name;
		
	}
}

/**
 * function curl_get
 * 
 * 
 * @options, Options object.
 * @->request_to_file, Save to cached file when status==200.
 * @->request_from_file, Read from file if exists.
 * @->folder, Includes trailing forward-slash.
 */
function curl_get($options) {
	// Return var
	$return = new stdClass;
	
	if	(
		property_exists($options, 'request_to_file') ||
		property_exists($options, 'request_from_file')
		)
	{
		$file = $options->folder . $options->filename; 
		if (property_exists($options, 'request_from_file') && file_exists($file)) {
			$fgc = file_get_contents($file);
			$fgc = trim($fgc);
			if ($fgc != '') { // Continue here if blank!
				$return->isCached = true;
				$return->html = $fgc;
				$return->status = 200; // Echo 200 status.
				return $return;
			}
		}
	}
	
	$ch = curl_init($options->url);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
	//~ curl_setopt($ch, CURLOPT_COOKIEFILE, '');
	//~ curl_setopt($ch, CURLOPT_COOKIEJAR, '');
	curl_setopt($ch, CURLOPT_TIMEOUT, 140);
	curl_setopt($ch, CURLOPT_ENCODING, 'identity');//
	if (property_exists($options, 'post')) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $options->post_str);
	} else
		curl_setopt($ch, CURLOPT_POST, false);
	// Exec
	$r = curl_exec($ch);
	$x = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	// Save to file
	if 	(
		property_exists($options, 'request_to_file') && $x == 200
		)
	{
		file_put_contents($file, $r);
	}
	
	// Return
	$return->html = $r;
	$return->status = $x;
	return $return;
}
?>
