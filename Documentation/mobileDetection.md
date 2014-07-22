# Mobile detection

For mobile detection Google Pagespeed API is used. The testing application is hosted @ Google, there is a maximum of request per day of 25.000, Google is accepting requests for more requests per day when needed.

## Implementation

[Talking to the API](https://developers.google.com/speed/docs/insights/v1/getting_started#invoking) is easy, we need an API key for the Google Pagespeed API via the Google Developers Console.

send a request to the API:
https://www.googleapis.com/pagespeedonline/v1/runPagespeed?url=http://www.triquanta.nl/&key=xxxx&strategy=mobile

* url: the url of the page you want to test
* key: the API key from Google
* strategy: the set of test to run, Common are _desktop_ and _mobile_. We'll be using _mobile_

the result is a JSON document. This document contains the detailed results per test case, we are  at the moment only interested in the score (and response code to know the test went OK):

* score (integer on the scale of 0 .. 100)
* responseCode (needs to be 200)

## PHP function

The PHP code contains logic to determine the url's score on mobile, using Google Pagespeed API. It also contains timing logic to check the performance of the operaion for debugging purposes. The test seems to perform quite well on my local machine it fetches the result in less than a second. 

````php
/**
 * Perform tests on mobile performance using Google Pagespeed API.
 *
 * todo:
 *   Calculation of time spend is only for debuggin purposes, can be deleted from function.
 *
 * @param $url the URL to analyse.
 * @return string score on mobile strategy.
 */
function testMobile($url = NULL) {
  $google_pagespeed_api_url = 'https://www.googleapis.com/pagespeedonline/v1/runPagespeed';
  $google_pagespeed_api_key = 'AIzaSyA3Q_W9PO_ibkvSzVGxfncaMNNu3382lcw';
  $google_pagespeed_api_strategy = 'mobile';

  // Check if we have an url.
  if (!isset($url)) {
    exit();
  }

  // Get time before execution of API call, for measuring performance.
  $start = (float) array_sum(explode(' ',microtime()));

  // Get cURL resource
  $curl = curl_init();
  // Set some options - we are passing in a user agent too here
  curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $google_pagespeed_api_url . '?' .
        'key=' . $google_pagespeed_api_key .
        '&url=' . $url .
        '&strategy=' . $google_pagespeed_api_strategy,
      CURLOPT_USERAGENT => 'GT inspector script',
  ));

  // Send the request & save response.
  $result_string = curl_exec($curl);

  // Close request to clear up some resources
  curl_close($curl);

  // Decode the JSON result string to a PHP object to obtain values.
  $result = json_decode($result_string);

  // Get time after execution of API call, for measuring performance.
  $end = (float) array_sum(explode(' ',microtime()));

  // Calculate time spend, for debugging.
  $time_spend = sprintf("%.4f", ($end-$start))." seconds.";

  // Return score if we have one.
  if(isset($result->responseCode) && $result->responseCode == 200) {
    // For now return the score only.
    return $result->score;
  } else {
    return null;
  }

}
````

## Settings and setup

The Mobile scoring logic needs to be setup before it can be used. Using the example.settings.php you will find the following settings which need to be present in your settings.php:

* _google_pagespeed_api_url_ -> enter the Google API url here.
* _google_pagespeed_api_key_ -> enter you Google API key here.
* _google_pagespeed_api_strategy_ -> enter the testing strategy mobile or desktop.
* (optional) _google_pagespeed_api_fetch_limit_ -> enter a limit here to fetch in one execution.

When updating a server without Google pagespeed API mobile scoring, import the new database structure using the mysql structure update script:

`mobile_scoring_table_updater.sql`


## Execution on command line

use the option _google_pagespeed_ :

`	``
php inspector.php google_pagespeed
```