const serverEUrl = "https://localhost/speedtest/";
const serverGUrl = "https://localhost/speedtest/blank.file";
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
        } catch (exception) {
            alert("Your internet connection is unavelible...");
        }
    });
}

async function download(multiConnection) {
    return new Promise(resolve => {
        var totalStartTime = 0;
        requests = [];
        requestsHelper = [];
        function temp(i){
            setTimeout(
    			function() {
                    var startTime = 0;
                    requests[i] = new XMLHttpRequest();
                    requestsHelper[i] = [];
                    requestsHelper[i].done = false;
                    requests[i].open("GET", serverGUrl + "?_=" + Math.random(), true);
                    requests[i].onreadystatechange = function() {
                        if (requests[i].readyState === 4 && requests[i].status === 200) {
                            var endTime = new Date().getTime();
                            var reqLength = requests[i].responseText.length * 8;
                            var sumTime = (endTime - startTime) / 1000;
                            var speed = (((reqLength / sumTime) / 1024 /* Kbps */ ) / 1024 /* Mbps */ ).toFixed(2);
                            
                            requestsHelper[i].speed = speed;
                            requestsHelper[i].sumTime = sumTime;
                            requestsHelper[i].done = true;
                            alert("request " + i + " done.");
                        }
                    };
                    requests[i].onprogress = function() {
                        if (status == "stop") {
                            requests[i].abort();
                        }
            
                        var endTime = new Date().getTime();
                        var reqLength = requests[i].responseText.length * 8;
                        var sumTime = (endTime - startTime) / 1000;
                        var speed = (((reqLength / sumTime) / 1024 /* Kbps */ ) / 1024 /* Mbps */ ).toFixed(2);
                        
                        requestsHelper[i].speed = speed;
                        requestsHelper[i].sumTime = sumTime;
                    };
                    try {
                        startTime = new Date().getTime();
                        requests[i].send(null);
                    } catch (exception) {
                        alert("Your internet connection is unavelible...");
                    }
    			},
    		1);
        }
        
        totalStartTime = new Date().getTime();
        for (var i = 0; i < multiConnection; i++) {
    		temp(i);
    	}
    	
    	while(true){
    	    done = true;
        	var tmpTotalSpeed = 0;
    	    requestsHelper.forEach(function (request){
    	        if(done && !request.done) done = false;
    	        tmpTotalSpeed += request.speed
    	    });
    	    
	        var totalEndTime = new Date().getTime();
    	    var totalSpeed = tmpTotalSpeed / multiConnection;
    	    var totalSumTime = (totalEndTime - totalStartTime) / 1000;
    	    
    	    if(done){
    	        resolve(totalSpeed + " Mbps (" + totalSumTime + " s)");
    	        return;
    	    }
    	    else{
    	        setText("down", totalSpeed + " Mbps (" + totalSumTime + " s)");
    	    }
    	}
    });
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
        request.open("POST", serverEUrl + "?_=" + Math.random(), true);
        request.setRequestHeader("Content-Type", "application/octet-stream");
        request.onreadystatechange = function() {
            if (request.readyState === 4 && request.status === 200) {
                var endTime = new Date().getTime();
                var sumTime = (endTime - startTime) / 1000;
                var speed = ((((junkLength * 8 /* byte (not bit) */ ) / sumTime) / 1024 /* Kbps */ ) / 1024 /* Mbps */ ).toFixed(2);
                resolve(speed + " Mbps (" + sumTime + " s)");
            }
        };
        try {
            startTime = new Date().getTime();
            request.send(junk);
        } catch (exception) {
            alert("Your internet connection is unavelible...");
        }
    });
}
