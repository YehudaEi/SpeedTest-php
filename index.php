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
			</div>
			<center>
				<div id="startStopBtn" onclick="startStop()"></div>
			</center>
			<script type="text/javascript">setTimeout(initUI,100);</script>
		</div>	
	<?php require_once ('inc/footer.php')?>			
</body>
</html>