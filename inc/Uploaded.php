<?php
/**
 * Uploaded.net API Class
 * 
 *  Unterstützung von:
 *  - hochladen, herunterladen, Backup, löschen und umbenennen von Dateien 
 *  - erstellen, löschen und umbenennen von Ordnern 
 *  - Zuordnung von Dateien in Ordnern. 
 *  - Remote Uploads
 *  - Free und Premium User Support
 *   
 * @author Julius Fischer
 * @copyright 2012 Julius Fischer
 * 
 * @license GNU LGPL <br>
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 * 
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * 
 * @link http:\\www.it-gecko.de
 * @version 0.16
 *
 */
define("UP_ERR_NONE", 0);
define("UP_ERR_404", 1);
define("UP_ERR_PATH", 2);
define("UP_ERR_DOWNLOAD_SERVER", 3);
define("UP_ERR_FILE_EXISTS", 4);
define("UP_ERR_FILENAME", 5);
define("UP_ERR_LOGIN", 6);
define("UP_ERR_INTERNAL", 7);
define("UP_ERR_PRIVACY", 8);
define("UP_ERR_RESUME", 9);

/**
 * Keine Einschänkung
 */
define("UP_PRIVACY_NONE", 1);

/**
 * Passwort setzten
 */
define("UP_PRIVACY_PW", 2);

/**
 * Download nur über eigenen Account möglich
 */
define("UP_PRIVACY_PRIVATE", 3);

define("UP_PROGRESS_HANDLER", 1);
define("UP_FILE_HANDLER", 2);
define('UP_RESUME_DOWNLOAD', 3);
define('UP_RESUME_START_BYTE', 4);

class Uploaded
{
	protected $user_id = '';
	protected $user_pw = '';
	protected $auth_str = '';
	protected $cookie_str = '';
	protected $login_status = false;
	protected $acc_status = false; //false = free | true = premium
	
	protected $last_error;
	
	public function __construct($user_id = '', $user_pw = '')
	{
		if($user_id && $user_pw)
			$this->login($user_id, $user_pw);
		
		$this->set_last_error(UP_ERR_NONE, "");
	}
	
	protected function init_login()
	{
		$res = $this->get_curl('http://uploaded.net/io/login', 'id='.$this->user_id.'&pw='.$this->user_pw, false, true);

		if(preg_match('#.'.preg_quote('{"err":').'#si', $res))
		{
			$this->login_status = false;
			$this->set_last_error(UP_ERR_LOGIN, "User-ID oder Passwort falsch");
			
			return false;
		}
		
		if(preg_match('#PHPSESSID\=[a-zA-Z0-9]+;#si', $res, $matches))
			$this->cookie_str = $matches[0];
		
		if(preg_match('#login\=.*?;#si', $res, $matches))
			$this->cookie_str .= $matches[0];
		
		if(preg_match('#auth\=[a-zA-Z0-9];#si', $res, $matches))
			$this->cookie_str .= $matches[0];
		
		$this->login_status = true;
		
		return true;
	}
	
	/**
	 * Prüft auf erfolgreichen Login
	 * 
	 * @return bool true = erfolgreich eingeloggt | false = Login fehlgeschlagen
	 */
	public function login_check()
	{
		//return preg_match('#'.preg_quote('<a href="logout">Logout</a>').'#si', $this->get_curl('http://uploaded.net'));
		
		return $this->login_status;
	}
	
