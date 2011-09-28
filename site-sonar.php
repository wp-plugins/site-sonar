<?

if(!file_exists(dirname(__FILE__).'/site-sonar-config.php'))
{
	$SS_PASSWORD = 'site-sonar.com';
	$SS_DATABASE_FILENAME = dirname(__FILE__).'/logs.sqlite';
	$SS_PAGEVIEWS_LIMIT = -1;
	$SS_SESSION_FIELDS = '[contractorname] ([contractorid])';
	$SS_SCRIPT_PATH = '';
	$SS_SERVER_DATA = $_SESSION;
}
else
{
	include(dirname(__FILE__).'/site-sonar-config.php');
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$pswd = isset($_GET['pswd']) ? $_GET['pswd'] : '';
$fromid = isset($_GET['fromid']) ? $_GET['fromid'] : '';
$fromactivity = isset($_GET['fromactivity']) ? $_GET['fromactivity'] : '';


// ==================WRAP PDO SQLITE ===============
if(!function_exists('sqlite_open'))
{
	define('SQLITE_ASSOC', 1);
	define('SQLITE_NUM', 2);
	define('SQLITE_BOTH', 3);
	

	function sqlite_open($filename, $filemode, &$error)
	{
		try
		{
			$pdo = new PDO("sqlite:$filename");
			$pdo->setAttribute(PDO::ATTR_TIMEOUT, 10);
			$pdo->exec("SELECT 0");
			return $pdo;
		}
		catch(Exception $exception)
		{
			try
			{
				$error = $exception;
				
				$pdo = new PDO("sqlite:$filename");
				$pdo->exec("SELECT 0");
				return $pdo;
			}
			catch(Exception $exception2)
			{
				$error = $exception2;
			}
		}
		
		return $pdo;
	}

	function sqlite_escape_string($sql)
	{
		$sql = str_replace("'", "''", $sql);
		return $sql;
	}
	
	function sqlite_exec($pdo, $sql, &$error)
	{
		$rowsaffected = $pdo->exec($sql);
		
		if($rowsaffected === FALSE)
		{
			$errorinfo = $pdo->errorInfo();
			$error = $errorinfo[2];
		}
	}
	
	function sqlite_query($pdo, $sql, $fetchtype = SQLITE_ASSOC, &$error = '')
	{
		$statement = $pdo->query($sql);
		
		if($statement === FALSE)
		{
			$errorinfo = $pdo->errorInfo();
			$error = $errorinfo[2];
		}
		
		if($fetchtype == SQLITE_NUM)
			$statement->setFetchMode(PDO::FETCH_NUM);
		else if($fetchtype == SQLITE_ASSOC)
			$statement->setFetchMode(PDO::FETCH_ASSOC);
		else if($fetchtype == SQLITE_BOTH)
			$statement->setFetchMode(PDO::FETCH_BOTH);
		
		return $statement;
	}
	
	function sqlite_fetch_array(&$statement)
	{
		$array = $statement->fetch();
		return $array;
	}
	
	function sqlite_fetch_single($statement)
	{
		$res = $statement->fetchColumn();
		
		return $res;
	}
	
	function sqlite_close($pdo)
	{
		unset($pdo);
	}
}

//======================== DETECT ===============================
if($action == 'detect')
{
	echo 'OK';
	exit();
}

//======================== CREATE DB ============================
if(!file_exists($SS_DATABASE_FILENAME))
{
	$error = '';
	$dbpointer = sqlite_open($SS_DATABASE_FILENAME, 0666, $error);
	if($error != '')
	{
		error_log("ERR 124: $error");
		return;
	}
	
	$error = '';
	sqlite_exec($dbpointer, 
			'CREATE TABLE pageview (
				id INTEGER PRIMARY KEY ASC,
				fkbefore INTEGER,
				fkuser INTEGER,
				created DATETIME,
				url VARCHAR(400)
			);
			
			CREATE TABLE user (
				id INTEGER PRIMARY KEY ASC,
				uid VARCHAR(200),
				activity DATETIME,
				created DATETIME,
				ip VARCHAR(15),
				sourceurl VARCHAR(400),
				user_agent VARCHAR(500),
				http_accept VARCHAR(500),
				language VARCHAR(5),
				resolution VARCHAR(15),
				colors VARCHAR(20),
				plugins TEXT,
				sessiondata TEXT
			);
			
			CREATE INDEX user_uid ON user(uid);
			CREATE INDEX user_user_agent ON user(user_agent);
			CREATE INDEX pageview_fkuser ON pageview(fkuser);
			CREATE INDEX pageview_created ON pageview(created);', 
			$error);
			
	if($error != '')
	{
		error_log("ERR 162: $error");
		return;
	}
}
else
{
	$error = '';
	$dbpointer = sqlite_open($SS_DATABASE_FILENAME, 0666, $error);
	
	if($error != '')
	{
		error_log("ERR 173: $error");
		return;
	}
}


