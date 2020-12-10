let serverEUrl = "http://localhost/speedtest/";
let serverGUrl = "http://localhost/speedtest/blank.file";
let status = "";

// TODO - check 5 connection together

async function startTest(){
  if(status == "" || status == "stop"){
    status = "start";
    setText("button", "Stop Test");
  }
  else if(status == "start"){
    status = "stop";
    setText("status", "stoping...");
    return;
  }

  setText("status", "checking ping...");
  var pingMS = await ping();
  setText("ping", pingMS);

  if(status == "stop"){stopTest();return;}

  setText("status", "checking download...");
  var downMB = await download();
  setText("down", downMB);

  if(status == "stop"){stopTest();return;}

  setText("status", "checking upload...");
  var downMB = await upload();
  setText("up", downMB);

  status = "";
  setText("status", "done.");
  setText("button", "Start Test");
}

function stopTest(){
  status = "";
  setText("button", "Start Test");
  setText("status", "stoped.");
}

function setText(elm, text){
  document.getElementById(elm).innerText = text;
}

async function ping() {
  return new Promise(resolve => {
    var startTime, request = new XMLHttpRequest();
    request.open("HEAD", serverEUrl + "?_=" + Math.random(), true);
    request.onreadystatechange = function() {
      if (request.readyState === 4) {
        var endTime = new Date().getTime();
        resolve((endTime - startTime) + " ms");
      }
    };
    try {
      startTime = new Date().getTime();
      request.send(null);
    } catch(exception) {
      alert("Your internet connection is unavelible...");
    }
  });
}

async function download(){
  return new Promise(resolve => {
    var startTime, request = new XMLHttpRequest();
    request.open("GET", serverGUrl + "?_=" + Math.random(), true);
    request.onreadystatechange = function() {
      if (request.readyState === 4 && request.status === 200) {
        var endTime = new Date().getTime();
        var reqLength = request.response.length * 8;
        var sumTime = (endTime - startTime) / 1000;
        var speed = (((reqLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
        resolve(speed + " Mbps (" + sumTime + " s)");
      }
    };
    request.onprogress = function(){
      var reqLength = request.response.length * 8;
      var sumTime = (new Date().getTime() - startTime) / 1000;
      var speed = (((reqLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
      setText("down", speed + " Mbps (" + sumTime + " s)");
    }
    try {
      startTime = new Date().getTime();
      request.send(null);
    } catch(exception) {
      alert("Your internet connection is unavelible...");
    }
  });
}

function junkData(length){
  var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', result = '';
  for (var i = 0; i < length; i++) result += chars[Math.floor(Math.random() * chars.length)];
  return result;
}

async function upload(){
  return new Promise(resolve => {
    var startTime, junkLength = 2500000; //=2.5 Mb
    var junk = junkData(junkLength);
    var request = new XMLHttpRequest();
    request.open("POST", serverEUrl + "?_=" + Math.random(), true);
    request.setRequestHeader("Content-Type", "application/octet-stream");
    request.onreadystatechange = function() {
      if (request.readyState === 4 && request.status === 200) {
        var endTime = new Date().getTime();
        var sumTime = (endTime - startTime) / 1000;
        var speed = ((((junkLength * 8 /* byte (not bit) */) / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
        resolve(speed + " Mbps (" + sumTime + " s)");
      }
    };
    request.onprogress = function(){
      var sumTime = (new Date().getTime() - startTime) / 1000;
      var speed = ((((junkLength * 8 /* byte (not bit) */) / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
      setText("up", speed + " Mbps (" + sumTime + " s)");
    }
    try {
      startTime = new Date().getTime();
      request.send(junk);
    } catch(exception) {
      alert("Your internet connection is unavelible...");
    }
  });
}
