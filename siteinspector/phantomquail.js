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
var guidelinedata = fs.read('/opt/siteinspector/guideline.json');
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
      accessibilityTests: tests,
      // Filter down to just one test for development.
      guideline: ['cssTextHasContrast'],
      // Called when an individual Case in a Test is resolved.
      caseResolve: function (eventName, test, _case) {
        // Do not turn this on unless you filter the tests.
        // It creates A LOT of data.
        console.log(_case.get('status') + "\t\t" + test.get('name'), "\n\t\t\t\t\t" + _case.get('message') + "\n\t\t\t\t\t" + _case.get('selector') + "\n");
      },
      // Called when all the Cases in a Test are resolved.
      testComplete: function (eventName, test) {
        console.log('testComplete: ' + test.get('name'));
      },
      // Called when all the Tests in a TestCollection are completed.
      complete: function (eventName, testCollection) {
        testCollection.each(function (index, test) {
          console.log('complete: ' + test.get('name'));
        });
      }
    });
  }, guideline, tests, url);
  //console.log(JSON.stringify(testResults));
  //console.log('TIME: ',t);
  //console.log(JSON.stringify(page)):

}
