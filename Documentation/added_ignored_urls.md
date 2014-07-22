# Added and ignored URL's

This design describes the use case of urls which need to be added to and ignored on a domain.
The use case is af follows:

```
A use has the posibility to add single urls to a domain. The user also has the posibility to ignore single urls for a domain. This adding and ignoring of urls does not require crawling.

Added urls need to be included in the scoring process, ignored url's should not be included in the scoring process.
```

## Add to be added urls

Adding of urls is pretty streight forward, se the following pseudo code:

````
function update_added_urls() {
	// Read to be added urls from db.
	// select * from {single_urls} where operation = 'add' and status=1;
	
	// Match urls with website id's from db.
	
	// If there is a match save create a new record in db table {urls} using:
	// wid, full to be added url, STATUS_SCHEDULED, priority -> 1.
}

````

This code needs to be run on every `update-sitelist`.

## Filtering urls in Quail worker

As ignoring urls is done in the inpector script, filtering out ignored urls need to be done in the Quail fetching phase, since here we are getting results back.

todo: is this the right time?

the following pseudo code can be used to update existing urls to be ignored.
```
function update_ignored_urls() {
	// Read to be ignored urls from db.
	// select * from {single_urls} where operation = 'ignore' and status=1;
	
	// Loop over urls and set them the be ignored=1 in the {urls} table.
}
```

Now we have set a ignore flag in the DB, we also need to incorporate the the check for ignored urls in all db queries. There are some in the `QuailTester.php` script.



## Extending of db

We need to extend the DB urls table with a column 'ignored'. This column can have the following satuses:

- 0: not ignored
- 1: to be ignored
	
use the following command in MySQL CLI:

```
ALTER TABLE urls ADD ignored int(1) AFTER priority;
```

## Remove results from Solr

Wheb urls are set to be ignored, we need to check that the results are also removed from Solr, so already tested results can not end up as part of the score anymore.

for this the following pseudo code is implemented:
````
function remove_ignored_urls_from_solr() {
  // Get a list of all the active removed urls.
  
  // Remove alle single active removed urls from
}
````

This code needs to run on every `update-sitelist`.


## todo

in Drupal TD:

- move single_url table to inspector db.
- add column status 1=active 0:inactive
- when url is added to table (ignored or added) set status to 1=active
- wheb url is removed (not ignored or added) set status to 0=inactive
- this all is for adding a mechanism for handling removing of to be added or deleted urls from the website node in Drupal.