//=============================== LOG =========================================
if($action == '' || $action == 'update')
{
	//retrieve values from _SERVER and _COOKIE
	$uid = isset($_COOKIE['SS_uid']) ? $_COOKIE['SS_uid'] : '';
	$url = sqlite_escape_string($_SERVER['REQUEST_URI']);
	$ip = $_SERVER['REMOTE_ADDR'];
	$user_agent = sqlite_escape_string($_SERVER['HTTP_USER_AGENT']);
	$http_accept = sqlite_escape_string($_SERVER['HTTP_ACCEPT']);
	$selfhost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
	$sourceurl = isset($_SERVER['HTTP_REFERER']) ? sqlite_escape_string($_SERVER['HTTP_REFERER']) : '';
	
	$matches = array();
	if(preg_match_all("/\\[[A-Za-z0-9_]*\\]/i", $SS_SESSION_FIELDS, $matches) > 0)
	{
		$sessiondata = $SS_SESSION_FIELDS;
		$hasdata = FALSE;
		foreach($matches[0] as $match)
		{
			$matchkey = trim(trim($match, '['), ']');
			if(isset($SS_SERVER_DATA[$matchkey]) && ''.$SS_SERVER_DATA[$matchkey] != '')
			{
				$sessiondata = str_replace($match, $SS_SERVER_DATA[$matchkey], $sessiondata);
				$hasdata = TRUE;
			}
			else
				$sessiondata = str_replace($match, '', $sessiondata);
		}
		
		if(!$hasdata)
			$sessiondata = '';
	}
	
	$language = isset($_COOKIE['SS_language']) ? sqlite_escape_string($_COOKIE['SS_language']) : '';
	$resolution = isset($_COOKIE['SS_resolution']) ? sqlite_escape_string($_COOKIE['SS_resolution']) : '';
	$colors = isset($_COOKIE['SS_colors']) ? sqlite_escape_string($_COOKIE['SS_colors']) : '';
	$plugins = isset($_COOKIE['SS_plugins']) ? sqlite_escape_string($_COOKIE['SS_plugins']) : '';
	
	if($url != '/favicon.ico' && $url != '/robots.txt')
	{
		if(stripos($sourceurl, $selfhost) >= 5 && stripos($sourceurl, $selfhost) <= 11)
			$sourceurl = '';
		
		//merge bots
		if(''.$uid == '')
		{
			$botdefs = array('googlebot', 'yandexbot', 'bingbot', 'baiduspider', 'yahoo', 'mj12bot');
			foreach($botdefs as $botdef)
			{
				if(stripos('__'.$user_agent, $botdef) > 1)
				{
					$res = sqlite_query($dbpointer, "SELECT uid FROM user WHERE user_agent = '$user_agent'", SQLITE_NUM, $error);
					$uid = ''.sqlite_fetch_single($res);
					unset($res);
					break;
				}
			}
		}
		
		//search for user
		if(''.$uid == '')
		{
			$res = sqlite_query($dbpointer, "SELECT uid FROM user 
																WHERE 
																	user_agent = '$user_agent' AND
																	ip = '$ip' AND 
																	http_accept = '$http_accept' AND 
																	activity > date('now', 'localtime', '-10 hour')", 
															SQLITE_NUM, $error);
			if($error != '')
			{	
				error_log("ERR 249: $error");
				return;
			}
			
			$uid = ''.sqlite_fetch_single($res);
			unset($res);
		}
		
		//skip doubled pageview
		if(strlen($user_agent) > 2 && $url == '/' && ''.$sourceurl == '' && $http_accept == '*/*')
		{
			return;
		}
		
		//generate new one
		if(''.$uid == '')
		{
			$uid = sha1($ip.'|'.$user_agent.'|'.mt_rand(0x19A100, 0x39AA3FF));
		}
		
		$sessiondata = iconv("UTF-8", "Windows-1252", $sessiondata);
		$sourceurl = iconv("UTF-8", "Windows-1252", $sourceurl);
		$url = iconv("UTF-8", "Windows-1252", $url);
				
		if($action == 'update')
		{
			$sql = "UPDATE user SET
							ip = '$ip',
							language = CASE WHEN '$language' = '' THEN language ELSE '$language' END,
							resolution = CASE WHEN '$resolution' = '' THEN resolution ELSE '$resolution' END,
							colors = CASE WHEN '$colors' = '' THEN colors ELSE '$colors' END,
							plugins = CASE WHEN '$plugins' = '' THEN plugins ELSE '$plugins' END
						WHERE uid = '$uid'";
				
			$error = '';
			sqlite_exec($dbpointer, $sql, $error);
			if($error != '')
			{	
				error_log("ERR 286: $error");
				return;
			}
		}
		else
		{
			//check if user already exist
			$error = '';
			$res = sqlite_query($dbpointer, "SELECT id FROM user WHERE uid = '$uid'", SQLITE_NUM, $error);
			$fkuser = ''.sqlite_fetch_single($res);
			unset($res);
			
			if($error != '')
			{
				error_log("ERR 299: $error");
				return;
			}
			
			if($fkuser == '')
			{
				//insert new user row.
				$sql = "INSERT INTO user (
							uid, 
							created,
							activity,
							ip,
							sourceurl,
							user_agent,
							http_accept,
							sessiondata,
							language,
							resolution,
							colors,
							plugins
						)
						VALUES (
							'$uid', 
							datetime('now', 'localtime'),
							datetime('now', 'localtime'),
							'$ip',
							'$sourceurl',
							'$user_agent',
							'$http_accept',
							'$sessiondata',
							'$language',
							'$resolution',
							'$colors',
							'$plugins'
						);";
						
				$error = '';
				sqlite_exec($dbpointer, $sql, $error);
				if($error != '')
				{
					error_log("ERR 339: $error");
					return;
				}
				
				$res = sqlite_query($dbpointer, "SELECT last_insert_rowid()");
				$fkuser = ''.sqlite_fetch_single($res);
				unset($res);
			}
			else
			{
				$sql = "UPDATE user SET
							ip = '$ip',
							sessiondata = CASE 
															WHEN '$sessiondata' = '' THEN sessiondata 
															ELSE '$sessiondata' 
														END,
							http_accept = '$http_accept',
							activity = datetime('now', 'localtime'),
							sourceurl = CASE 
														WHEN sourceurl <> '' AND sourceurl IS NOT NULL THEN sourceurl
														WHEN ('$sourceurl' = '') THEN sourceurl 
														ELSE '$sourceurl' 
													END
							WHERE id = $fkuser";
				
				$error = '';
				sqlite_exec($dbpointer, $sql, $error);
				if($error != '')
				{
					error_log("ERR 367: $error");
					return;
				}
			}
			
			$res = sqlite_query($dbpointer, "SELECT MAX(id) FROM pageview WHERE fkuser = $fkuser");
			$fkbefore = ''.sqlite_fetch_single($res);
			unset($res);
					
			if($fkbefore == '') $fkbefore = 'NULL';
				
			//insert pageview log
			$sql = "INSERT INTO pageview (
						fkuser, 
						fkbefore,
						created,
						url
					)
					VALUES (
						$fkuser, 
						$fkbefore,
						datetime('now', 'localtime'),
						'$url'
					);";
			
			$error = '';
			sqlite_exec($dbpointer, $sql, $error);
			if($error != '')
			{
				error_log("ERR 394: $error");
				return;
			}
		}
		
		//delete pageviews of bots older than 10 minutes
		$botnames = array('googlebot', 'bingbot', 'mj12bot', 'netsprint', 'baiduspider', 'yandexbot', 'ahrefsbot');
		foreach($botnames as $botname)
		{
			if(stripos('_'.$user_agent, $botname) > 1)
			{
				$sql = "DELETE FROM pageview WHERE uid = '$uid' AND created < datetime('now', 'localtime', '-10 minute')";
				sqlite_exec($dbpointer, $sql, $error);
				break;
			}
		}
		
		if($action != 'update')
		{
?><script type="text/javascript" >
    document.cookie = "SS_uid=<?= $uid ?>;expires=<?= gmdate("D, d M Y H:i:s", time() + 3600 * 24 * 1095)." GMT" ?>";<?
		//Use javascript to fill rest of values.
		if($language == '' && $resolution == '' && $colors == '' && $plugins == '' && $action != 'update')
		{
?>
function ssSetCookie(name, value) 
{
	if(navigator.cookieEnabled)
	{
		document.cookie = name + "=" + escape(value) + ";expires=<?= gmdate("D, d M Y H:i:s", time() + 3600 * 24 * 1095)." GMT" ?>";
	}
}

(function() {

	var pgs = '';
	if(navigator.plugins)
	{
		for(var i=0; i<navigator.plugins.length; i++)
		{
			var pg = navigator.plugins[i];
			
			if(pg != null)
			{
				var pname = pg.name.toString();
				var pdescription = pg.description.toString().replace(pname, "");
				
				if(pgs.indexOf(';' + pname, 0) >= 0)
					continue;
				
				pgs += ';' + pname + '|' + pdescription;
			}
		}
	}
	
	ssSetCookie('SS_language', (window.navigator.language) ? window.navigator.language : window.navigator.userLanguage);
	ssSetCookie('SS_resolution', screen.width.toString() + 'x' + screen.height.toString());
	ssSetCookie('SS_colors', screen.colorDepth.toString());
	ssSetCookie('SS_plugins', pgs);

	var sa = document.createElement('script'); 
	sa.type = 'text/javascript'; 
	sa.async = true;
	sa.src = '<?= $SS_SCRIPT_PATH ?>site-sonar.php?action=update';
	var ps = document.getElementsByTagName('script')[0]; 
	ps.parentNode.insertBefore(sa, ps);
})();
			<?
		}
		if($action == '')
		{
			?>
function ssPing() {
	var img = new Image();
	img.src = '<?= $SS_SCRIPT_PATH ?>site-sonar.php?action=ping&q=' + Math.round( Math.random() * 10000 );
}
self.setInterval("ssPing()", 60000);
			<?
		}
		?>
		</script>
		<?
		}
	}
}

