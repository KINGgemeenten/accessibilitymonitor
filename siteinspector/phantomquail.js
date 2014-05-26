var system = require('system');
var page = require('webpage').create();
var address, dir;

page.onConsoleMessage = function (msg, line, source) {
  console.log(msg);
};

// Return early.
if (system.args.length <= 1) {
  console.log('Usage: phantomjs-quail.js <some URL> <guidelines>');
  phantom.exit();
}

page.settings.resourceTimeout = 60000; // 5 seconds
page.onResourceTimeout = function (e) {
  console.log(e.errorCode);   // it'll probably be 408
  console.log(e.errorString); // it'll probably be 'Network timeout on resource'
  console.log(e.url);         // the url whose request timed out
  phantom.exit(1);
};

// Open the page at the provided URL in Phantom.
address = system.args[1];
dir = '/usr/local/opt/siteinspector';
page.open(address, function (status) {
  if (status !== 'success') {
    console.log('FAIL to load the address');
  }
  else {
    var fs = require('fs');
    var guidelinedata = fs.read(dir + '/guideline.json');
    var guidelines = JSON.parse(guidelinedata);

    var testsdata = fs.read(dir + '/quail/dist/tests.json');
    var tests = JSON.parse(testsdata);
    // If a specific test is requested, just use that one.
    var testFromCLI = system.args[2];
    if (testFromCLI && tests[testFromCLI]) {
      var singleTest = tests[testFromCLI];
      tests = {};
      tests[testFromCLI] = singleTest;
    }

    // Inject assets into the page.
    page.injectJs(dir + '/js/jquery-1.10.1.min.js');
    page.injectJs(dir + '/js/jquery.hasEventListener-2.0.4.min.js');
    page.injectJs(dir + '/quail/dist/quail.jquery.js');

    // Handle results from the test runs.
    var len = size(tests);
    // Open a write stream to an output file.
    var stream = fs.open(dir + '/results.js', 'w');
    page.onCallback = function(data) {
      var test = JSON.parse(data);
      console.log('Finished testing ' + test.id + '.');
      stream.write(data);
      --len;
      // All the tests have completed.
      if (len === 0) {
        stream.close();
        quitPhantom('Testing complete');
      }
    };

    var testname;
    for (testname in tests) {
      page.evaluateAsync(function (address, tests, testname) {
        console.log('Running ' + testname + '...')
        jQuery.noConflict();
        var test = tests[testname];
        // Basic test attributes.
        var output = {
          id: testname,
          title: test.title,
          description: test.description,
          type: test.type,
          testability: test.testability,
          guidelines: test.guidelines || {},
          tags: test.tags,
          cases: []
        };
        jQuery('html').quail({
          accessibilityTests: tests,
          guideline: [testname],
          // Called when an individual Case in a test is resolved.
          caseResolve: function (eventName, test, _case) {
            output.cases.push({
              status: _case.get('status'),
              selector: _case.get('selector')
            });
          },
          // Called when all the Cases in a Test are resolved.
          testComplete: function (eventName, test) {},
          // Called when all the Tests in a TestCollection are completed.
          complete: function (eventName, testCollection) {
            // Push the results of the test out to the Phantom listener.
            if (typeof window.callPhantom === 'function') {
              window.callPhantom(JSON.stringify(output));
            }
          }
        });
      }, 0, testname, tests, address);
    }
  }
});

/**
 * Logs the reason for exit; exits Phantom.
 */
function quitPhantom (reason) {
  console.log('Exit' + (reason && (': ' + reason) || ''));
  phantom.exit();
}

/**
 * Determines the length of an object.
 *
 * @param object obj
 *   The object whose size will be determined.
 *
 * @return number
 *   The size of the object determined by the number of keys.
 */
function size (obj) {
  var size = 0, key;
  for (key in obj) {
    if (obj.hasOwnProperty(key)) {
      size++;
    }
  }
  return size;
}
