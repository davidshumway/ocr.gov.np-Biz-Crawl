<?php
/**
 * 
 * NOTE: Ensure copyright notice is shown with these. "Copyright © 2008-2015, YPNepal.com : Global-Biz Yellow Pages 2015. All Right Reserved."
 * http://www.ypnepal.com/index.php?list=44000 Goes to about 45k
 */
class crawl_ypnepal  {
	private $tmp_folder;
	private $gapi_key;
	private $geonames_username;
	private $regional_locations_array;
	private $arr_bad_str = array( // Array bad strings
		'This API project is not authorized to use this API. Please ensure that this API is activated in the APIs Console: Learn more: https://code.google.com/apis/console', // google
		'You have exceeded your daily request quota for this API.', // google
		'"message":"ERROR: canceling statement due to statement timeout"' // geonames
	);
	/**
	 * function __construct
	 */
	function __construct($tmp_folder, $gapi_key, $geonames_username) {
		$this->tmp_folder = $tmp_folder;
		$this->gapi_key = $gapi_key;
		$this->geonames_username = $geonames_username;
		echo 'Starting ypnepal.com crawl.'."\n";
		$this->add_geo();
		
		// Default
		$this->crawl();
		
		// Limit to:
		$kw = array(
			'plastic', 'paper', 'pulp', 'paper mill', 'cardboard'
		);
		$cat = array(
			'Paper Products',
			'Paper Products / Stationery',
			'Bags & Wrappers',
			//~ 'Handicrafts',
			//~ 'Handicrafts- Paper',
			'Packaging Industries',
			'PVC Card',
			'PVC Products',
			'Plastic Wood'
		);
		$this->crawl('businesses-on-ypnepal-cardboard.csv', $kw, $cat);
	}
	function add_geo() {
		$add_locn = array(
			['north'=>'24.439560','south'=>'20.912610','east'=>'89.558872','west'=>'85.559849'], // 5
			['north'=>'27.276516','south'=>'24.439560','east'=>'89.558872','west'=>'85.559849'], // 4
			['north'=>'27.276516','south'=>'24.439560','east'=>'81.560826','west'=>'77.561803'], // 3
			['north'=>'27.276516','south'=>'24.439560','east'=>'85.559849','west'=>'81.560826'], // 2
			['north'=>'30.113472','south'=>'27.276516','east'=>'85.559849','west'=>'81.560826']
		);
		$this->geonames_cities($add_locn); // Adds to $this->regional_locations_array
		$add_locn = array(
			// Add abde (NOT C!)
			['north'=>'30.113472','south'=>'27.276516','east'=>'81.560826','west'=>'77.561803'],  // d
			['north'=>'32.950428','south'=>'30.113472','east'=>'81.560826','west'=>'77.561803'],  // a 2.836956 NORTH
			['north'=>'32.950428','south'=>'30.113472','east'=>'85.559849','west'=>'81.560826'],  // b
			['north'=>'30.113472','south'=>'27.276516','east'=>'89.558872','west'=>'85.559849'],  // e 2.836956 NORTH
		);
		$this->geonames_cities($add_locn); // Adds to $this->regional_locations_array
	}
	/**
	 * function google_geocode
	 */
	function google_geocode($address) {
		$use_key = false;
		$ufilename =
			'https://maps.googleapis.com/maps/api/geocode/json'. // This will allow using a key but to keep the same filename.
			'?address='.urlencode($address);
		$u = 'https://maps.googleapis.com/maps/api/geocode/json'.
			'?address='.urlencode($address);
		if ($use_key)
			$u .= '&key='.urlencode($this->gapi_key);
		$opts = new StdClass;
		$opts->url = $u;
		$opts->filename = md5($ufilename);
		$opts->overwrite_if_strpos = $this->arr_bad_str;
		$opts->do_not_save_if_strpos = $this->arr_bad_str;
		$opts->request_to_file = true;
		$opts->request_from_file = true;
		$opts->folder = $this->tmp_folder;
		$content = curl_get($opts); // status,html
		if (property_exists($content, 'isCached')) { // Is cached.
		} else {
			echo "\n".'Adding new (not cached) geocoding for:'.$address."\n";
			if ($content->status != 200) {
				die('status != 200, =' . $content->status);
			}
		}
		$d = json_decode($content->html);
		check_response_bad_str($content->html);
		if (count($d->results) > 0 && $d->results[0]->geometry && $d->results[0]->geometry->location) {
			$d = $d->results[0]->geometry->location; // Just lat/lng.
			$this->regional_locations_array[ $address ] = array('lat-lng'=>$d->lat . ',' . $d->lng, 'lat'=>$d->lat, 'lng'=>$d->lng); // Set
		} else {
			$d = false;
			$this->regional_locations_array[ $address ] = array('lat-lng'=>'', 'lat'=>'', 'lng'=>''); // Set
		}
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
				//~ $this->regional_locations_array[ $pretty_name ] = [$locn->lat . ',' . $locn->lng;
				$this->regional_locations_array[ $pretty_name ] = array('lat-lng'=>$locn->lat . ',' . $locn->lng, 'lat'=>$locn->lat, 'lng'=>$locn->lng);
			}
		}
	}
	/**
	 * function email_check
	 * 
	 * Emails are hidden.
	 * 
	 * E.g.:
	 */
	//~ <div class="labelLeft">Email </div>
    //~ <div class="labelRight">: 
    //~ <a class="listingmenu" ...><span ...>...</span>...</a>...</div>
	function email_check($html) {
		if (strpos($html, '<span class="__cf_email__" data-cfemail="') === false) {
			return $html;
		}
		// If here then this is a hidden email.
		// E.g. 11787f777e51783c747564727065787e7f623f727e7c
		preg_match('/<span class="__cf_email__" data-cfemail="([^"]+)">/',$html,$match);
		if ($match) {			
			//base_convert
			$code = $match[1];
			$r = intval(substr($code, 0, 2), 16);
			for ($e="", $n=2; strlen($code)-$n; $n+=2) {
				$i = intval(substr($code, $n, 2), 16) ^ $r;
				$e .= chr($i);
			}
			//~ echo $e."\n";
			return $e;
		} else {
			return ''; // Empty.
		}
	}
	/**
	 * 
	 */
	function crawl($filename = false, $keywords = array(), $categories = array()) {
		
		// CSV
		$csv = (!$filename) ? 'businesses-on-ypnepal.csv' : $filename; // Default = 'businesses-on-ypnepal.csv'
		$fp = fopen($csv, 'w');
		$hd = array( // Just the value is written here not the key, when key is present.
			'Ypnepal.com Listing ID',
			'Company',
			'Description',
			'Address',
			'P.O. Box',
			'City',
			'Country',
			'Phone',
			'Mobile',
			'Fax',
			'Email',
			'URL',
			'Updated',
			'Categories',
			'location-lat-lng',
			'location-lat',
			'location-lng'
		);
		// Write header to CSV.
		fputcsv($fp, $hd);
		// Loop
		// Listing IDs
		// 1...45000
		for ($i=1; $i<=45000; $i++) {
			// Set this below
			$add_to_output = (count($keywords) == 0 && count($categories) == 0) ? true : false; // Default = true
			// Curl get
			$u = 'http://www.ypnepal.com/index.php?list='.$i;
			$tmp_fn = md5($u);
			// Opts
			$opts = new StdClass;
			$opts->url = $u;
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
					echo $content->html;
					die('Status != 200. Status='.$content->status);
				}
			}
			// Note
			// Does "Listing Name" match header? If not then alert.
			// <table border="0" cellpadding="0" cellspacing="0" width="100%">
			$content->html = str_replace("\n", '', $content->html);
			preg_match('/<table border="0" cellpadding="0" cellspacing="0" width="100%">(.+?)<\/table>/', $content->html, $match);
			if (empty($match)) {
				//~ echo $content->html;
				//~ die('No table!');
			}
			$table = $match[1];
			// preg_match_all rather than dom
			// 1) e.g. "Address "
			// 2) e.g. ": Narayangarh"
			preg_match_all('/<div class="labelLeft">([^<]+)<\/div>[\r\n\t\s]*<div class="labelRight">(.+?)<\/div>/', $table, $match);
			if (empty($match)) {
				//~ die('No divs!');
				//~ continue;
			}
			$arr = array( // Just the value is written here not the key, when key is present.
				$i, // Listing ID
				'Listing Name'=>'',
				'Description'=>'',
				'Address'=>'',
				'P.O. Box'=>'',
				'City'=>'',
				'Country'=>'',
				'Phone'=>'',
				'Mobile'=>'',
				'Fax'=>'',
				'Email'=>'',
				'Website'=>'',
				'Updated on'=>'',
				'Categories'=>'',
				'location-lat-lng'=>'',
				'location-lat'=>'',
				'location-lng'=>''
			);
			$has_content = false;
			for ($j=0; $j<count($match[1]); $j++) {
				$type = trim($match[1][$j]);
				$type = ($type == 'Webpage') ? 'Website' : $type;
				$value = trim(trim($match[2][$j], ':')); // Add ':' to trim list.
				if (!array_key_exists($type, $arr)) {
					die('Type is missing:' . $type);
				}
				$value = $this->email_check($value); // Emails are hidden
				$value = preg_replace('/<[^>]*>/', '', $value);// Contains html elements?
				$value = str_replace('\\"','"',$value); // Replace "\"" with single quote no backslash '"'. Seems to not be escaped propertly by fputcsv.
				$arr[ $type ] = $value;
				if ($value != '') {
					$has_content = true; // Not blank
				}
			}
			// All blank?
			// When a listing is "empty" it will only display "Listing Name" and "Updated on".
			if (!$has_content) {
				continue; // Skip here.
			}
			// Categories
			// e.g. "Medicine Distributors"
			preg_match_all('/<a href="index\.php\?cat=\d+" class="listingmenu">([^<]+)<\/a>/', $table, $match);
			if (empty($match)) {
				die('No catgeories!'); // DEBUG // All have categories!
			}
			$catg = array(); // Assoc. array.
			foreach ($match[1] as $key => $category) {
				$catg[ $category ] = $category;
			}
			$arr[ 'Categories' ] = implode(' / ', $catg);
			
			// Check if included. Default=yes.
			if (!$add_to_output) {
				foreach ($catg as $c => $category) {
					if (in_array($category, $categories)) {
						$add_to_output = true; // Set true
						break; // End here
					}
				}
			}
			if (!$add_to_output) {
				foreach ($keywords as $key => $keyword) {
					if  (
						stripos($arr['Listing Name'], $keyword) !== false ||
						stripos($arr['Description'], $keyword) !== false 
						)
					{
						$add_to_output = true; // Set true
						break; // End here
					}
				}
			}
			// Skip this?
			if (!$add_to_output) {
				continue;
			}
			
			//~ if (count($catg) > 1) { // debug // Looks okay
				//~ echo $table;
				//~ die('Multiple:'.$arr[ 'Categories' ]);
			//~ }
			
			// Add geo
			$key = $arr['City'].','.$arr['Country'];
			$key2= $arr['City'].$arr['Country'];
			if (array_key_exists($key, $this->regional_locations_array)) {
				$loc =  $this->regional_locations_array[ $key ];
				$arr['location-lat-lng'] = $loc['lat-lng'];
				$arr['location-lat'] = $loc['lat'];
				$arr['location-lng'] = $loc['lng'];
			} else if ($key2!= '') {
				$this->google_geocode($key);
				// Try again
				if (array_key_exists($key, $this->regional_locations_array)) {
					$loc =  $this->regional_locations_array[ $key ];
					$arr['location-lat-lng'] = $loc['lat-lng'];
					$arr['location-lat'] = $loc['lat'];
					$arr['location-lng'] = $loc['lng'];
				} else if ($key2 != '') {
					print_r($arr);
					die('Could not find geo!');
				}
			}
			//~ print_r($arr);
			// Write row to CSV.
			fputcsv($fp, $arr);
			
			//~ // DOM
			//~ $dom = new DOMDocument();
			//~ @$dom->loadHTML($match[1]);
			//~ # Iterate over all the <a> tags
			//~ foreach($dom->getElementsByTagName('a') as $link) {
				//~ echo $link->getAttribute('href');
				//~ echo "<br />";
			//~ }
			//~ if ($i > 100)
				//~ exit;
		}
		
		fclose($fp);
	}
}
?>