//================================= PING ==========================================
if($action == 'ping')
{		
	$uid = isset($_COOKIE['SS_uid']) ? $_COOKIE['SS_uid'] : '';
	$ip = $_SERVER['REMOTE_ADDR'];
	
	$sql = "UPDATE user SET
				activity = datetime('now', 'localtime')
				WHERE uid = '$uid'";
	
	$error = '';	
	sqlite_exec($dbpointer, $sql, $error);
	if($error != '')
	{
		error_log("ERR 491: $error");
		return;
	}
	
	header("Content-Type: image/gif"); 
	$img = @imagecreate(10, 10);
	imagecolorallocate($img, 0, 0, 0);
	imagegif($img);
	imagedestroy($img);
}

//================================ DOWNLOAD ===================================

if($action == 'download')
{
	if($pswd != $SS_PASSWORD)
	{
		echo 'ERR:INCORRECT PASSWORD';
		exit();
	}

	$dxml = tempnam(":\n\\/?><", "");
	$dxmlgz = tempnam(":\n\\/?><", "");
	
	$xr = xmlwriter_open_uri($dxml);
	xmlwriter_start_document($xr, '1.0" encoding="Windows-1252');
	xmlwriter_start_element($xr, 'root');
	
	$islog = FALSE;
	

	//users
	xmlwriter_start_element($xr, 'user');
	$sql = "SELECT 
						id,
						created,
						activity,
						ip,
						sourceurl,
						user_agent,
						http_accept,
						sessiondata,
						language,
						resolution,
						colors,
						plugins
					FROM user";
					
	if($fromactivity != '')
		$sql .= " WHERE activity > datetime('$fromactivity')";
	
	$error = '';
	$res = sqlite_query($dbpointer, $sql, SQLITE_ASSOC, $error);
	
	if($error != '')
	{
		error_log("ERR 546: $error");
		return;
	}
															
	while($row = sqlite_fetch_array($res))
	{
		xmlwriter_start_element($xr, 'row');
		$colindex = 0;
		foreach($row as $key => $val)
		{
			$islog = TRUE;
			xmlwriter_write_element($xr, 'c'.$colindex, $val);
			$colindex++;
		}
		xmlwriter_end_element($xr);
	}
	xmlwriter_end_element($xr);
	unset($res);
	
	//pageviews
	xmlwriter_start_element($xr, 'pageview');
	$sql = "SELECT id, fkuser, fkbefore, created, url FROM pageview";
					
	if($fromid != '')
		$sql .= " WHERE id > $fromid ";
		
	$sql .= " ORDER BY id ASC ";
		
	$error = '';
	$res = sqlite_query($dbpointer, $sql, SQLITE_ASSOC, $error);
	if($error != '')
	{
		error_log("ERR 577: $error");
		return;
	}
									
	while($row = sqlite_fetch_array($res))
	{
		xmlwriter_start_element($xr, 'row');
		$colindex = 0;
		foreach($row as $key => $val)
		{
			$islog = TRUE;
			xmlwriter_write_element($xr, 'c'.$colindex, $val);
			$colindex++;
		}
		xmlwriter_end_element($xr);
	}
	xmlwriter_end_element($xr);
	unset($res);
	//
	
	xmlwriter_end_element($xr);
	xmlwriter_end_document($xr);
	xmlwriter_flush($xr);

	//compress GZIP
	$fs = fopen($dxml, 'rb');
	$gz = gzopen($dxmlgz, 'w5');
	
	while (!feof($fs))
	{
  	$buffer = fread($fs, 1000);
		gzwrite($gz, $buffer);
	}

	gzclose($gz);
	fclose($fs);
	//compress

	if(!$islog)
	{
		header('Content-Description: File Transfer');
    header('Content-Type: none');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
	}
	else if (file_exists($dxmlgz))
	{
    header('Content-Description: File Transfer');
    header('Content-Type: application/x-gzip');
    header('Content-Disposition: attachment; filename=download.xml.gz');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: '.filesize($dxmlgz));
		
    readfile($dxmlgz);
	}
	else if (file_exists($dxml))
	{
    header('Content-Description: File Transfer');
    header('Content-Type: text/xml');
    header('Content-Disposition: attachment; filename=download.xml');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: '.filesize($dxml));
    
		readfile($dxml);
	}

	if(file_exists($dxml))
		unlink($dxml);
	if(file_exists($dxmlgz))
		unlink($dxmlgz);
}

sqlite_close($dbpointer);
if(isset($dbpointer)) unset($dbpointer);

?>