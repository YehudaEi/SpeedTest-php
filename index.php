<html>
	<?php require_once ('inc/head.php')?>
<body>
	<?php require_once ('inc/menu.php')?>
		<div class="container">
			<div id="test">
				<div class="testGroup" id="nomob">
					<div class="testArea">
						<div class="icontest"><i class="fas fa-unlink"></i></div>
						<div class="testName">Jitter | דילאי</div>
						<canvas id="jitMeter" class="meter"></canvas>
						<div id="jitText" class="meterText"></div>
						<div class="unit">ms</div>
					</div>
					<div class="testArea">
						<div class="icontest"><i class="fas fa-link"></i></div>
						<div class="testName">Ping | פינג</div>
						<canvas id="pingMeter" class="meter"></canvas>
						<div id="pingText" class="meterText"></div>
						<div class="unit">ms</div>
					</div>
				</div>
				<div class="testGroup">
					<div class="testArea">
					<div class="testAreastyle">
						<div class="icontest"><i class="fas fa-download"></i></div>
						<div class="testName">Download | הורדה</div>
					</div>	
						<canvas id="dlMeter" class="meter"></canvas>
						<div id="dlText" class="meterText"></div>
						<div class="unit">Mbps</div>
					</div>
					<div class="testArea">
						<div class="icontest"><i class="fas fa-upload"></i></div>
						<div class="testName">Upload | העלאה</div>
						<canvas id="ulMeter" class="meter"></canvas>
						<div id="ulText" class="meterText"></div>
						<div class="unit">Mbps</div>
					</div>
				</div>
				<div style="display: none;" id="ipArea">
					IP Address: <span id="ip"></span> :כתובת אייפי
				</div>
				<a style="display: none; font-size: 35px;" id="shareButton" href="">
					<i class="fa fa-share-alt"></i>
				</a>
			</div>
			<center>
				<div id="startStopBtn" onclick="startStop()"></div>
			</center>
			<script type="text/javascript">setTimeout(initUI,100);</script>
			
			<!--<center>
			    </br>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
				<div id="counter-area-winkey">Real time visitors: <span id="counter-winkey"></span> :משתמשים בזמן אמת</div>
				<script>
					async function a(b=-1){
						var r=b;
						await fetch('v.php?v='+b).then((rr)=>{r=rr.json();});
						return r;
					}
					var i=2e3,b=a();$("#counter-winkey").text(b);/*setInterval(function(){b=a(b),$("#counter-winkey").text(b)},i);*/
				</script>
			</center>
			-->
		</div>	
	<?php require_once ('inc/footer.php')?>			
</body>
</html>