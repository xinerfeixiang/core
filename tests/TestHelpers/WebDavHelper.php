<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace TestHelpers;

use Exception;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Stream\StreamInterface;
use InvalidArgumentException;
use Sabre\DAV\Client as SClient;

/**
 * Helper to make WebDav Requests
 *
 * @author Artur Neumann <artur@jankaritech.com>
 *
 */
class WebDavHelper {
	/**
	 * returns the id of a file
	 *
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 * @param string $path
	 *
	 * @throws Exception
	 * @return int
	 */
	public static function getFileIdForPath(
		$baseUrl,
		$user,
		$password,
		$path
	) {
		$body = Stream::factory(
			'<?xml version="1.0"?>
<d:propfind  xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">
  <d:prop>
    <oc:fileid />
  </d:prop>
</d:propfind>'
		);
		$response = self::makeDavRequest(
			$baseUrl, $user, $password, "PROPFIND", $path, null, $body
		);
		\preg_match('/\<oc:fileid\>(\d+)\<\/oc:fileid\>/', $response, $matches);
		if (!isset($matches[1])) {
			throw new Exception("could not find fileId of $path");
		}
		return $matches[1];
	}

	/**
	 *
	 * @param string $baseUrl
	 * URL of owncloud e.g. http://localhost:8080
	 * should include the subfolder if owncloud runs in a subfolder
	 * e.g. http://localhost:8080/owncloud-core
	 * @param string $user
	 * @param string $password or token when bearer auth is used
	 * @param string $method PUT, GET, DELETE, etc.
	 * @param string $path
	 * @param array $headers
	 * @param StreamInterface $body
	 * @param string $requestBody
	 * @param int $davPathVersionToUse (1|2)
	 * @param string $type of request
	 * @param string $sourceIpAddress to initiate the request from
	 * @param string $authType basic|bearer
	 * @param bool $stream Set to true to stream a response rather
	 *                     than download it all up-front.
	 * @param int $timeout
	 *
	 * @return ResponseInterface
	 */
	public static function makeDavRequest(
		$baseUrl,
		$user,
		$password,
		$method,
		$path,
		$headers,
		$body = null,
		$requestBody = null,
		$davPathVersionToUse = 1,
		$type = "files",
		$sourceIpAddress = null,
		$authType = "basic",
		$stream = false,
		$timeout = 0
	) {
		$baseUrl = self::sanitizeUrl($baseUrl, true);
		$davPath = self::getDavPath($user, $davPathVersionToUse, $type);
		//replace # and ? in the path, Guzzle will not encode them
		$urlSpecialChar = [['#', '?'],['%23', '%3F']];
		$path = \str_replace($urlSpecialChar[0], $urlSpecialChar[1], $path);
		$fullUrl = self::sanitizeUrl($baseUrl . $davPath . $path);

		if ($requestBody !== null) {
			$body = $requestBody;
		}
		if ($authType === 'bearer') {
			$headers['Authorization'] = 'Bearer ' . $password;
			$user = null;
			$password = null;
		}
		$config = null;
		if ($sourceIpAddress !== null) {
			$config = [ 'curl' => [ CURLOPT_INTERFACE => $sourceIpAddress ]];
		}

		if ($headers !== null) {
			foreach ($headers as $key => $value) {
				//? and # need to be encoded in the Destination URL
				if ($key === "Destination") {
					$value = \str_replace(
						$urlSpecialChar[0], $urlSpecialChar[1], $value
					);
					$headers[$key] = $value;
					break;
				}
			}
		}

		return HttpRequestHelper::sendRequest(
			$fullUrl, $method, $user, $password, $headers, $body, $config, null,
			$stream, $timeout
		);
	}

	/**
	 * get the dav path
	 *
	 * @param string $user
	 * @param int $davPathVersionToUse (1|2)
	 * @param string $type
	 *
	 * @throws InvalidArgumentException
	 * @return string
	 */
	public static function getDavPath(
		$user, $davPathVersionToUse = 1, $type = "files"
	) {
		if ($davPathVersionToUse === 1) {
			return "remote.php/webdav/";
		} elseif ($davPathVersionToUse === 2) {
			if ($type === "files") {
				return "remote.php/dav" . '/files/' . $user . "/";
			} else {
				return "remote.php/dav";
			}
		} else {
			throw new InvalidArgumentException(
				"DAV path version $davPathVersionToUse is unknown"
			);
		}
	}

	/**
	 * returns a Sabre client
	 *
	 * @param string $baseUrl
	 * @param string $user
	 * @param string $password
	 *
	 * @return \Sabre\DAV\Client
	 */
	public static function getSabreClient($baseUrl, $user, $password) {
		$settings = [
				'baseUri' => $baseUrl . "/",
				'userName' => $user,
				'password' => $password,
				'authType' => SClient::AUTH_BASIC
		];
		$client = new SClient($settings);
		$client->addCurlSetting(CURLOPT_SSL_VERIFYPEER, false);
		return $client;
	}

	/**
	 * make sure there are no double slash in the URL
	 *
	 * @param string $url
	 * @param bool $trailingSlash forces a trailing slash
	 *
	 * @return string
	 */
	public static function sanitizeUrl($url, $trailingSlash = false) {
		if ($trailingSlash === true) {
			$url = $url . "/";
		} else {
			$url = \rtrim($url, "/");
		}
		$url = \preg_replace("/([^:]\/)\/+/", '$1', $url);
		return $url;
	}
}
