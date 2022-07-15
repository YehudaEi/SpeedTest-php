const serverPUrl = "https://localhost/speedtest/";
const serverDUrl = "https://localhost/speedtest/blank.file";
const serverUUrl = "https://localhost/speedtest/empty.file";
const connectionTogether = 5;

let status = "";
let multi = false; //multi connection together (5)

async function startTest() {
    if (status == "" || status == "stop") {
        status = "start";
        setText("button", "Stop Test");
        setText("ping", "");
        setText("down", "");
        setText("up", "");
        setText("status", "");

        multi = document.getElementById("multi-signal").checked;
    } else if (status == "start") {
        status = "stop";
        setText("status", "stoping...");
        return;
    }

    setText("status", "checking ping...");
    var pingMS = await ping();
    setText("ping", pingMS);

    if (status == "stop") {
        stopTest();
        return;
    }

    setText("status", "checking download...");
    var downMB = await download((multi ? connectionTogether : 1));
    setText("down", downMB);

    if (status == "stop") {
        stopTest();
        return;
    }

    setText("status", "checking upload...");
    var upMB = await upload((multi ? connectionTogether : 1));
    setText("up", upMB);

    status = "";
    setText("status", "done.");
    setText("button", "Start Test");
}

function stopTest() {
    status = "";
    setText("button", "Start Test");
    setText("status", "stoped.");
}

function setText(elm, text) {
    document.getElementById(elm).innerText = text;
}

async function ping() {
    return new Promise(resolve => {
        var startTime = 0;
        var request = new XMLHttpRequest();
        request.open("HEAD", serverPUrl + "?_=" + Math.random(), true);
        request.onreadystatechange = function() {
            if (request.readyState === 4) {
                var endTime = new Date().getTime();
                resolve((endTime - startTime) + " ms");
            }
        };
        try {
            startTime = new Date().getTime();
            request.send(null);
        } catch (exception) {
            alert("Your internet connection is unavelible...");
        }
    });
}

async function sleep(ms) { 
    return new Promise(resolve => setTimeout(resolve, ms)); 
}

async function download(multiConnection) {
    // return new Promise(resolve => {
        var startTime = 0;
        var requests = {"status":[], "speed":[], "requests":[]};
        
        for(var i = 0; i < multiConnection; i++){
            var tmpReq = new XMLHttpRequest();
            tmpReq.open("GET", serverDUrl + "?_=" + Math.random(), true);
            tmpReq.onreadystatechange = function() {
                if(status == "stop"){
                    tmpReq.abort();
                    requests['status'][i] = "stoped";
                }
                if (tmpReq.readyState === 4) {
                    if(tmpReq.status !== 200) console.log(tmpReq.status);
                    var endTime = new Date().getTime();
                    var reqLength = tmpReq.responseText.length * 8;
                    var sumTime = (endTime - startTime) / 1000;
                    var speed = (((reqLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
                    
                    requests['status'][i] = "done";
                    requests['speed'][i] = speed;
                }
            };
            tmpReq.onprogress = function() {
                if(status == "stop"){
                    tmpReq.abort();
                    requests['status'][i] = "stoped";
                }
                
                var endTime = new Date().getTime();
                var reqLength = tmpReq.responseText.length * 8;
                var sumTime = (endTime - startTime) / 1000;
                var speed = (((reqLength / sumTime) / 1024 /* Kbps */) / 1024 /* Mbps */).toFixed(2);
                
                requests['status'][i] = "progress";
                requests['speed'][i] = speed;
            };
            
            requests['status'][i] = "start";
            requests['speed'][i] = "0";
            requests['requests'][i] = tmpReq;
        }
        
        startTime = new Date().getTime();
        for(var i = 0; i < multiConnection; i++){
            try {
                requests['requests'][i].send(null);
            }
            catch(exception) {
              alert("Your internet connection is unavelible...");
            }
        }
        
    	while(true){
    	    await sleep(100);

    	    done = true;
        	var tmpTotalSpeed = 0;
    	    for(var i = 0; i < multiConnection; i++){
    	        if(done && requests['status'][i] != "done")
    	            done = false;

    	        tmpTotalSpeed += requests['speed'][i];
    	    }
    	    
	        var totalEndTime = new Date().getTime();
    	    var totalSpeed = tmpTotalSpeed / multiConnection;
    	    var totalSumTime = (totalEndTime - startTime) / 1000;
    	    
    	    if(done){
    	        return /*resolve*/(totalSpeed + " Mbps (" + totalSumTime + " s)");
    	       // return;
    	    }
    	    else{
    	        setText("down", totalSpeed + " Mbps (" + totalSumTime + " s)");
    	    }
    	}
    //});
}

function junkData(length) {
    var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        result = '';
    for (var i = 0; i < length; i++) result += chars[Math.floor(Math.random() * chars.length)];
    return result;
}

async function upload(multiConnection) {
    return new Promise(resolve => {
        var startTime = 0;
        var junkLength = 5000000; //=~5 Mb
        var junk = JSON.stringify({
            data: junkData(junkLength)
        });
        var request = new XMLHttpRequest();
        request.open("POST", serverUUrl + "?_=" + Math.random(), true);
        request.setRequestHeader("Content-Type", "application/octet-stream");
        request.onreadystatechange = function() {
            if (request.readyState === 4 && request.status === 200) {
                var endTime = new Date().getTime();
                var sumTime = (endTime - startTime) / 1000;
                var speed = ((((junkLength * 8 /* byte (not bit) */ ) / sumTime) / 1024 /* Kbps */ ) / 1024 /* Mbps */ ).toFixed(2);
                resolve(speed + " Mbps (" + sumTime + " s)");
            }
        };
        request.upload.addEventListener("progress", function(event) {
            if (event.lengthComputable) {
                var endTime = new Date().getTime();
                var sumTime = (endTime - startTime) / 1000;
                var speed = ((((event.loaded * 8 /* byte (not bit) */ ) / sumTime) / 1024 /* Kbps */ ) / 1024 /* Mbps */ ).toFixed(2);
                setText("up", speed + " Mbps (" + sumTime + " s)");
            }
        }, false);
        
        try {
            startTime = new Date().getTime();
            request.send(junk);
        } catch (exception) {
            alert("Your internet connection is unavelible...");
        }
    });
}
