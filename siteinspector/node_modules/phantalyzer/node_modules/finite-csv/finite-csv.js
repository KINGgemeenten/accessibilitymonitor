var fs = require('fs');

/**
 * This method will parse the CSV file
 * based on RFC 4180.  I wrote this mostly
 * to test out a Finite State Machine I've
 * been thinking about.  A Bison or Jison 
 * grammar would obviously be more concise.
 */
function parseCSV(str) {
  var position = 0;
  var field = null;
  var row = [];
  var result = [];
  var parent = this;
  var error = null;

  this.peek = function() {
    return position < str.length ? str.charAt(position) : null;
  };
  this.pop = function() {
    var ch = str.charAt(position);
    position++;
    return ch;
  };
  this.count = function() {
    return str.length - position;
  };

  var flowDef = {
    "start" : "ready_for_field",
    "transitions" : [
    {
      "from"   : "processing_field",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == null; },
    },
    {
      "from"   : "processing_field",
      "to"     : "error",
      "guard"  : function() { return parent.peek() == '"'; },
      "action" : function() { error = "fields cannot contain quotes unless they are entirely contained by quotes. [" + field + "\"]"; }
    },
    {
      "from"   : "processing_field",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == ','},
      "action" : function() {
	var ch = parent.pop();
	row.push(field);
	field = null;
	//console.log("pushing field");
      }
    },
    {
      "from"   : "processing_field",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == "\n"; },
      "action" : function() {
	ch = parent.pop();
	row.push(field);
	result.push(row);
	row = [];
	field = null;
	//console.log("pushing field and record");
      }
    },
    {
      "from"   : "processing_field",
      "to"     : "processing_field",
      "action" : function() {
	ch = parent.pop();
	field += ch;
	//console.log("char " + ch);
      }
    },
    {
      "from"   : "ready_for_field",
      "to"     : "end",
      "guard"  : function() { return parent.peek() == null; },
      "action" : function() {
	if ( field != null ) {
	  row.push(field);
	}
	result.push(row);
	//console.log("file end");
      },
    },
    {
      "from"   : "ready_for_field",
      "to"     : "quote_mode",
      "guard"  : function() { return parent.peek() == '"'; },
      "action" : function() { parent.pop(); field = ''; }
    },
    {
      "from"   : "ready_for_field",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == "\r"; },
      "action" : function() { parent.pop(); }
    },
    {
      "from"   : "ready_for_field",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == "\n"; },
      "action" : function() { parent.pop(); result.push(row); row = []; }
    },
    {
      "from"   : "ready_for_field",
      "to"     : "processing_field",
      "action" : function() { field = ''; }
    },
    {
      "from"   : "quote_mode",
      "to"     : "error",
      "guard"  : function() { return parent.peek() == null; },
      "action" : function() { error = "unexpected EOF while reading quoted field"; }
    },
    {
      "from"   : "quote_mode",
      "to"     : "quote_mode",
      "guard"  : function() { return parent.count() > 1 && parent.peek() == '"' && str.charAt(position + 1) == '"'; },
      "action" : function() { parent.pop(); parent.pop(); field += '"'; }
    },
    {
      "from"   : "quote_mode",
      "to"     : "quote_complete",
      "guard"  : function() { return parent.peek() == '"'; },
      "action" : function() {
	parent.pop();
	row.push(field);
	field = null;
      }
    },
    {
      "from"   : "quote_mode",
      "to"     : "quote_mode",
      "action" : function() {
        var ch = parent.pop();
	//console.log("qchar " + ch);
        field += ch;
      }
    }, 
    {
      "from"   : "quote_complete",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == ','; },
      "action" : function() { parent.pop(); }
    },
    {
      "from"   : "quote_complete",
      "to"     : "end",
      "guard"  : function() { return parent.peek() == null; },
      "action" : function() { result.push(row); }
    },
    {
      "from"   : "quote_complete",
      "to"     : "quote_complete",
      "guard"  : function() { return parent.peek() == "\r"; },
      "action" : function() { parent.pop(); }
    },
    {
      "from"   : "quote_complete",
      "to"     : "ready_for_field",
      "guard"  : function() { return parent.peek() == "\n"; },
      "action" : function() {
	parent.pop();
	result.push(row);
	row = [];
      }
    }
    ]
  };

  var workflow = new FiniteStateMachine(flowDef);
  workflow.enterStartState();
  if ( workflow.current == 'error' ) {
    throw error;
  }
  return result;
}

function FiniteStateMachine(flow) {
  this.flow = flow;
  this.current = flow.start;

  this.enterStartState = function() {
    this.processEvent();
  }

  this.processEvent = function(eventName) {
    //console.log('tate=' + this.current + ' processEvent(' + eventName + ')');
    
    while (true) {
      var foundTransition = false;
      //var relevant = this.findRelevant(eventName);
      //console.log('r=' + relevant);
      var relevant = false
      for ( var i = 0; i < this.flow.transitions.length; i++ ) {
        var t = this.flow.transitions[i];
        if ( t.from != this.current ) continue;
        if ( t.event != eventName ) continue;
        if ( t.guard == undefined || t.guard() ) {
          relevant = t;
          break;
        }
      }
    
      if ( relevant ) {
        //console.log('  ' + relevant.from + "\t ==> " + relevant.to);
        this.current = relevant.to;
        eventName = undefined;
        /* if an action is called then it could publish an event */
        if ( relevant.hasOwnProperty('action') ) {
          relevant.action();
        }
      } else {
        return;
      }
    }
  };
}

exports.parseCSV = parseCSV;
