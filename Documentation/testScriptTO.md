# Test script quail and sampling

## Overview

For quail tests to be run on websites, three things must be done:

1. Generating a queue of url's to test (sampling)
2. Testing each url using quail
3. Processing the data from quail to determine scoring on wcag

The sampling process doesn't need to be done in the same script as the testing by quail and the processing of the results.

## Sampling

The sampling process will contain the following steps:

- Check for a site if there are still url's that needs to be tested.
- If there are no url's, ask for each site 1000 url's from nutch in a (random) order.
- (Foreach url) If the url is not already in the url table, add the url in the url table and mark the url as 'to be tested'
- Add all url's marked as 'to be tested' to a queue table. The priority is determined by the row number. This works if we add url's per website.

The queue table consists of the following fields:

  - qid
  - urlid (int)
  - priority (int)
  - status

The status in the queue table is as follows:

    0: queued
    1: testing

When a test has been done, the record is removed from the queue table.

## Testing quail

The main problem when testing quail is speed. We need to parallel process in order to increase speed.

### Parallel processing

There are two ways to do parallel processing:

1. Run more than one script at a time.
2. Run one script and use threads to start more processes.