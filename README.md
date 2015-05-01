This PHP project gathers the database of businesses listed on the website http://www.ocr.gov.np/search/advanced_search.php. This project is currently in progress.

To run the project run the following from a command line, where the arguments are 1) the root folder to use for the project (a "data" folder is created here); and 2) an optional Google Translation API key, which, when provided, will try to translate the business address to English from Nepalese:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "[output folder]"[, "OPTIONAL GOOGLE TRANSLATION API KEY"]```

For example:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "/var/www/ocr.gov.np-Biz-Crawl/" "[API KEY]"```

This will download and cache the files from ocr.gov.np. It will then create two CSV files. The first CSV file is the complete database from the site. This file is about 58MB is size. The second CSV is a list of businesses registered in the past four years, since 2068. This file is about 19MB is size.

The program will reuse the cached files on any subsequent runs. To clear the cache and redownload the files manually delete the files in the "data" directory.

As of April 30, 2015, the scrape produces 127,543 business registered in total, and 43,903 businesses registered since 2068.

When an API key is provided, the program will attempt to translate all unique business addresses to English.