	/**
	 * Login
	 * 
	 * @param int $user_id	Benutzer-ID
	 * @param string $user_pw Benutzerpasswort
	 * 
	 * @return bool true = erfolgreich eingeloggt | false = login fehlgeschlagen
	 */
	public function login($user_id, $user_pw)
	{
		$this->user_id 	= $user_id;
		$this->user_pw 	= $user_pw;
		//$this->auth_str	= '&id='.$this->user_id.'&pw='.sha1($this->user_pw);
		
		if($this->init_login())
		{
			//User ID statt Alias oder E-Mail adresse
			if(preg_match('#id%3D([0-9]+)%26#', $this->cookie_str, $matches))
				$this->user_id = $matches[1]; 
			
			if(preg_match('#pw%3D([0-9a-z]+)%26#', $this->cookie_str, $matches))
				$pw = $matches[1];
			
			$this->auth_str = '&id='.$this->user_id.'&pw='.$pw;
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Setzt die Logindaten für Uploaded.net Premium Account
	 * 
	 * @param int $user_id	Benutzer-ID
	 * @param string $user_pw Benutzerpasswort
	 * 
	 * @return bool true = erfolgreich eingeloggt | false = login fehlgeschlagen
	 * 
	 * @deprecated Benutze login()
	 */
	public function set_login($user_id, $user_pw)
	{
		/*
		$this->user_id 	= (int)$user_id;
		$this->user_pw 	= $user_pw;		
		$this->auth_str	= '&id='.$this->user_id.'&pw='.sha1($this->user_pw);
		
		$this->init_login();
		
		return $this->login_check();
		*/
		
		return $this->login($user_id, $user_pw);
	}
	
	/**
	 * Gibt Account-Informationen zurück<br>
	 * <br>
	 * mehr Infos im Beispiel /demo/get_account_info.php
	 * @return array Keys: id, created, alias, email, acc_status, traffic, expire, credit, points
	 */
	public function get_account_info()
	{
		$acc_info = $this->get_curl("http://uploaded.net/api/user");
		
		if($acc_info === false || empty($acc_info)) 
		{
			return array('err' => 'Accountinfos konnten nicht gelesen werden');
		}
		
		$j = json_decode($acc_info);
		
		if($j === false || isset($j->err) || !isset($j->account))
		{
			return array('err' => 'Fehler bei der Verarbeitung');
		}
	
		$i = $j->account;
		
		$a = array();
		
		$a['id'] = $i->id;
		$a['created'] = strtotime($i->created);
		$a['alias'] = $i->alias->name->alias;
		$a['email'] = $i->alias->name->email;
		$a['acc_status'] = $i->alias->name->status;
		$a['traffic'] = $i->traffic->available;
		$a['expire'] = strtotime($i->pro->expire);
		$a['credit'] = (float)str_replace(",", ".", $i->affiliate->balance);
		$a['points'] = $i->affiliate->points;
		
		return $a;
		
		/*
		$postdata 	= array('uid' => $this->user_id,
							'upw' => $this->user_pw);
		$res		= $this->get_curl('http://uploaded.net/status', $postdata);
		$a 			= array();
		
		if(preg_match('#blocked#i', $res))
		{
			return array('err' => 'Gesperrt! 15min Warten');	
		}
		
		if(preg_match('#wrong user#i', $res))
		{
			return array('err' => 'falsche UserID');
		}
		
		if(preg_match('#wrong password#i', $res))
		{
			return array('err' => 'falsches Passwort');
		}
		
		if(!preg_match('#status: premium#i', $res))
		{
			return array('acc_status' => 'Free Account');
		}
		
		preg_match('#traffic: (\d+)#i', $res, $matches);
		$a['traffic'] = $matches[1];
		
		preg_match('#expire: (\d+)#i', $res, $matches);
		$a['expire'] = $matches[1];
		
		$a['acc_status'] = 'Premium Account';
		
		
		$res = $this->get_curl('http://uploaded.net/affiliate');
	
		preg_match('#<th class="aR cB" id="userbalance">([\d\.]+)#i', $res, $matches);
		$a['credit'] = (float)$matches[1];
		
		if(preg_match('#<td>Punkte:</td>.*<em class="cB">([0-9\.]+)</em>#i', $res, $matches))
			$a['points'] = str_replace(".", "", $matches[1]);
		
		return $a;
		*/
	}

	/**
	 * Läd eine Datei auf Uploaded.net hoch
	 * 
	 * @param string $file Pfad zur Datei z.B. C:\text.txt oder /home/text.txt
	 * 
	 * @return bool|array false = Upload fehlgeschlagen | array = ['id', 'editKey']
	 */
	public function upload($file, $opt = null)
	{		
		if(false === $rf = realpath($file))
		{
			$this->set_last_error(UP_ERR_PATH, "ungültiger Pfad");
		
			return false;
		}
		
		$options = null;
		
		if($opt != null && is_array($opt))
		{
			if(isset($opt[UP_PROGRESS_HANDLER]))
			{
				$options = array();
				$options[CURLOPT_NOPROGRESS] = false;
				$options[CURLOPT_PROGRESSFUNCTION] = $opt[UP_PROGRESS_HANDLER];
			}
		}

		$key 		= Uploaded::generate();
		$postdata 	= array('Filename' => basename($rf),
							'Upload' => 'Submit Query',
							'Filedata' => '@'.$rf);		
		$res		= $this->get_curl($this->get_upload_server().'upload?admincode='.$key.$this->auth_str, 
											$postdata, true, false, $options);

		if($res === false)
		{
			$this->set_last_error(UP_ERR_INTERNAL, "interner Fehler");
			
			return false;
		}
		
		if(strpos($res, ',') === false)
		{
			$this->set_last_error(UP_ERR_LOGIN, 'Logindaten sind falsch: "anonymous fileuploads are forbidden."');
			
			return false;
		}
		
		return array('id' => (strpos($res, ',') ? substr($res, 0, strpos($res, ',')) : $res), 'editKey' => $key);
	}
	
	/**
	 * Gibt Dateiname und Dateigröße eines Download zurück<br>
	 * Dies Funktion kann vor einem Download aufgerufen werden, um den Dateiname und Dateigröße zu ermitteln.
	 * <br>
	 * mehr Infos im Beispiel /demo/get_download_info.php
	 * 
	 * @param String File-ID
	 * 
	 * @return bool|array False = ein Fehler ist aufgetretten, Array = filename -> Dateiname, size -> Dateigröße
	 */
	public function get_download_infos($file_id) 
	{		
		$page =  $this->get_curl('http://uploaded.net/file/'.$file_id.'/ddl', null, true, true);
	
		if(preg_match('#Location: http://uploaded\.net/404#', $page))
		{
			$this->set_last_error(UP_ERR_404, "Datei nicht vorhanden");
			return false;
		}
		
		if(!preg_match("#http://[0-9a-z\-]*stor\d+.uploaded.net/dl/([0-9a-z\-]+)#mi", $page, $matches))
		{
			$this->set_last_error(UP_ERR_DOWNLOAD_SERVER, "Downloadserver nicht gefunden: ");
				
			return false;
		}
		
		$url = $matches[0];
		
		$file_header = $this->get_curl($url, null, true, true, array(CURLOPT_NOBODY => true), true);
		$size = $file_header['info']['download_content_length'];
		
		
		if(!preg_match('#filename=\"(.*?)\"#mi', $file_header['res'], $matches))
		{
			$this->set_last_error(UP_ERR_FILENAME, "Dateiname konnte nicht ermittelt werden");
				
			return false;
		}
		
		return array(	'size' => $size,
				'filename' => $matches[1],
				'url' => $url);

	}
	
	/**
	 * Ermöglicht das herunterladen einer Datei.
	 * Nur mit Premium Account 
	 * 
	 * <b>Optionen:</b><br>
	 * <b>* UP_PROGRESS_HANDLER: (PHP >= 5.3) </b>
	 * Setzten einen Download-Progress-Handler, 4 Parameter erwartet:
	 * (int) Downloadsize, (int) Downloadprogress, (int) Uploadsize und (int) Uplodadprogress 
	 * <b>* UP_RESUME_DOWNLOAD:</b>
	 * Setzt einen Download fort, ausgehend von der Dateigröße
	 * <b>* UP_FILE_HANDLER:</b>
	 * Setzt einen eigenen File-Handler. Erwartet wird eine Resource, z.B. die Rückgabe von fopen() oder auch STDOUT.
	 * Der Parameter $path wird dabei ignoriert
	 * In Verbindung mit UP_RESUME_DOWNLOAD muss UP_RESUME_START_BYTE gesetzt werden.
	 * <b>* UP_RESUME_START_BYTE:</b>
	 * Option nur nötig in Verbindung mit UP_FILE_HANDLER und UP_RESUME_DOWNLOAD
	 * Setzt den Startpunkt für das fortsetzten eines Downloads
	 * 
	 * @example
	 * <b>UP_PROGRESS_HANDLER:</b><br>
	 * $opt = array(UP_PROGRESS_HANDLER => 'myProgressHandler');<br>
	 * $up->download('id', 'path' $opt);<br>
	 * function myProgressHandler($downloadsize, $downloadprogress, $uploadsize, $uploadprogress) {<br>
	 * 		echo $downloadprogress;<br>
	 * }<br><br>
	 * <b>UP_FILE_HANDLER:</b><br>
	 *  $opt = array(UP_FILE_HANDLER => STDOUT');<br>
	 *  $up->download('id', null $opt);<br>
	 * @param string $file_id File-ID
	 * @param string $path Download-Verzeichnis
	 * @param array $opt Setzt Optionen für den Download
	 */
	public function download($file_id, $path, $opt = null)
	{
		$info = $this->get_download_infos($file_id);
		
		if($info === false)
			return false;
		
		$options = array();
		$resume = false;
		
		if($opt != null && is_array($opt))
		{
			if(isset($opt[UP_PROGRESS_HANDLER]))
			{
				$options[CURLOPT_PROGRESSFUNCTION] = $opt[UP_PROGRESS_HANDLER];
				$options[CURLOPT_NOPROGRESS] = false;
			}
			
			if(isset($opt[UP_RESUME_DOWNLOAD])) 
			{
				$resume = true;
			}
			
			if(isset($opt[UP_FILE_HANDLER]))
			{
				if($resume && isset($opt[UP_RESUME_START_BYTE]))
				{
					$options[CURLOPT_RANGE] = $opt[UP_RESUME_START_BYTE].'-';
				}
				
				if(is_resource($opt[UP_FILE_HANDLER])) {
					$options[CURLOPT_FILE] = $opt[UP_FILE_HANDLER];
				} else if($opt[UP_FILE_HANDLER] === false) {
					$options[CURLOPT_RETURNTRANSFER] = false;
				}
				
				return $this->get_curl($info['url'], null, true, false, $options);
			}
			
			
		}
		
		if(false === $rf = realpath($path))
		{
			$this->set_last_error(UP_ERR_PATH, "ungültiger Pfad");
		
			return false;
		}
					
		$file = $rf."/".$info['filename'];
		
		if($resume)
		{
			$size = @filesize($file);
			
			if($size === false)
				$size = 0;
			
			if($size > $info['size'])
			{
				$this->set_last_error(UP_ERR_RESUME, "Ziel Datei ist größer als Quelldatei");
				return false;
			}
			else if($size == $info['size'])
			{
				return true;
			}
			
			$fp = fopen($file, 'a+');
						
			$options[CURLOPT_RANGE] = $size.'-';	
		}
		else
		{
			if(file_exists($file))
			{
				$this->set_last_error(UP_ERR_FILE_EXISTS, "Datei existiert bereits");
			
				return false;
			}
			
			$fp		= fopen($file, 'w+');
		}
		
		$options[CURLOPT_FILE] = $fp;
		
		$res	= $this->get_curl($info['url'], null, true, false, $options);	
		
		fclose($fp);	
		
		return $res;
	}
	
	/**
	 * Verschiebt eine Datei in einen Ordner
	 * 
	 * @param string $file_id		File-ID
	 * @param string $folder_id		Ordner-ID
	 */
	public function move_to_folder($file_id, $folder_id)
	{
		return $this->get_curl('http://uploaded.net/io/me/folder/'.$folder_id.'/add/'.$file_id) === '';
	}
	
	/**
	 * Benennt einen Ordner um
	 * 
	 * @param string $folder_id
	 * @param string $folder_name
	 * 
	 * @return bool
	 */
	public function set_folder_name($folder_id, $folder_name)
	{
		return $this->set_name('folder', $folder_id, $folder_name);
	}
	
	/**
	 * Erstellt einen neuen Ordner
	 * 
	 * @param string $folder_name
	 * 
	 * @return string Ordner-ID
	 */
	public function create_folder($folder_name)
	{
		$this->get_curl('http://uploaded.net/io/me/folder/create');
		$id = $this->get_folders('title', 'Neuer Ordner', 'id');
		$this->set_folder_name($id, $folder_name);
		
		return $id;
	}
	
	/**
	 * Umbennen einer Datei
	 * 
	 * @param string $file_id
	 * @param string $file_name
	 * 
	 * @return bool
	 */
	public function set_file_name($file_id, $file_name)
	{
		return $this->set_name('files', $file_id, $file_name);
	}
	
	/**
	 * Löscht einen Ordner
	 * 
	 * @param string $folder_id
	 * 
	 * @return bool
	 */
	public function delete_folder($folder_id)
	{
		return $this->delete_obj('folder', $folder_id);
	}
	
	/**
	 * Löscht eine Datei
	 * 
	 * @param string $file_id
	 * 
	 * @return bool
	 */
	public function delete_file($file_id)
	{
		return $this->delete_obj('files', $file_id);
	}
	
	/**
	 * Gibt ein oder mehrere Folder-Objekte zurück, einzeln oder in einem Array.
	 * 
	 * @param string $filer_type [optional]  Auf was soll gefiltert werden [files, id, ispublic, title]
	 * @param string $filter [optional] Wert des Filters
	 * @param int|string $limit_opt [optional] 0 = Gibt Folder-Objekt zurück | >0 = Gibt limitierte Anzahl von Folder-Objekt in Array zurück | String = gibt Folder-Objekt Eigenschaft zurück
	 * 
	 * @return mixed false = Fehler | array = Enthält ein oder mehrere Folder-Objekte | Objekt = Folder-Objekt | String = Wert einer Folder-Objekt Eigenschaft
	 */
	public function get_folders($filer_type = '', $filter = '', $limit_opt = false)
	{
		return $this->get_list_obj('folders', $filer_type, $filter, $limit_opt);
	}
	
	/**
	 * Gibt ein oder mehrere File-Objekte zurück, einzeln oder in einem Array.
	 * 
	 * @param string $filer_type [optional]  Auf was soll gefiltert werden [admin, available, date, ddl, desc, dls, file_extension, filename, id, lastdownload, privacy, size]
	 * @param string $filter [optional] Wert des Filters
	 * @param int|string $limit_opt [optional] 0 = Gibt File-Objekt zurück | >0 = Gibt limitierte Anzahl von File-Objekt in Array zurück | String = gibt File-Objekt Eigenschaft zurück
	 * 
	 * @return mixed false = Fehler | array = Enthält ein oder mehre File-Objkete | Objekt = File-Objekt | String = Wert einer File-Objekt Eigenschaft
	 */
	public function get_files($filer_type = '', $filter = '', $limit_opt = false)
	{
		return $this->get_list_obj('files', $filer_type, $filter, $limit_opt);
	}
	
	/**
	 * Gibt alle File-Objekte eines Ordner zurück
	 * @param string $foldername Ordnername
	 * 
	 * @return Array mit File-Objekte | null = Keine Files gefunden
	 */
	public function get_folder_files($foldername)
	{
		$files = $this->get_files();
		$f = array();

		for($i = 0, $c = count($files); $i < $c; $i++)
		{
			if(isset($files[$i]->foldername) && $files[$i]->foldername == $foldername)
				$f[] = $files[$i];
		}
		
		return empty($f) ? null : $f;
	}
	
	/**
	 * Sicherheitskopie einer Datei anlegen
	 * 
	 * @param String $file_id
	 * @return boolean|mixed
	 */
	public function backup_file($file_id)
	{
		
		$json = $this->get_curl('http://uploaded.net/io/file/backup/'.$file_id);
		$obj = json_decode(utf8_encode(str_replace(array('auth', 'filename', 'size'), array('"auth"', '"filename"', '"size"'), $json)));
		
		// UTF8 in ISO-8859-1
		foreach($obj as $j => &$v)
			$v = utf8_decode($v);
			
		if($obj === null || isset($obj->err))
			return false;
		
		return $obj;
	}
	
	/**
	 * Startet ein Remote Upload
	 * 
	 * @param string $urls Remote URL (1 URL)
	 * 
	 * @return boolean
	 */
	public function add_remote_upload($url)
	{
		return $this->get_curl('http://uploaded.net/io/remote/add', 'values='.urlencode($url)) === '<em class=\'cG\'>Added</em>: '.$url."\n";	
	}
	
	/**
	 * Importiert eine oder mehrere URLs
	 * 
	 * @param string|array $url
	 * 
	 * @return array
	 */
	public function add_import($url)
	{
		$url  = urlencode(implode("\n", (array)$url));
		$return = array();
		
		$json = $this->get_curl('http://uploaded.net/io/import', "urls=".$url);		
		$json = preg_split('#}([,])#', $json, 0, PREG_SPLIT_NO_EMPTY);
		
		foreach($json as $j)
		{
			$return[] = json_decode(preg_replace('/([{,])(\s*)([^"]+?)\s*:/','$1"$3":',$j.'}'));
		}

		return $return;
	}
	
	
	/**
	 * Überprüft eine Datei auf Verfügbarkeit
	 *
	 * @param string $file_id File-ID
	 *
	 * @return boolean
	 *
	 * @author Lukas
	 */
	public function is_file_available($file_id) 
	{
		$file_header = $this->get_curl('http://uploaded.net/file/'.$file_id.'/ddl', null, true, true,
				array(	CURLOPT_NOBODY => true,
						CURLOPT_FOLLOWLOCATION => true));
	
		if(preg_match("#404 Not Found#", $file_header))
		{
			$this->set_last_error(UP_ERR_404, "Datei nicht vorhanden");
			
			return false;
		}
	
		return true;
	}
	
	/**
	 * Privatsphäre-Einstellungen:<br><br>
	 * 
	 * Dateien können gewöhnlich von allen Benutzern heruntergeladen werden, sofern diese Kenntnis vom Downloadlink haben.
	 * Sie können Ihre Dateien jedoch auch vor unautorisierten Downloads schützen indem Sie<br>
	 * • den Download erst nach der Eingabe eines von Ihnen festgelegten Passwortes freigeben<br>
	 * • ausschließlich Downloads von Ihrem Account aus zulassen<br><br>
	 * 
	 * Privacy - Konstanten<br>
	 * UP_PRIVACY_NONE = Keine Einschänkung<br>
	 * UP_PRIVACY_PW = Passwort setzten <br>
	 * UP_PRIVACY_PRIVATE = Download nur über eigenen Account möglich<br>
	 *	
	 * @param string $file_id	File-ID
	 * @param int $type			Privacy - Konstanten (UP_PRIVACY_NONE, UP_PRIVACY_PW, UP_PRIVACY_PRIVATE)
	 * @param string $pw=""		PW bei UP_PRIVACY_PW
	 * 
	 * @return boolean true = OK | false = fehler
	 */
	public function set_privacy($file_id, $type, $pw = "")
	{
		$param = null;
		
		switch($type)
		{
			case UP_PRIVACY_NONE:
				$param = 'privacy=&pw=';
				break;
			case UP_PRIVACY_PW:
				$param = 'privacy=pw&pw='.$pw;
				break;
			case UP_PRIVACY_PRIVATE:
				$param = 'privacy=NULL&pw=';
				break;
		}
		
		$page = $this->get_curl('http://uploaded.net/io/me/files/'.$file_id.'/set/privacy', $param);
		
		if(strlen($page) != 2)
		{
			$j = json_decode($page);
			
			if($j === NULL || !isset($j->err))
			{
				$this->set_last_error(UP_ERR_PRIVACY, "unbekannter Fehler");
			}
			else
			{
				$this->set_last_error(UP_ERR_PRIVACY, "Fehler: ".$j->err);
			}
			
			return false;
		}
		
		return true;
	}
	
	/**
	 * Gibt letzte Fehlernummer zurück
	 * 
	 * @return int Fehlernummer
	 */
	public function get_last_errno()
	{
		return $this->last_error['code'];
	}
	
	/**
	 * Gibt letzte Fehlernachricht zurück
	 * 
	 * @return string Fehlernachricht
	 */
	public function get_last_error()
	{
		return $this->last_error['msg'];
	}
	
	protected function set_name($type, $obj_id, $obj_name)
	{	
		return $this->get_curl('http://uploaded.net/io/me/'.$type.'/'.$obj_id.'/set/title', 'value='.urlencode($obj_name).'&editorId=') == $obj_name;
	}
	
	protected function delete_obj($type, $obj_id)
	{
		return  $this->get_curl('http://uploaded.net/io/me/'.$type.'/'.$obj_id.'/delete') == '';
	}
	
	protected function get_json_list($url, $post)
	{
		
		$page = 0;
		$json = array();
		
		do
		{
			$obj = json_decode(utf8_encode($this->get_curl($url, $post.'&page='.$page++)));
			
			if($obj === null || isset($obj->err))
				return null;
			
			$json = array_merge($json, $obj->list);
			
		}while($obj->listopts->hasNext);

		// UTF8 in ISO-8859-1
		for($i = 0, $c = count($json); $i < $c; $i++)
			foreach($json[$i] as $j => &$v)
				$v = utf8_decode($v);
			
		return $json;
	}
	
	protected function get_list_obj($list_type, $filer_type = '', $filter = '', $limit_opt = false)
	{
		switch($list_type)
		{
			case 'folders':
				$obj = $this->get_json_list('http://uploaded.net/io/me/list/folders', '');	
				
				break;
				
			default:
				$obj = $this->get_json_list('http://uploaded.net/io/me/list/files', 
													'limit=100&order=date&dir=desc&search=');	
		}
						
		if($obj === null || isset($obj->err))
			return false;
		
		if($filter && $filer_type)
		{
			$files	= array();
			$i		= 0;
			$limit	= $limit_opt;
			$opt	= false;
			
			if($limit_opt !== false && !is_numeric($limit_opt))
			{
				$limit = 0;
				$opt = $limit_opt;
			}
									
			foreach($obj as $list)
			{
				if(!isset($list->$filer_type) || $list->$filer_type != $filter)
					continue;
				
				if($limit === $i++)
					return ($limit === 0) ? ($opt !== false && isset($list->$opt)) 
											? $list->$opt
											: $list
										: $files;
				
				$files[] = $list;
			}
			
			return $i ? $files : false;
		}
				
		return $obj;
	}
	
	/**
	 * 
	 * 
	 * @param string $url
	 * @param string|array $post (optional)
	 * @param bool $cookie (optional)
	 * @param bool $header (optional)
	 * @param array $opt (optional)
	 * @param bool $info (optional)
	 * @return mixed
	 */
	protected function get_curl($url, $post = null, $cookie = true, $header = false, $opt = null, $info = false)
	{
		$ch = curl_init();
						
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Referer: http://uploaded.net/upload'));
		
		if($post !== null)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);	
		}
		
		if($cookie)
			curl_setopt($ch, CURLOPT_COOKIE, $this->cookie_str);
		
		if($header)
			curl_setopt($ch, CURLOPT_HEADER, true);
		
		if($opt !== null)
		{
			curl_setopt_array($ch, $opt);
		}
				
		$res = curl_exec($ch);
		
		if($info === true) 
		{
			$r['res'] = $res;
			$r['info'] = curl_getinfo($ch);
			$res = $r;
		}
		
		curl_close($ch);
		
		return $res;
	}
	
