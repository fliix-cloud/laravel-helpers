<?php

use Fliix\Api\Core\Database;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PHPMailer\PHPMailer\PHPMailer;
#use Random\RandomException;

function sendMail($configName, string $subject, string $body, array $toAddress, array $ccAddress = array(), array $bccAddress = array(), array $attachments = array()): bool
{
	global $mailConfig;

	$config = $mailConfig[$configName];

	$mail = new PHPMailer(true);

	try {
		//Server settings
		$mail->CharSet   = 'UTF-8';
		$mail->Encoding  = 'base64';
		$mail->isSMTP();                                            //Send using SMTP
		$mail->Host       = $config["host"];                     //Set the SMTP server to send through
		$mail->SMTPAuth   = true;                                   //Enable SMTP authentication
		$mail->Username   = $config["user"];                     //SMTP username
		$mail->Password   = $config["password"];                               //SMTP password
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;            //Enable implicit TLS encryption
		$mail->Port       = $config["port"];                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

		//Recipients
		$mail->setFrom($config["from"], $config["sender"]);
		$mail->addReplyTo($config["reply"], $config["sender"]);

		foreach ($toAddress as $address)
		{
			$mail->addAddress($address);
		}

		foreach ($ccAddress as $address)
		{
			$mail->addCC($address);
		}

		foreach ($bccAddress as $address)
		{
			$mail->addBCC($address);
		}

		foreach ($attachments as $attachment)
		{
			$mail->addStringAttachment(base64_decode($attachment["data"]), $attachment["title"]);
		}

		//Content
		$mail->isHTML(true);                                  //Set email format to HTML
		$mail->Subject = $subject;
		$mail->Body    = $body;

		if($mail->send())
		{
			return true;
		}

	} catch (Exception $ex) {
		sendPush('fliix', "Message could not be sent. Mailer Error: {$ex->getMessage()} - ".print_r($toAddress,true), 'Mail Error: '.$subject);
	}

	return false;
}

/**
 * @param string $configName
 * @param string $message
 * @param string $title
 * @param string $url
 * @param string $url_title
 * @param string $sound
 *
 * @return bool
 */
function sendPush(string $configName, string $message,string $title = "",string $url = "",string $url_title = "",string $sound = ""): bool
{
	global $pushConfig, $log;

	if(!empty($pushConfig[$configName]))
	{
		$array = array();

		$array["token"] = $pushConfig[$configName]["token"];
		$array["user"] = $pushConfig[$configName]["user"];
		$array["message"] = $message;

		$array["title"] = $title ?? "";
		$array["url"] = $url ?? "";
		$array["url_title"] = $url_title ?? "";
		$array["sound"] = $sound ?? "";
		$array["html"] = "1";

		try {
			$results = apiRequest("POST",'https://api.pushover.net/1/messages.json',$array);

			if($results)
			{
				return true;
			}
			else
			{
				return false;
			}
		} catch (Exception $ex) {
			error_log($ex->getMessage());
			$log->warning($ex->getMessage());
		}
	}

	return false;
}

/**
 * @param string      $method
 * @param string      $url
 * @param array|null  $data
 * @param string|null $permission
 * @param bool        $asArray
 *
 * @return string|array|object|false
 */
function apiRequest(string $method,string $url,array $data=null,string $permission=null,bool $asArray=false): string|array|object|false
{
	if(isset($data) && count($data) > 0)
	{
		//Encode the array into JSON.
		$jsonDataEncoded = json_encode($data);
	}
	else
	{
		$jsonDataEncoded = null;
	}

	//Initiate cURL.
	$ch = curl_init($url);
	//Set the return as string option
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	// START TEMPORARY BASIC AUTH
	$username = "api";
	$password = "qpTUs2t#N7GJRKh7tdtCD3qcHn2hB6mZ";

	curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
	// END TEMPORARY BASIC AUTH

	switch($method)
	{
		case "PUT":
			//Set the content type to application/json
			curl_setopt($ch, CURLOPT_HTTPHEADER, array($permission, 'Content-Type: application/json','Content-Length: ' . strlen($jsonDataEncoded)));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
			//Tell cURL that we want to send a PUT request.
			curl_setopt($ch, CURLOPT_PUT, 1);
			//Attach our encoded JSON string to the PUT fields.
			curl_setopt($ch, CURLOPT_POSTFIELDS,$jsonDataEncoded);
			break;
		case "POST":
			//Set the content type to application/json
			curl_setopt($ch, CURLOPT_HTTPHEADER, array($permission, 'Content-Type: application/json'));
			//Tell cURL that we want to send a POST request.
			curl_setopt($ch, CURLOPT_POST, 1);
			//Attach our encoded JSON string to the POST fields.
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
			break;
		default:
			//Set the content type to application/json
			curl_setopt($ch, CURLOPT_HTTPHEADER, array($permission, 'Content-Type: application/json'));
			// GET is Default
			break;
	}

	//Execute the request
	$result = curl_exec($ch);

	// Close cURL Session
	curl_close($ch);

	if(!empty($result) && isJSON($result)) {
		if ($asArray) {
			return json_decode($result, true);
		} else {
			// Return data
			return json_decode($result);
		}
	}

	return false;
}


