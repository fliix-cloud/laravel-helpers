<?php

use Illuminate\Support\Facades\Http;

/**
 * Sends a push notification using the specified configuration.
 *
 * @param string $configName The name of the configuration to use.
 * @param string $message The message to send.
 * @param string $title The title of the notification.
 * @param string $url The URL to include in the notification.
 * @param string $url_title The title of the URL.
 * @param string $sound The sound to play with the notification.
 * @return bool True if the notification was sent successfully, false otherwise.
 */
if (!function_exists('sendPush')) {
	function sendPush(string $configName, string $message, string $title = "", string $url = "", string $url_title = "", string $sound = ""): bool
	{
		global $pushConfig;

		if (!empty($pushConfig[$configName])) {
			$array = [
				"token" => $pushConfig[$configName]["token"],
				"user" => $pushConfig[$configName]["user"],
				"message" => $message,
				"title" => $title,
				"url" => $url,
				"url_title" => $url_title,
				"sound" => $sound,
				"html" => "1"
			];

			try {
				$response = Http::post('https://api.pushover.net/1/messages.json', $array);

				return $response->successful();
			} catch (Exception $ex) {
				error_log($ex->getMessage());
			}
		}

		return false;
	}
}

/**
 * Generates a version 4 UUID.
 *
 * @param string|null $data Optional data to use for generating the UUID.
 * @return string The generated UUID.
 */
if (!function_exists('getGuidV4')) {
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
			$log->error('random_bytes failed: ' . $ex->getMessage());
			return '';
		}
	}
}

/**
 * Determines if a given string is valid JSON.
 *
 * @param string $string The string to check.
 * @param bool $return_data Whether to return the decoded data if valid JSON.
 * @return bool|mixed True if the string is valid JSON, otherwise false or the decoded data if $return_data is true.
 */
if (!function_exists('is_json')) {
	function is_json(string $string, bool $return_data = false)
	{
		$data = json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : true) : false;
	}
}

/**
 * Converts seconds to a human-readable time format.
 *
 * @param int $seconds The number of seconds.
 * @param array $filter Optional filter to limit the time units included in the result.
 * @return string The human-readable time string.
 */
if (!function_exists('secondsToHumanTime')) {
	function secondsToHumanTime(int $seconds, array $filter = []): string
	{
		$intervalDefinitions = [
			'year' => ['interval' => 31536000, 'labels' => ['Jahr', 'Jahre']],
			'month' => ['interval' => 2592000, 'labels' => ['Monat', 'Monate']],
			'week' => ['interval' => 604800, 'labels' => ['Woche', 'Wochen']],
			'day' => ['interval' => 86400, 'labels' => ['Tag', 'Tage']],
			'hour' => ['interval' => 3600, 'labels' => ['h', 'h']],
			'minute' => ['interval' => 60, 'labels' => ['min', 'min']],
			'second' => ['interval' => 1, 'labels' => ['s', 's']],
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
			if ($counter = intdiv($seconds, $numerator)) {
				$intervals[] = $counter . ' ' . ($labels[(int)((bool)($counter - 1))] ?? '');
				$seconds -= ($counter * $numerator);
			}
		}

		return implode(' ', $intervals);
	}
}

/**
 * Converts a multi-line JSON array to a single-line JSON array.
 *
 * @param array $array The multi-line JSON array.
 * @return array The converted single-line JSON array.
 */
if (!function_exists('convertMultiLineJsonArray')) {
	function convertMultiLineJsonArray(array $array): array
	{
		$arrayKeys = array_keys($array);
		$arrayTotal = count($array[$arrayKeys[0]]);

		$newArray = [];

		for ($i = 0; $i < $arrayTotal; $i++) {
			$row = [];

			foreach ($arrayKeys as $key) {
				$row[$key] = $array[$key][$i];
			}

			$newArray[] = $row;
		}

		return $newArray;
	}
}

/**
 * Calculates the distance between two GPS coordinates.
 *
 * @param float $lat1 Latitude of the first point.
 * @param float $lng1 Longitude of the first point.
 * @param float $lat2 Latitude of the second point.
 * @param float $lng2 Longitude of the second point.
 * @return float The distance in kilometers.
 */
if (!function_exists('getDistanceBetweenGPSPoints')) {
	function getDistanceBetweenGPSPoints(float $lat1, float $lng1, float $lat2, float $lng2): float
	{
		if (($lat1 == $lat2) && ($lng1 == $lng2)) {
			return 0.0;
		} else {
			$theta = $lng1 - $lng2;
			$dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
			$dist = acos($dist);
			$dist = rad2deg($dist);
			return $dist * 60 * 1.1515 * 1.609344;
		}
	}
}

/**
 * Validates an email address or checks if it's empty.
 *
 * @param string|null $email The email address to validate.
 * @return bool True if the email is valid or empty, false otherwise.
 */
if (!function_exists('validateOrEmptyEmail')) {
	function validateOrEmptyEmail(string $email = null): bool
	{
		return empty($email) || validateEmail($email);
	}
}

/**
 * Validates an email address.
 *
 * @param string $email The email address to validate.
 * @return bool True if the email address is valid, false otherwise.
 */
if (!function_exists('validateEmail')) {
	function validateEmail(string $email): bool
	{
		$emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
		return (bool)preg_match($emailRegex, $email);
	}
}

/**
 * Validates a social security number (SV-Number) against a birth date.
 *
 * @param string|null $svNumber The SV-Number to validate.
 * @param string|null $birthday The birth date to validate against (format: Y-m-d).
 * @return bool True if the SV-Number is valid, false otherwise.
 */
if (!function_exists('validateSVNumber')) {
	function validateSVNumber(string $svNumber = null, string $birthday = null): bool
	{
		$svRegex = '/^[0-9]{2}[0-9]{6}[A-Z][0-9]{3}$/';
		$svNumber = strtoupper(str_replace(' ', '', $svNumber));

		if (!preg_match($svRegex, $svNumber)) {
			return false;
		}

		$svBirthDate = substr($svNumber, 2, 6);
		$birthdayDate = DateTime::createFromFormat('Y-m-d', $birthday);
		if (!$birthdayDate) {
			return false;
		}
		$formattedBirthday = $birthdayDate->format('dmy');

		return $svBirthDate === $formattedBirthday;
	}
}

/**
 * Validates a tax ID.
 *
 * @param string|null $taxID The tax ID to validate.
 * @return bool True if the tax ID is valid, false otherwise.
 */
if (!function_exists('validateTaxID')) {
	function validateTaxID(string $taxID = null): bool
	{
		$taxIDRegex = '/^\d{11}$/';
		$taxID = str_replace(' ', '', $taxID);
		return (bool)preg_match($taxIDRegex, $taxID);
	}
}
