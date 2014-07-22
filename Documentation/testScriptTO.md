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
  - ignored

The status in the queue table is as follows:

    0: queued
    1: testing
    
The ignored in the queue tables has the following options:
	0: not ignored
	1: to be ignored

When a test has been done, the record is removed from the queue table.

## Testing quail

The main problem when testing quail is speed. We need to parallel process in order to increase speed.

### Parallel processing

There are two ways to do parallel processing:

1. Run more than one script at a time.
2. Run one script and use threads to start more processes.

We are going to use one script and start threads using pthreads to parallel process.

### Testing pseudo code

    class QuailTester {

      protected workers = array();
      protected finished_workers = array();

      protected pdo;

      public function __construct(maxTime, int workers, PDO pdo) {
        // set the max time the tester may test.

        // Set the pdo.
      }

      public function test() {
        // while max time is not exceded: {

          // Pick the amount of workers from the queue.

          // Update the queue to indicate that the items are being tested.

          // Create the workers

          // Process the finished workers.

          // Join the workers and put them in the finishedWorkers array.
        }
      }

      protected function processFinishedWorkers() {
        // Write the results to the database.
      }


    }

### Hanging threads

Very often the threads hang, which causes the whole inpector script to become stalled.
To account for this, the method of thread handling should be altered:

Quailtester:

- Alter the getTestingUrls function to getTestingTarget so only one result is added each time.
- Add workers to the workers array until the maximum is reached.
- Do some general work
- Sleep for 0.1 seconds (100000000 nanoseconds).
- Check the workers. If they are done, or exceed the maximum time, join or kill it, put it in the finishedWorkers array and unset it from the workers array.

PhantomQuailWorker:

- Record the start time in the constructor
- Add a method to check if a timeout occured
- Store the process in execTimeout in a object var
- Alter the kill method, so it also kills the phantomjs process.