/**
 * Generate 16 bytes (128 bits) of random data or use the data passed into the function.
 * MySQL Data Type: char(36)
 *
 * @param $data
 *
 * @return string
 */
function getGuidV4($data = null): string
{
	global $log;

	try {
		$data = $data ?? random_bytes(16);
		assert(strlen($data) == 16);

		// Set version to 0100
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		// Set bits 6-7 to 10
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		// Output the 36 character UUID.
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	} catch (Exception $ex) {
		error_log($ex->getMessage());
		$log->error('random_bytes failed: '. $ex->getMessage());
		return '';
	}
}

/**
 * Determine if a given string is a valid JSON
 *
 * @param string $string
 * @param bool $return_data
 *
 * @return bool
 */
function isJSON(string $string, bool $return_data = false): bool
{
	$data = json_decode($string);
	return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : true) : false;
}

function getFileType(string $type): string
{
	$db = new Database();

	$data = $db->query('SELECT name FROM auth_file_types WHERE NOT JSON_SEARCH(mime_types, "one", "'.$type.'") IS NULL')->fetchSingle();

	return $data["name"];
}

function createPdf(array $pdfData): string
{
	$defaultConfig = (new ConfigVariables())->getDefaults();
	$fontDirs = $defaultConfig['fontDir'];

	$defaultFontConfig = (new FontVariables())->getDefaults();
	$fontData = $defaultFontConfig['fontdata'];

	// Start mPDF Generator
	$mpdf = new Mpdf([
			'mode' => 'utf-8',
			'format' => 'A4',
			'fontDir' => array_merge($fontDirs, [
				APP_DIR . '/fonts',
			]),
			'fontdata' => $fontData + [ // lowercase letters only in font key
					'arial' => [
						'R' => 'Arial.ttf',
						'B' => 'Arial_Bold.ttf',
					]
				],
			'default_font' => 'arial',
			'setAutoTopMargin' => 'pad',
			'margin_header' => 20]
	);

	if(isset($pdfData["config"]["output"]) && $pdfData["config"]["output"] != "")
	{
		switch($pdfData["config"]["output"])
		{
			case "download":
				$output = Destination::DOWNLOAD;
				$filename = $pdfData["config"]["filename"];
				break;
			case "string":
				$output = Destination::STRING_RETURN;
				$filename = "";
				break;
			case "inline":
			default:
				$output = Destination::INLINE;
				$filename = $pdfData["config"]["filename"];
				break;
		}
	}
	else
	{
		$output = Destination::STRING_RETURN;
	}

	if(isset($pdfData["config"]["background"]) && $pdfData["config"]["background"])
	{
		$mpdf->SetDefaultBodyCSS( 'background', "url('" . APP_DIR . "/includes/pdf/background.svg')" );
		$mpdf->SetDefaultBodyCSS( 'background-image-resize', 6 );
	}

	if(isset($pdfData["stylesheet"]) && $pdfData["stylesheet"] != "")
	{
		$mpdf->WriteHTML($pdfData["stylesheet"], HTMLParserMode::HEADER_CSS);
	}

	if(isset($pdfData["header"]))
	{
		$mpdf->SetHTMLHeader($pdfData["header"]);
	}

	if(isset($pdfData["footer"]))
	{
		$mpdf->SetHTMLFooter($pdfData["footer"]);
	}

	for($i=0;$i<count($pdfData["pages"]);$i++)
	{
		if($i>0)
		{
			if(isset($pdfData["config"]["header_first_page_only"]) && $pdfData["config"]["header_first_page_only"])
			{
				$mpdf->SetHTMLHeader('');
			}

			$mpdf->AddPage();
		}

		$mpdf->WriteHTML($pdfData["pages"][$i]["html"]);
	}

	return base64_encode($mpdf->Output('', Destination::STRING_RETURN));
}

