let serverEUrl = "http://localhost/speedtest/e.php";
let serverGUrl = "http://localhost/speedtest/g.php";
let status = "";

function startTest(){
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
  ping((ms) => {
    setText("ping", ms);
    if(status == "stoped"){stopTest();return;}
    setText("status", "checking download...");
    download((mb) => {
      setText("down", mb);
      if(status == "stoped"){stopTest();return;}
      setText("status", "checking upload...");
      upload((mb) => {
        setText("up", mb);
        if(status == "stoped"){stopTest();return;}
        setText("status", "done.");
        setText("button", "Start Test");
      });
    });
  });
}

function stopTest(){
  setText("button", "Start Test");
  setText("status", "stoped.");
}

function setText(elm, text){
  document.getElementById(elm).innerText = text;
}

function ping(callback) {
  var startTime = 0;
  var request = new XMLHttpRequest();
  request.open("HEAD", serverEUrl + "?_=" + Math.random(), true);
  request.onreadystatechange = function() {
    if (request.readyState == 4) {
      var endTime = new Date().getTime();
      callback(endTime - startTime);
    }
  };
  try {
    startTime = new Date().getTime();
    request.send(null);
  } catch(exception) {
    alert("Your internet connection is unavelible...");
  }
}

function download(callback){
  var startTime = 0;
  var request = new XMLHttpRequest();
  request.open("GET", serverGUrl, true);
  request.onreadystatechange = function() {
    if (request.readyState == 4 && request.status === 200) {
      var endTime = new Date().getTime();
      var reqLength = request.responseText.length * 8;
      var sumTime = (endTime - startTime) / 1000;
      var speed = (((reqLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
      callback(speed + " Mbps");
    }
  };
  try {
    startTime = new Date().getTime();
    request.send(null);
  } catch(exception) {
    alert("Your internet connection is unavelible...");
  }
}

function junkData(length){
  var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()`[]\\{}|:"<>?,./;\'', result = '';
  for (var i = 0; i < length; i++) result += chars[Math.floor(Math.random() * chars.length)];
  return result;
}

function upload(callback){
  var startTime = 0;
  var junkLength = 2500000;
  var junk = junkData(junkLength); //=2.5 Mb
  var request = new XMLHttpRequest();
  request.open("POST", serverEUrl + "?_=" + Math.random(), true);
  request.setRequestHeader("Content-Type", "application/octet-stream");
  request.onreadystatechange = function() {
    if (request.readyState == 4 && request.status === 200) {
      var endTime = new Date().getTime();
      var sumTime = (endTime - startTime) / 1000;
      var speed = (((junkLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
      callback(speed + " Mbps");
    }
  };
  try {
    startTime = new Date().getTime();
    request.send(junk);
  } catch(exception) {
    alert("Your internet connection is unavelible...");
  }
}
