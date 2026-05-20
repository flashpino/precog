<?php

namespace App\Services;

use InfluxDB2\Client as InfluxClient;
use GuzzleHttp\Client as GuzzleClient;

class InfluxConnectionHelper
{
    /**
     * Creates an InfluxDB2 Client instance configured with IPv4 resolve force and customized timeout.
     *
     * @param string $url
     * @param string $org
     * @param string $token
     * @return InfluxClient
     */
    public static function createClient(string $url, string $org, string $token): InfluxClient
    {
        $guzzleClient = new GuzzleClient([
            'timeout' => 30, // Increase timeout to 30 seconds to support slower queries/networks
            'verify' => false, // SSL verification is disabled as requested by environment setup
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Force IPv4 to prevent hanging/timeouts on IPv6 resolution (common in PHP cURL / production hosting setups)
            ],
        ]);

        return new InfluxClient([
            'url' => $url,
            'token' => $token,
            'org' => $org,
            'httpClient' => $guzzleClient,
        ]);
    }
}