function secondsToHumanTime(int $seconds, array $filter = []): string
{
	$intervalDefinitions = [
		'year'   => ['interval' => 31536000, 'labels' => ['Jahr', 'Jahre']],
		'month'  => ['interval' => 2592000, 'labels' => ['Monat', 'Monate']],
		'week'   => ['interval' => 604800, 'labels' => ['Woche', 'Wochen']],
		'day'    => ['interval' => 86400, 'labels' => ['Tag', 'Tage']],
		'hour'   => ['interval' => 3600, 'labels' => ['h', 'h']],
		'minute' => ['interval' => 60, 'labels' => ['min','min']],
		'second' => ['interval' => 1, 'labels' => ['s','s']],
	];

	$filteredIntervalDefinitions = array_column(
		$filter ?
			array_intersect_key($intervalDefinitions, array_flip($filter)) :
			$intervalDefinitions,
		'labels',
		'interval'
	);

	$intervals = [];
	foreach ($filteredIntervalDefinitions as $numerator => $labels) {
		if($counter = intdiv($seconds, $numerator)) {
			$intervals[] = $counter . ' ' . ($labels[(int)((bool)($counter - 1))] ?? '');
			$seconds -= ($counter * $numerator);
		}
	}

	return implode(' ', $intervals);
}

function formatUptime(int $uptime): string
{
	$secondsPerMinute = 60;
	$secondsPerHour = $secondsPerMinute * 60;
	$secondsPerDay = $secondsPerHour * 24;

	$days = floor($uptime / $secondsPerDay);
	$hours = floor(($uptime % $secondsPerDay) / $secondsPerHour);
	$minutes = floor(($uptime % $secondsPerHour) / $secondsPerMinute);
	$seconds = $uptime % $secondsPerMinute;

	$formattedUptime = "";

	if ($days > 0) {
		$formattedUptime .= "$days Tag" . ($days != 1 ? "e " : " ");
	}
	$formattedUptime .= sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

	return $formattedUptime;
}

/**
 * @param string $timestamp
 *
 * @return string
 */
function formatLastCommunication(string $timestamp): string
{
	$currentTime = time();
	$timeDiff = $currentTime - strtotime($timestamp);

	if ($timeDiff < 60) {
		if($timeDiff < 1)
		{
			return "jetzt";
		}
		else
		{
			return "vor {$timeDiff} Sekunden";
		}
	} elseif ($timeDiff < 3600) {
		$minutes = floor($timeDiff / 60);
		$seconds = $timeDiff % 60;
		return "vor {$minutes} Minute" . ($minutes != 1 ? "n" : "") . ($seconds > 0 ? " {$seconds} Sekunden" : "");
	} elseif ($timeDiff < 86400) {
		$hours = floor($timeDiff / 3600);
		$minutes = floor(($timeDiff % 3600) / 60);
		return "vor {$hours} Stunde" . ($hours != 1 ? "n" : "") . ($minutes > 0 ? " {$minutes} Minuten" : "");
	} elseif ($timeDiff < 2592000) {
		$days = floor($timeDiff / 86400);
		$hours = floor(($timeDiff % 86400) / 3600);
		return "vor {$days} Tag" . ($days != 1 ? "en" : "") . ($hours > 0 ? " {$hours} Stunden" : "");
	} else {
		$months = floor($timeDiff / 2592000);
		$days = floor(($timeDiff % 2592000) / 86400);
		return "vor {$months} Monat" . ($months != 1 ? "en" : "") . ($days > 0 ? " {$days} Tagen" : "");
	}
}

function add_config(string $name, string $content): void
{
	$db = new Database();

	$db->query('INSERT INTO config (name, content) VALUE (?,?)', $name, $content);
}

