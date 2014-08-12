var system = require('system');
var page = require('webpage').create();
var fs = require('fs');
var address, dir;

page.onConsoleMessage = function (msg) {
  console.log(msg);
};

// Return early.
if (system.args.length <= 1) {
  console.log('Usage: phantomjs-quail.js <some URL> <guidelines>');
  phantom.exit();
}

// Catch script evaluation errors; quit Phantom.
page.onError = function (msg, trace) {
  console.log(JSON.stringify([
    'Error on the evaluated page',
    msg,
    trace
  ], undefined, 2));
};

page.settings.resourceTimeout = 5000; // 5 seconds

page.onResourceRequested = function (request) {
  console.log(JSON.stringify([
    'Requested (' + request.method + ')',
    request.url
  ]));
};

page.onResourceReceived = function(response) {
  console.log(JSON.stringify([
    'Received',
    response.status,
    response.url
  ]));
};

page.onResourceTimeout = function (error) {
  console.log(JSON.stringify([
    'Resource timeout',
    error.errorCode, // it'll probably be 408
    error.errorString, // it'll probably be 'Network timeout on resource'
    error.url // the url whose request timed out
  ]));
};

page.onResourceError = function (error) {
  console.log(JSON.stringify([
    'Resource error',
    'Error code: ' + error.errorCode,
    error.errorString,
    error.url
  ], undefined, 2));
};

// This is the last chance to catch catestrophic errors.
phantom.onError = function(msg, trace) {
  console.log(JSON.stringify([
    'Error in the phantom runner',
    msg,
    trace
  ], undefined, 2));
};

// Open the page at the provided URL in Phantom.
address = system.args[1];

// We need the path of the script.
// This is kind of a workaround, but it works.
var relativeScriptPath = system.args[0];
var absoluteScriptPath = fs.absolute(relativeScriptPath);
var absoluteScriptDir = absoluteScriptPath.substring(0, absoluteScriptPath.lastIndexOf('/'));

dir = absoluteScriptDir;

var guidelinedata = fs.read(dir + '/guideline.json');
var guidelines = JSON.parse(guidelinedata);

var testsdata = fs.read('/opt/quail/dist/tests.json');
// Save the testsdata in the array all tests.
// Some tests might need to be filtered out.
var allTests = JSON.parse(testsdata);
var tests = {};

// Only add the tests which are defined in the guidelines.
for ( var i = 0 ; i < guidelines.length; i++) {
  var key = guidelines[i];
  if (allTests[key]) {
    tests[key] = allTests[key];
  }
}

// If a specific test is requested, just use that one.
var testFromCLI = system.args[2];

if (testFromCLI && allTests[testFromCLI]) {
  var singleTest = allTests[testFromCLI];
  tests = {};
  tests[testFromCLI] = singleTest;
}
else {
  tests = allTests;
}

// The number of items that will attempt to write data from the evaluation.
// When the evaulation starts, it will register how many items will
// report back.
var len = 0;
// Open a write stream to an output file.
var stream = fs.open(dir + '/results.js', 'w');
// The data to be written to file.
var output = {};
var start = (new Date()).getTime();
// The callback function reachable from the page.evaluate* methods.
page.onCallback = function(action, data) {
  switch (action) {
    // Len is the number of times we expect to log data.
    case 'setCounter':
      len = data;
      break;
    case 'writeData':
      --len;
      // Store all the keys in the object to an output object.
      data = JSON.parse(data);
      if (typeof data === 'object') {
        for (var key in data) {
          // Tests and Success Criteria are situated under their own keys.
          if (key === 'tests' || key === 'successCriteria') {
            if (!output[key]) {
              output[key] = {};
            }
            for (var name in data[key]) {
              output[key][name] = data[key][name];
            }
          }
          else {
            output[key] = data[key];
          }
        }
      }
      // All the tests have completed.
      if (len === 0) {
        console.log('Elapsed time: ' + ((new Date()).getTime() - start) / 1000 + ' seconds');
        console.log('Cases found: ' + output.stats.cases);
        var out = JSON.stringify(output);
        stream.write(out);
        stream.close();
        quitPhantom('Testing complete');
      }
      break;
    case 'quit':
      quitPhantom(data);
      break;
    default:
      break;
  }
};

