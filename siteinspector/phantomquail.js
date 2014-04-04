var page = require('webpage').create(),
  fs = require("fs"),
  system = require('system'),
  t, address;

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
var guidelinedata = fs.read('/opt/quail/examples/php/data/guideline.json');
var guidelines = JSON.parse(guidelinedata);

var testsdata = fs.read('/opt/quail/dist/tests.json');
var tests = JSON.parse(testsdata);


page.open(address, function (status) {
  if (status !== 'success') {
    console.log('FAIL to load the address');
  }
  else {
    t = Date.now() - t;

    page.injectJs('/js/jquery-1.10.1.js');
    page.injectJs('/js/jquery.hasEventListener-2.0.4.js');
    page.injectJs('/opt/quail/dist/quail.jquery.js');
    // Our "event loop"
    if (!phantom.state) {
      console.log('#start testing', address);
      init_test(guidelines, tests, address);
    }
    else {
      phantom.state();
    }
  }
  phantom.exit();
});


function init_test(guideline, tests, url) {
  //page.injectJs('/js/quail/dist/quail.jquery.js');
  page.evaluate(function () {
    jQuery.noConflict();
  });
  page.evaluate(function (guideline, tests, url) {
    var testResults = { totals: {}, failedTests: [] };

    //start quail
    jQuery('html').quail({
      guideline: guideline,
      accessibilityTests: tests,
      jsonPath: '/js/quail/dist',
      testFailed: function (event) {
        outerHTML = jQuery('<textarea />').append(event.element);
        outerHTML.val(outerHTML.html());
        var wcag = event.test.guidelines.wcag;
        if (wcag) {
          var res = {
            url: url,
            element: outerHTML.html().substr(0, 255),
            name: event.test.title.en,
            //fail:event.test,
            wcag: JSON.stringify(event.test.guidelines.wcag),
            tags: event.test.tags,
            testability: event.test.testability,
            testtype: event.test.type,
            severity: event.severity
          };
        }
        else {
          var res = {
            url: url,
            element: outerHTML.html().substr(0, 255),
            name: event.test.title.en,
            tags: event.test.tags,
            testability: event.test.testability,
            testtype: event.test.type,
            severity: event.severity
          };
        }
        testResults.failedTests.push(res);
        console.log(JSON.stringify(res));
      },
      complete: function (results) {
        var totals = {aggregated: results.totals, 'url': url};
        console.log(JSON.stringify(totals));

      }
    });
  }, guideline, tests, url);
  //console.log(JSON.stringify(testResults));
  //console.log('TIME: ',t);
  //console.log(JSON.stringify(page)):

}
