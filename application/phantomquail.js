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

var wcag2data = fs.read('/opt/quail/dist/wcag2.json');
var wcag2structure = JSON.parse(wcag2data);

// Save the testsdata in the array all tests.
// Some tests might need to be filtered out.
var allTests = JSON.parse(testsdata);
var tests = {};

// If a specific test is requested, just use that one.
var testFromCLI = system.args[2];

if (testFromCLI && allTests[testFromCLI]) {
  var singleTest = allTests[testFromCLI];
  tests = {};
  tests[testFromCLI] = singleTest;
}
else if (guidelines.length) {
  // Only add the tests which are defined in the guidelines.
  for ( var i = 0 ; i < guidelines.length; i++) {
    var key = guidelines[i];
    if (allTests[key]) {
      tests[key] = allTests[key];
    }
  }
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
        console.log('Cases found: ' + (output.stats && output.stats.cases || 0));
        var out = JSON.stringify(output);
        stream.write(out);
        // Also write the output to the console.
        console.log(out);
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
      page.evaluate(function (tests, size, wcag2structure) {
        var callPhantom = window && window.callPhantom || function () {};
        // Tell the client that we're starting the test run.
        var scLen = size(quail.guidelines.wcag.successCriteria);
        console.log('Beginning evaluation of ' + size(tests) + ' tests and ' + scLen + ' Success Criteria.');
        // Determine how many data writes we'll make.
        //console.log(JSON.stringify(tests));
        //console.log(JSON.stringify(wcag2structure));
        callPhantom('setCounter', 1); // +1 because we attempt a data write once for all tests on testCollectionComplete
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
          guideline: 'wcag2',
          accessibilityTests: tests,
          wcag2Structure: wcag2structure,
          // Called when all the Cases in a Test are resolved.
          testComplete: function (eventName, test) {
            console.log('Finished testing ' + test.get('name') + '.');
          //  // Increment the tests count.
          //  //output.stats.tests++;
          },
          // Called when all the Tests in a TestCollection are completed.
          testCollectionComplete: function (eventName, testCollection) {
            // Push the results of the test out to the Phantom listener.
            console.log('The test collection has been evaluated.');
            console.log(JSON.stringify(testCollection));
            callPhantom('writeData', JSON.stringify(testCollection));
          }
        });
      }, tests, size, wcag2structure);
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
