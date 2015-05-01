<?php
/**
 * ocr.gov.np
 * 
 * Init. with, e.g.: php -f /var/www/html/mturk/util.php -- "ocr.gov.np" "/var/www/html/development/NepalRegions/"
 */
if (isset($argv[1]) && $argv[1] == 'ocr.gov.np' && isset($argv[2])) {
	
	// Set max preg string size limit. 10 MB.
	// A very long biz match causes regular expression to exceed the max length.
	// Default is probably 500 KB to 1000 KB.
	//
	/**
	 * From http://stackoverflow.com/questions/18296441/maximum-length-of-string-preg-match-all-can-match-and-acquire:
	 * Set the ini_set('pcre.backtrack_limit', '1048576'); to whatever you want in your script or on your php.ini file for global use. (example is 1mb). Credit to: http://www.karlrixon.co.uk/writing/php-regular-expression-fails-silently-on-long-strings/.
	 */
	ini_set('pcre.backtrack_limit', 10485760);
	
	//~ REGION, REGION NUM, REGION ID, DISTRICT, DISTRICT-EN, DISTRICT ID, ZONE, ZONE-EN, ZONE ID,
	
	class region {
		public $id;
		public $post_id;
		public $name;
		public $zones; // fill in
		public $districts; // fill in, includes the businesses in this district
		private $tmp_folder; // tmp folder for cached CURL requests
		function __construct($id, $name, $tmp_folder) {
			// Set this region's post id and other details
			$this->id = $id;
			$this->post_id = 'region,'.$id;
			$this->name = $name;
			$this->tmp_folder = $tmp_folder; // This is the location to save to, when necessary.
			
			// Gets zones, and then get districts, and then get businesses.
			$this->get_zones();
			$this->get_districts();
			$this->get_biz_in_district();
			
			//~ // Get biz in districts
			//~ $this->get_biz_in_district();
		}
		//~ // Biz object
		//~ $biz = new stdClass;
		//~ $biz->reg_number = null;
		//~ $biz->reg_date = null;
		//~ $biz->name = null;
		//~ $biz->company_type = null;
		//~ $biz->address = null;
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
		/**
		 {
		 "sn_no":...,
		 "registration_no":"...",
		 "registration_date":"...",
		 "name":"...",
		 "name_eng":"...",
		 "address":"...",
		 "type_name":"..."
		 }
		 */
		// [{biz1}, {biz2}, ...]
		function reg_biz($html) {
			$d = array(); // Default is an empty array
			preg_match('/\$\("#list\d+"\)\.jqGrid\(\{ data: (.+?), height:\'auto\',datatype: "local",/', $html, $match);
			if ($match) { // Has results
				$d = json_decode($match[1]);
			}
			// Not this to test: if($match !== false && !empty($match))
			return $d;
		}
		function get_biz_in_district() {
			// Get businesses for each district
			//~ $test_count = 0; //DEBUG
			foreach ($this->zones as $key => $zone_obj) {
				foreach ($zone_obj->districts as $key2 => $dist_obj) { // $zone_obj->districts is an array of district objects.
					//~ if ($test_count > 2) exit; //DEBUG
					//~ $test_count++; //DEBUG
					
					$content = $this->get_biz_in_district_curl($zone_obj, $dist_obj);
					$d = $this->reg_biz($content->html);
					
					// If 0 business then exit?
					// Test
					if (count($d) == 0) { // || $tmp_fn == '59ab917722f064dabab931ee51a8b1f0'
						// Timeout on page is 30 seconds.
						// If blank, this must go through year-registered sets.
						// Where each set is something like 10 years.
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
						for ($i = 2040; $i<= 2080; $i++) {
							$content = $this->get_biz_in_district_curl($zone_obj, $dist_obj, $i . '-01-02', ($i+1) . '-01-01');
							$d = $this->reg_biz($content->html);
							echo '(Num. businesses='.count($d).')'. "\n";
							if (count($d) == 0)
								die('0 BUSINESSES FOUND!');
							$tmp_biz = array_merge($tmp_biz, $d);
						}
						echo '(Num. businesses all dates='.count($tmp_biz).')'. "\n";exit;
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
	class regions {
		private $regions;
		private $tmp_folder;
		private $data_folder;
		function __construct($folder) {
			// CHECK: Folder ends with "/".
			if ($folder[ strlen($folder)-1 ] != '/') {
				die('ERROR: Folder must end with forward-slash "/":' . $folder);
			}
			// Make the folder if it does not exist.
			if (!file_exists($folder)) {
				if (!mkdir($folder)) {
					die('ERROR: Could not create the folder '.$folder);
				}
			}
			// Make a "tmp" data dir if it does not exist.
			$this->tmp_folder = $folder . 'data/';
			if (!file_exists($this->tmp_folder)) {
				if (!mkdir($this->tmp_folder)) {
					die('ERROR: Could not create the folder '.$this->tmp_folder);
				}
			}
			//~ // Make a "data" dir if it does not exist.
			//~ $this->data_folder = $folder . 'data/';
			//~ if (!file_exists($this->data_folder)) {
				//~ if (!mkdir($this->data_folder)) {
					//~ die('ERROR: Could not create the folder '.$this->data_folder);
				//~ }
			//~ }
			// Init. regions.
			$this->build_regions();
			//~ // Download businesses by District (Region->Zone->District)
			//~ $this->get_biz();
			
			// Save regions to disk as CSV
		}
		function build_regions() {
			$this->regions = [
				['id'=>1,'name'=>'Far-Western Development Region'],
				['id'=>2,'name'=>'Mid-Western Development Region'],
				['id'=>3,'name'=>'Western Development Region'],
				['id'=>4,'name'=>'Central Development Region'],
				['id'=>5,'name'=>'Eastern Development Region'],
				['id'=>6,'name'=>'Unknown']
			];
			foreach ($this->regions as $key => $region) {
				$this->regions[$key] = new region($region['id'], $region['name'], $this->tmp_folder); // Set each region to a class object.
			}
		}
		function get_biz() {
			foreach ($this->regions as $key => $region) {
				//foreach ($this->zones as $key => $zone_obj) {
				try {
					//~ print_r($region->zones);
				} catch(Exception $e) {
					echo 'Caught exception: ',  $e->getMessage(), "\n";
				}
			}
		}
	}
	// Folder = This is the location to save to, when necessary.
	$regions = new regions($argv[2]);
	
	
	
		//~ [
			//~ 'id'=>1,
			//~ 'post-id'=>'region,1',
			//~ 'name'=>'Far-Western Development Region',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
		//~ [
			//~ 'id'=>2,
			//~ 'post-id'=>'region,2',
			//~ 'name'=>'Mid-Western Development Region',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
		//~ [
			//~ 'id'=>3,
			//~ 'post-id'=>'region,3',
			//~ 'name'=>'Western Development Region',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
		//~ [
			//~ 'id'=>4,
			//~ 'post-id'=>'region,4',
			//~ 'name'=>'Central Development Region',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
		//~ [
			//~ 'id'=>5,
			//~ 'post-id'=>'region,5',
			//~ 'name'=>'Eastern Development Region',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
		//~ [
			//~ 'id'=>6,
			//~ 'post-id'=>'region,6',
			//~ 'name'=>'Unknown',
			//~ 'detail'=> null // To be filled in through CURL
		//~ ],
	//~ ];
	//~ foreach ($region as $key=>$value) {
		//~ 
	//~ }
	//~ curl_get();
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
	
	if  (
		property_exists($options, 'request_to_file') ||
		property_exists($options, 'request_from_file')
		)
	{
		$file = $options->folder . $options->filename; 
		if (property_exists($options, 'request_from_file') && file_exists($file)) {
			$return->isCached = true;
			$return->html = file_get_contents($file);
			$return->status = 200; // Echo 200 status.
			return $return;
		}
	}
	
	$ch = curl_init($options->url);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
	//~ curl_setopt($ch, CURLOPT_COOKIEFILE, '/var/www/html/cookie.txt');
	//~ curl_setopt($ch, CURLOPT_COOKIEJAR, '/var/www/html/cookie.txt');
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
	if  (
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
