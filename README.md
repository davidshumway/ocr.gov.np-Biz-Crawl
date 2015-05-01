This PHP project scrapes businesses from http://www.ocr.gov.np/search/advanced_search.php. It is currently in progress.

To run the project run from a command line:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "[output folder]"```

For example:<br>
```php -f ocr.gov.np_biz_retrieval.php -- "/var/www/ocr.gov.np-Biz-Crawl/"```

Deleting the files in the "data" directory will allow the crawler to redownload the files from ocr.gov.np.

After running the PHP program two CSV files are created. The first CSV file is the complete database from the site. The second CSV is just a list of businesses registered in the past four years, since 2068.

