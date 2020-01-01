<script type="text/javascript">
	function I(id){return document.getElementById(id);}
	var meterBk="#E0E0E0";
	var dlColor="#0085c9",
		ulColor="#0085c9",
		pingColor="#0085c9",
		jitColor="#0085c9";
	var progColor="#55be00";
	function drawMeter(c,amount,bk,fg,progress,prog){
		var ctx=c.getContext("2d");
		var dp=window.devicePixelRatio||1;
		var cw=c.clientWidth*dp, ch=c.clientHeight*dp;
		var sizScale=ch*0.0055;
		if(c.width==cw&&c.height==ch){
			ctx.clearRect(0,0,cw,ch);
		}else{
			c.width=cw;
			c.height=ch;
		}
		ctx.beginPath();
		ctx.strokeStyle=bk;
		ctx.lineWidth=16*sizScale;
		ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,Math.PI*0.1);
		ctx.stroke();
		ctx.beginPath();
		ctx.strokeStyle=fg;
		ctx.lineWidth=16*sizScale;
		ctx.arc(c.width/2,c.height-58*sizScale,c.height/1.8-ctx.lineWidth,-Math.PI*1.1,amount*Math.PI*1.2-Math.PI*1.1);
		ctx.stroke();
		if(typeof progress !== "undefined"){
			ctx.fillStyle=prog;
			ctx.fillRect(c.width*0.3,c.height-16*sizScale,c.width*0.4*progress,4*sizScale);
		}
	}
	function mbpsToAmount(s){
		return 1-(1/(Math.pow(1.3,Math.sqrt(s))));
	}
	function msToAmount(s){
		return 1-(1/(Math.pow(1.08,Math.sqrt(s))));
	}
	var w=null; 
	var data=null; 
	function startStop(){
		if(w!=null){
			w.postMessage('abort');
			w=null;
			data=null;
			I("startStopBtn").className="";
			initUI();
		}else{
			w=new Worker('netspeed/speedtest_worker.min.js');
			w.postMessage('start'); 
			I("startStopBtn").className="running";
			w.onmessage=function(e){
				data=JSON.parse(e.data);
				var status=data.testState;
				if(status>=4){
					I("startStopBtn").className="";
					w=null;
					updateUI(true);
				}
			};
		}
	}
	function updateUI(forced){
		if(!forced&&(!data||!w)) return;
		var status=data.testState;
		I("ip").textContent=data.clientIp;
		I("ipArea").style.display="block";
		I("dlText").textContent=(status==1&&data.dlStatus==0)?"...":data.dlStatus;
		drawMeter(I("dlMeter"),mbpsToAmount(Number(data.dlStatus*(status==1?oscillate():1))),meterBk,dlColor,Number(data.dlProgress),progColor);
		I("ulText").textContent=(status==3&&data.ulStatus==0)?"...":data.ulStatus;
		drawMeter(I("ulMeter"),mbpsToAmount(Number(data.ulStatus*(status==3?oscillate():1))),meterBk,ulColor,Number(data.ulProgress),progColor);
		I("pingText").textContent=data.pingStatus;
		drawMeter(I("pingMeter"),msToAmount(Number(data.pingStatus*(status==2?oscillate():1))),meterBk,pingColor,Number(data.pingProgress),progColor);
		I("jitText").textContent=data.jitterStatus;
		drawMeter(I("jitMeter"),msToAmount(Number(data.jitterStatus*(status==2?oscillate():1))),meterBk,jitColor,Number(data.pingProgress),progColor);
	}
	function oscillate(){
		return 1+0.02*Math.sin(Date.now()/100);
	}
	setInterval(function(){
		if(w) w.postMessage('status');
	},200);
	window.requestAnimationFrame=window.requestAnimationFrame||window.webkitRequestAnimationFrame||window.mozRequestAnimationFrame||window.msRequestAnimationFrame||(function(callback,element){setTimeout(callback,1000/60);});
	function frame(){
		requestAnimationFrame(frame);
		updateUI();
	}
	frame(); 
	function initUI(){
		drawMeter(I("dlMeter"),0,meterBk,dlColor,0);
		drawMeter(I("ulMeter"),0,meterBk,ulColor,0);
		drawMeter(I("pingMeter"),0,meterBk,pingColor,0);
		drawMeter(I("jitMeter"),0,meterBk,jitColor,0);
		I("dlText").textContent="";
		I("ulText").textContent="";
		I("pingText").textContent="";
		I("jitText").textContent="";
		I("ip").textContent="";
	}
</script>	
<div class="topnav" id="myTopnav">
	<a href="./" class="home"><i class="fas fa-house-damage"></i></a>
	<a href="contact.php" class="<?php if (basename($_SERVER['PHP_SELF']) == "contact.php") { ?> selected <?php } ?>">Contact | צור קשר</a>
</div>