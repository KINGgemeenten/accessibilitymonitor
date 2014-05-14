var page = require('webpage').create();
var system = require('system');
var t, address;

if (system.args.length <= 1) {
  console.log('Usage: phantomjs-quail.js <some URL> <guidelines>');
  phantom.exit();
}

page.onConsoleMessage = function (msg, line, source) {
  console.log(msg);
};

page.settings.resourceTimeout = 60000; // 5 seconds
page.onResourceTimeout = function (e) {
  console.log(e.errorCode);   // it'll probably be 408
  console.log(e.errorString); // it'll probably be 'Network timeout on resource'
  console.log(e.url);         // the url whose request timed out
  phantom.exit(1);
};

t = Date.now();
address = system.args[1];
test = system.args[2];

/**
 * Determine the length of an object.
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

var quitPhantom = function (reason) {
  console.log('Exit' + (reason && (': ' + reason) || ''));
  phantom.exit();
}

page.open(address, function (status) {
  if (status !== 'success') {
    console.log('FAIL to load the address');
  }
  else {
    var fs = require('fs');
    var guidelinedata = fs.read('/opt/siteinspector/guideline.json');
    var guidelines = JSON.parse(guidelinedata);

    var testsdata = fs.read('/opt/quail/dist/tests.json');
    var tests = JSON.parse(testsdata);

    // Inject assets into the page.
    page.injectJs('/usr/local/opt/siteinspector/js/jquery-1.10.1.min.js');
    page.injectJs('/usr/local/opt/siteinspector/js/jquery.hasEventListener-2.0.4.min.js');
    page.injectJs('/usr/local/opt/siteinspector/quail/dist/quail.jquery.js');

    // Handle results from the test runs.
    var len = size(tests);
    // Open a write stream to an output file.
    var stream = fs.open('/usr/local/opt/siteinspector/results.js', 'w');
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
        var output = {
          id: testname,
          title: test.title,
          description: test.description,
          type: test.type,
          testability: test.testability,
          tags: test.tags,
          cases: []
        };
        jQuery('html').quail({
          accessibilityTests: tests,
          guideline: [testname],
          // testFailed: function (event) {
          //   console.log('// from the test failed function');
          //   outerHTML = jQuery('<textarea />').append(event.element);
          //   outerHTML.val(outerHTML.html());
          //   var wcag = event.test.guidelines.wcag;
          //   if (wcag) {
          //     var res = {
          //       url: url,
          //       element: outerHTML.html().substr(0, 255),
          //       name: event.test.title.en,
          //       //fail:event.test,
          //       wcag: JSON.stringify(event.test.guidelines.wcag),
          //       tags: event.test.tags,
          //       testability: event.test.testability,
          //       testtype: event.test.type,
          //       severity: event.severity
          //     };
          //   }
          //   else {
          //     var res = {
          //       url: url,
          //       element: outerHTML.html().substr(0, 255),
          //       name: event.test.title.en,
          //       tags: event.test.tags,
          //       testability: event.test.testability,
          //       testtype: event.test.type,
          //       severity: event.severity
          //     };
          //   }
          //   testResults.failedTests.push(res);
          //   console.log(JSON.stringify(res));
          // },
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
            testCollection.each(function (index, test) {
              //output += 'complete: ' + test.get('name');
            });
            if (typeof window.callPhantom === 'function') {
              window.callPhantom(JSON.stringify(output));
            }
          }
        });
      }, 0, testname, tests, address);
    }
  }
});