	protected function get_upload_server()
	{
		return  $this->get_curl('http://uploaded.net/api/uploadserver');
	}

	protected function set_last_error($code, $msg)
	{
		$this->last_error['code'] = $code;
		$this->last_error['msg'] = $msg;
	}
		
	protected static function generate($len = 6)
	{
		$pwd = '';
		$con = array('b','c','d','f','g','h','j','k','l','m','n','p','r','s','t','v','w','x','y','z');
		$voc = array('a','e','i','o','u');
		$len /= 2;
		
		for($i = 0; $i < $len; $i++)
		{
			$c = mt_rand(0, 1000) % 20;
			$v = mt_rand(0, 1000) % 5;
			$pwd .= $con[$c].$voc[$v];
		}
		
		return $pwd;
	}
	
	protected static function get_mime_type($file)
	{
		if(function_exists('finfo_file'))
		{
			$finfo 		= finfo_open(FILEINFO_MIME_TYPE);
			$mime_type 	= finfo_file($finfo, $file);
			
			finfo_close($finfo);
		}
		elseif(function_exists('mime_content_type'))
		{
			$mime_type =  mime_content_type($file);
		}
		
		return (empty($mime_type)) ? 'text/plain' : $mime_type;
	}
	
	public function __sleep()
	{
		return array('user_id', 'user_pw', 'auth_str', 'cookie_str');	
	}
	
	public function __wakeup()
	{
			$this->init_login();
	}
}
?>