# Technical design inspector script

## Mysql tables

The inspector script needs a database in order to function correctly. Now and then the tables should be altered.
Although it would be better to have a database management system, like the drupal schema api, for now we use the following
tactic:

  - In de directory sql-dump, there is a file names inspector-X.sql in which X is the version number of the schema.
    The dump only contains the structure of the table.
  - When altering the the table, the inspector-X.sql is updated. Also an sql is added named inspector-update-X.sql
    This file contains the mysql commands needed to update the schema from X-1 to X.

The following tables are present:

  - urls: table containing all url's that will be (are) tested.
  - website: domains that needs to be tested.
  - actions: the actions to perform
  - test_results: table containing test results of non quail tests

## Queueing quail analysis

In order to queue the urls to test, we use the following mechanism:

There is an urls table. New url's are added with a priority. New url's are added with status 0: to be tested.
So the queueing is priority combined with status.

## Scope: exclusion and adding of url's

The scope for websites and url's can be altered. Url's can be added, or excluded. Websites can also be added or excluded.
Added items are added to the urls or website table with status 0.
Excluded items get status 4 (STATUS_EXLUDED).

Setting status to excluded means that cleanup has to be done: results for quail tests must be removed. In order to do this
we need actions.

## Actions: exclude

When an url is excluded we need to do cleanup:

- remove results for the url and or website from solr.

The actions must be done by the backend script, but should be directed from Drupal.
We need the following table for the actions:

  table: actions
    aid: action_id
    action: action to perform
    item_uid: object to perform action for
    timestamp: timestamp when action is performed by the backend script.

The item_uid is either a domain, or a full url depending on the action. The action will added with timestamp 0.
When the action has been performed the table is updated with the timestamp.

The following actions are defined:

    excludeWebsite:
      - set the website to status excluded
      - delete all results for the website domain
      - update all url's to status excluded
    addWebsite:
      - Add the website to the website table if not already present
    rescanWebsite:
      - delete all results for the website domain
      - update all urls to status 0
    addUrl:
      - add the url to the url's table when the website belonging to the url is found. Set status to 0 and prio to 1.
    excludeUrl:
      - Check if the url is already in the url's table, and if so
        - remove results
        - set status to excluded.
        else
        - add url to urls table with status excluded.

The actions will be functions.

The following helper functions will be available:

      function deleteResults() {
        delete results from solr or mongo.
      }

      function trackUrl(url, status) {
        check if the url is already present
        if present {
          update status
        }
        else {
          insert
        }
      }

      function trackWebsite(website, status) {
        check if the website is already present
        if present {
          update status
        }
        else {
          insert
        }
      }

## Non quail tests.

Non quail tests can also be performed and will only be done on homepages i.e. website entries.
This will be done in the PhantomQuailWorker.

The results will be saved in a seperate table: testresults:

  table: test_results
    tid: test id, primary id
    wid: website id
    type: test type: cms, pagespeed
    result: varchar: simple result. Only strings allowed.

Results will also be saved in mongodb, so the full structered result may be saved.
The results will be saved in one colletion: test_results.

### Wappalyzer data

Simple result: detected cms
Structured result:

  cms
  technologies[]

### Google pagespeed data

Simple result: score.
Structered results:

   data retrieved from google.