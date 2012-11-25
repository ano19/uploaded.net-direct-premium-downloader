<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
session_start();

if(!file_exists("./inc/config.inc.php")) {
	die("Config file missing! Aborting.");
}

$startzeit = explode(" ", microtime()); 
$startzeit = $startzeit[0] + $startzeit[1];

require_once("./inc/functions.php");

$up = new Uploaded();

$login = true;
if(!$up->login($user["user"], $user["pass"]))
{
	$login = false;
}

$info = $up->get_account_info();

$premium = true;
if($info["acc_status"] != "premium") {
	$premium = false;
}

$download = 0;
$error = null;
if(isset($_POST["submit"]) && !empty($_POST["file"])) {

	$file = $_POST["file"];

	$file = trim($file);
	$file = str_replace("http://adf.ly/1474226/", "", $file);
	$file = str_replace("http://anonym.to/?", "", $file);
	if(preg_match('#/file/(.*)/#i', $file, $tmp)) {
		$file = $tmp[0];
	}

	$file = str_replace("http://", "", $file);
	$file = str_replace("https://", "", $file);
	$file = str_replace("uploaded.to", "", $file);
	$file = str_replace("ul.to", "", $file);
	$file = str_replace("uploaded.net", "", $file);
	$file = str_replace("ul.net", "", $file);
	$file = str_replace("file/", "", $file);
	$file = str_replace("/", "", $file);
	$file = str_replace(" ", "", $file);

	$dlinfo = $up->get_download_infos($file);
	if($dlinfo === false) 
	{
		$download = 2;
		$error = $up->get_last_errno();
	}else{
		$download = 1;
		$dllink = "?dl=".$file."&key=".md5($dlinfo['filename']."|".session_id());
	}
}

if(!empty($_GET["dl"]) && !empty($_GET["key"])) {
	$file = trim($_GET["dl"]);
	$dlinfo = $up->get_download_infos($file);
	if($dlinfo !== false) 
	{
		if(trim($_GET["key"]) == md5($dlinfo['filename']."|".session_id())) {
			session_regenerate_id();
			header('Content-Type: application/force-download');
			header('Content-Disposition: attachment; filename="'.$dlinfo['filename'].'"');
			header('Content-Length: '.$dlinfo['size']);
			$opt[UP_FILE_HANDLER] = false;
			$up->download($file, null, $opt);
	 	 }else{
			die("ERROR|403|Sorry, your download link is only valid for one download. Maybe we already downloaded this file or your session is not correct anymore.");
		}
	 }else{
		die("ERROR|404|File was not found. File still available?");
	}
	die("ERROR|000|Unknown error.");
}

?>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<title>ul.net Download</title>
<link type="text/css" href="./css/style.css" rel="stylesheet" />
<script type="text/javascript">
window.onload = init;

function init()
{
	link = document.getElementById('file');
	link.focus();
}

function start() 
{
	var file = document.getElementById("file");
	var submit = document.getElementById("submit");
	file.readonly = "readonly";
	submit.readonly = "readonly";
	submit.value = "Starting download...";
}

function started()
{
	var message = document.getElementById("message");
	message.innerHTML = '<font color="green">Your download should start in a few moments.<br /><b>Thanks for using my service!</b></font><br /><br />';
}
</script>
</head>
<body>

<div class="content">

		<div id="icons" style="position: absolute;">
			<a href="./" title="Home"><img src="./images/house.png"></a><br />
		</div>

		<center>
		<br />

		<div style="margin: 0 auto; width:500px">
		  <div class="info" style="font-family: Arial; padding: 20 0 15 0px; ">
			<center>
				<span style="font-size: 30px;"><b>Uploaded.net</b></span><br />
				<br />
				<span style="font-size: 20px;">Premium Downloader</span><br />
			</center>
			</div>
		  <div class="info_a"></div>
		</div>

		<?php
		if(!$login || !$premium) {
			echo "<br />\n";

			if(!$login) {
				$alertmsg = '<strong>Login not possible!</strong><br />
							I\'m sorry, but the login into my premium account wasn\'t possible.<br />
							Please try again later, maybe I fixed the problem.';

			}else if(!$premium) {
				$alertmsg = '<strong>Premium inactive!</strong><br />
							I\'m sorry, but my premium account isn\'t active anymore.<br />
							Please try again later, maybe I have then premium again.';
			}

			echo '
			<div class="ui-widget" style="width:500px;">
				<div class="ui-state-error ui-corner-all" style="padding: 0 .7em;"> 
					<p>
						<span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
						'.$alertmsg.'
					</p>
				</div>
			</div>
			';

		 }else{

		echo 'Traffic left: '.round(($info['traffic'] / 1024 / 1024 / 1024),2).' GB &nbsp; &bull; &nbsp; Premium expires on <span title="'.time2str($info['expire'] - time()).' left">'.date("d.m.Y H:i", $info['expire']).'</span><br /><hr style="width:400px;">';
		?>

		<br />
		<span id="message">
<?php
if($download > 0) {
	if($download == 1) {
		echo '<font color="green">Great, your download for<br />
		      <b>'.$dlinfo["filename"].'</b><br />
			  ('.getSize($dlinfo["size"]).')
			  is ready.<br />
			  <a href="'.$dllink.'" onclick="started();">To start the download please click here.</a></font>'."\n";

	}elseif($download == 2) {
		echo '<font color="red">Download not possible. Is the file available and public?</font>'."\n";

	}elseif($download == 3) {
		echo '<font color="red">Sorry, an unknown error happended! (#'.$up->get_last_errno().')</font>'."\n";
	}

	echo '<br /><br />'."\n";
}
?>
		</span>

		<form action="" method="POST">
			<b>Put your uploaded.net link in here:</b><br />
			<input type="text" name="file" id="file" placeholder="http://uploaded.to/file/1234abcd" size="50"><br />
			<small>(examples: <i>http://ul.to/1234abcd</i>, <i>http://uploaded.to/file/1234abcd</i> or only the id <i>1234abcd</i>)</small><br />
			<br /><br />
			<input type="submit" name="submit" id="submit" onclick="start();" value="DOWNLOAD FILE">
		</form>

		<br />
		<br />
<?php
		}
?>
		</center>

</div>

</body>
</html>
<?php

$endzeit = explode(" ", microtime());
$endzeit = $endzeit[0] + $endzeit[1];
$sek = ($endzeit - $startzeit);

echo "<!--

	Ladezeit........: ".round($sek, 3)." Sekunden

-->";

?>