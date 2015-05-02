This PHP project gathers the database of businesses listed on the website http://www.ocr.gov.np/search/advanced_search.php. This project is currently in progress.

To run the project run the following from a command line, where the arguments are 1) the root folder to use for the project (a "data" folder is created here); and 2) an optional Google Translation API key, which, when provided, will try to translate the business address to English from Nepalese:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "output_folder"[, "OPTIONAL GOOGLE TRANSLATION API KEY"]```

For example:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "/var/www/ocr.gov.np-Biz-Crawl/" "API_KEY"```

This will download and cache the files from ocr.gov.np. It will then create two CSV files. The first CSV file is the complete database from the site. This file is about 58MB is size. The second CSV is a list of businesses registered in the past four years, since 2068. This file is about 19MB is size.

The program will reuse the cached files on any subsequent runs. To clear the cache and redownload the files manually delete the files in the "data" directory.

When an API key is provided, the program will attempt to translate all unique business addresses to English. In addition, the program will find the geolocation of regions and local regions. The furthest level in for locations is about the city or village level, with some businesses only coming up with a county geolocation. Precise geocoding is not available. Also, the geolocation is automated so it is really just a best guess and may not always be correct. The number of unique geocoded requests is about 3,500.

The website also categorizes the businesses. The file business-categories-translated.csv contains the categories including translation to English, or without English if no API key is provided. The most categories assigned to one business is 42. The total number of businesses with at least one category is 119,914.

As of April 30, 2015, the scrape produces 127,543 business registered in total, and 43,903 businesses registered since 2068. There are about 16,000 unique addresses and 112,000 duplicate addresses.

