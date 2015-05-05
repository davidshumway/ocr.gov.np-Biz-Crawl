This PHP project gathers the database of businesses listed on the website http://www.ocr.gov.np/search/advanced_search.php. This project is currently in progress.

To run the project run the following from a command line, where the arguments are 1) the root folder to use for the project (a "data" folder is created here); 2) optional Google Translation API key, which, when provided, will try to translate the business address to English from Nepalese; 3) optional Facebook API key; and 4) optional geonames.org username API key:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "ROOT FOLDER"[, "OPTIONAL GOOGLE API KEY", "OPTIONAL FACEBOOK API KEY", "OPTIONAL GEONAMES API KEY"]```

For example:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "/var/www/ocr.gov.np-Biz-Crawl/" "GOOGLE_API_KEY" "FACEBOOK_API_KEY" "GEONAMES_API_KEY"```

This will download and cache the files from ocr.gov.np. It will then create two CSV files. The first CSV file is the complete database from the site. This file is about 58MB is size. The second CSV is a list of businesses registered in the past four years, since 2068. This file is about 19MB is size.

The program will reuse the cached files on any subsequent runs. To clear the cache and redownload the files manually delete the files in the "data" directory.

When an API key is provided, the program will attempt to translate all unique business addresses to English. In addition, the program will find the geolocation of regions and local regions. The furthest level in for locations is about the city or village level, with some businesses only coming up with a county geolocation. Precise geocoding is not available. Also, the geolocation is automated so it is really just a best guess and may not always be correct. The number of unique geocoded requests is about 3,500.

The website also categorizes the businesses. The file business-categories-translated.csv contains the categories including translation to English, or without English if no API key is provided. The most categories assigned to one business is 42. The total number of businesses with at least one category is 119,914.

As of April 30, 2015, the scrape produces 127,543 business registered in total, and 43,903 businesses registered since 2068. There are about 16,000 unique addresses and 112,000 duplicate addresses.

If a Facebook application API key is supplied then the program will retrieve Facebook "Places" throughout Nepal.

<h3>Google Fusion Tables</h3>

The following CSV data is also on Google's Fusion Tables, which uses the geocoding information for mapping.

Businesses on Google Places: https://www.google.com/fusiontables/data?docid=1JC4o6Z078CYML0CVDVBK9OetK_q6MXkEBIFPyGoD#map:id=3
The search is based on the following business types: ```Car Repair, Electrician, General Contractor, Hardware Store, Home Goods Store, Locksmith, Moving Company, Plumber, Roofing Contractor```.

In addition, the program also searches across a set of keywords: ```construction OR cement OR mortar OR building OR "building supplies" OR materials OR tools OR contractor OR "power tools" OR "hand tools" OR "green building" OR "green home" OR builders OR "eco house" OR "sustainable construction" OR renovate OR roofing OR drill OR engineering OR machinery OR supplier OR engineer OR engineering```

Keywords Only: https://www.google.com/fusiontables/data?docid=1PM3J43hbt1JTpLnOWEMO0F--sOFeE9VzfWFOtE6b#map:id=3
The keywords only portion of the above search.

Types Only: https://www.google.com/fusiontables/data?docid=1gyiRo3FPoNUXfqIfvsIeHVTpC8pahBGT_1PE3US9#map:id=3
The types only portion of the above search.

Businesses on Google Places, Northern India & Nepal:

Keywords Only: https://www.google.com/fusiontables/data?docid=1xNRCmMSgJ3UUNPSnHdYVIYBuXGXPhq16Jx65nNND#map:id=3
Same search as ```1PM3J43hbt1JTpLnOWEMO0F--sOFeE9VzfWFOtE6b``` above but with additional N. India locations.

Types Only:  https://www.google.com/fusiontables/data?docid=1J23kTW8j8GMrLm8m48QQ5v7eG1D4ddb_0ssFJYCe#map:id=3
Same search as ```1gyiRo3FPoNUXfqIfvsIeHVTpC8pahBGT_1PE3US9``` above but with additional N. India locations.

Businesses on Google Places, Northern India & Nepal (tents, shelter materials): https://www.google.com/fusiontables/data?docid=1Jl84nOxm36sWZbHaxA7XuOjcQY0NHTR5gyJiiQ_A#map:id=3
Set of keywords: ```tent OR tents OR tenting OR tarp OR tarps OR tarpaulin OR tarpauline OR "building supplies" OR material OR fabric OR fabrics OR outdoor OR upholstery OR cotton OR textile OR textiles OR quilt OR shelter OR survival OR backpacking OR nylon OR silnylon OR hammock OR stakes OR camping OR tarptent OR canopy OR canopies OR weatherproof```

Facebook Places (~500): https://www.google.com/fusiontables/data?docid=1shrVLCEiy13cZJ5_EWggZ83ScF-92kYDf_-iEiDq
This is a list of all Facebook places in the country.