page.open(address);

// Decorate the page once the HTML has been loaded.
// This is where we run the tests.
page.onLoadFinished = function (status) {
  if (status === 'success') {
    console.log('Page opened successfully: ' + address);
    page.injectJs('js/jquery-1.10.1.js');
    page.injectJs('js/jquery.hasEventListener-2.0.4.js');
    page.injectJs('/opt/quail/dist/quail.jquery.js');

    // Run the evaluation.
    //
    // The evaluation is executed in its own function scope. Closures that
    // incorporate outside scopes are not possible.
    try {
      page.evaluate(function (tests, size) {
        var callPhantom = window && window.callPhantom || function () {};
        // Tell the client that we're starting the test run.
        var scLen = size(quail.guidelines.wcag.successCriteria);
        console.log('Beginning evaluation of ' + size(tests) + ' tests and ' + scLen + ' Success Criteria.');
        // Determine how many data writes we'll make.
        callPhantom('setCounter', scLen + 1); // +1 because we attempt a data write once for all tests on testCollectionComplete
        // Basic output structure attributes.
        var output = {
          tests: {},
          successCriteria: {},
          stats: {
            tests: 0,
            cases: 0
          }
        };
        jQuery('html').quail({
          accessibilityTests: tests,
          // Called when an individual Case in a test is resolved.
          caseResolve: function (eventName, test, _case) {
            var name = test.get('name');
            if (!output.tests[name]) {
              output.tests[name] = {
                id: name,
                title: test.get('title'),
                description: test.get('description'),
                type: test.get('type'),
                testability: test.get('testability'),
                guidelines: test.get('guidelines') || {},
                tags: test.get('tags'),
                cases: []
              };
            }
            // Push the case into the results for this test.
            output.tests[name].cases.push({
              status: _case.get('status'),
              selector: _case.get('selector'),
              html: _case.get('html')
            });
            // Increment the cases count.
            output.stats.cases++;
          },
          // Called when all the Cases in a Test are resolved.
          testComplete: function (eventName, test) {
            console.log('Finished testing ' + test.get('name') + '.');
            // Increment the tests count.
            output.stats.tests++;
          },
          // Called when all the Tests in a TestCollection are completed.
          testCollectionComplete: function (eventName, testCollection) {
            // Push the results of the test out to the Phantom listener.
            console.log('The test collection has been evaluated.');
            callPhantom('writeData', JSON.stringify(output));
          },
          successCriteriaEvaluated : function (eventName, successCriteria, testCollection) {
            var name = successCriteria.get('name');
            var status = successCriteria.get('status');
            var output = {
              successCriteria: {}
            };
            var result;
            // Get some stringifyable data from the results.
            var looper = function (index, _case) {
              output.successCriteria[name][result].push({
                selector: _case.get('selector'),
                html: _case.get('html')
              });
            };

            // Push the results of the test out to the Phantom listener.
            // If the SC was untested, report that as its status.
            if (status === 'untested') {
              output.successCriteria[name] = status;
            }
            // Otherwise get the cases and report them under their status.
            else {
              output.successCriteria[name] = {};
              var results = successCriteria.get('results');
              for (result in results) {
                if (results.hasOwnProperty(result)) {
                  output.successCriteria[name][result] = [];
                  // Go through each case for this result and get its selector and HTML.
                  results[result].each(looper);
                }
              }
            }
            // Echo
            console.log('Evaluated: ' + name, 'conclusion: ' + status);
            // Attempt to write out the data.
            callPhantom('writeData', JSON.stringify(output));
          }
        });
      }, tests, size);
    }
    catch (error) {
      callPhantom('quit', error);
    }
  }
  else {
    callPhantom('quit', 'Page failed to load');
  }
};

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
  var s = 0, key;
  for (key in obj) {
    if (obj.hasOwnProperty(key)) {
      s++;
    }
  }
  return s;
}