function update_config(string $name, string $content): void
{
	$db = new Database();

	$db->query('UPDATE config SET name = ? WHERE content = ?', $name, $content);
}

function get_config(string $name): string|false
{
	$db = new Database();

	$data = $db->query('SELECT content FROM config WHERE name = ?', $name)->fetchSingle();

	if(!empty($data["content"]))
	{
		return $data["content"];
	}

	return false;
}

function convertMultiLineJsonArray(array $array): array
{
	$arrayKeys = array_keys($array);
	$arrayTotal = count($array[$arrayKeys[0]]);

	$newArray = array();

	for($i=0;$i<$arrayTotal;$i++)
	{
		$row = array();

		foreach ($arrayKeys as $key)
		{
			$row[$key] = $array[$key][$i];
		}

		$newArray[] = $row;
	}

	return $newArray;
}

/**
 * Distance between two Lat/Lng Points
 *
 * Returns distance between two Lat/Lng Points in KM.
 *
 * @param float $lat1 Lat-1 Value.
 * @param float $lng1 Lng-1 Value.
 * @param float $lat2 Lat-2 Value.
 * @param float $lng2 Lng-2 Value.
 * @return float Distance in KM.
 * @since 1.0.0
 */
function getDistanceBetweenGPSPoints(float $lat1, float $lng1, float $lat2, float $lng2): float
{
	if (($lat1 == $lat2) && ($lng1 == $lng2))
	{
		return 0;
	}
	else
	{
		$theta = $lng1 - $lng2;
		$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
		$dist = acos($dist);
		$dist = rad2deg($dist);
		return $dist * 60 * 1.1515 * 1.609344;
	}
}

/**
 * @param string $address
 * @return array
 */
function getGPSFromAddress(string $address=""): array
{
	$gpsObject = array();

	if ($address != "")
	{
		str_replace("&amp;","+",$address);
		str_replace("&+","+",$address);
		str_replace(" ","+",$address);

		// Get Google GPS Data
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.GOOGLE_MAPS_API_KEY;
		//Initiate API Request.
		$addressObject = apiRequest("GET",$url,null,null);

		if(isset($addressObject->results)) {
			$gpsObject["lat"] = $addressObject->results[0]->geometry->location->lat;
			$gpsObject["long"] = $addressObject->results[0]->geometry->location->lng;
		}
	}

	return $gpsObject;
}

function validateOrEmptyEmail(string $email = null): bool
{
	// Check if the email is empty or matches the regex
	if (empty($email) || validateEmail($email)) {
		return true; // Valid email or empty field
	} else {
		return false; // Invalid email
	}
}

function validateEmail(string $email): bool
{
	// Regular expression for validating email addresses
	$emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';

	// Check if the email matches the regex
	if (preg_match($emailRegex, $email)) {
		return true; // Valid email or empty field
	} else {
		return false; // Invalid email
	}
}

function validateSVNumber(string $svNumber = null, string $birthday = null): bool
{
	// Regular expression for SV-Number: 2 digits, 6 digits (birth date), 1 uppercase letter, 3 digits
	$svRegex = '/^[0-9]{2}[0-9]{6}[A-Z][0-9]{3}$/';

	// Remove spaces and convert to uppercase
	$svNumber = strtoupper(str_replace(' ', '', $svNumber));

	// Check if the SV-Number matches the pattern
	if (!preg_match($svRegex, $svNumber)) {
		return false; // Invalid SV-Number format
	}

	// Extract birth date from SV-Number (characters 3-8)
	$svBirthDate = substr($svNumber, 2, 6);

	// Convert birthday to the format DDMMYY
	$birthdayDate = DateTime::createFromFormat('Y-m-d', $birthday);
	if (!$birthdayDate) {
		return false; // Invalid birthday format
	}
	$formattedBirthday = $birthdayDate->format('dmy');

	// Compare the extracted birth date with the provided birthday
	return $svBirthDate === $formattedBirthday;
}

function validateTaxID(string $taxID = null): bool
{
	// Regular expression for Tax-ID: 11 digits
	$taxIDRegex = '/^\d{11}$/';

	// Remove spaces
	$taxID = str_replace(' ', '', $taxID);

	// Check if the Tax-ID matches the pattern
	return preg_match($taxIDRegex, $taxID);
}
