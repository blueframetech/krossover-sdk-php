<?php
namespace Krossover\Traits;

use Krossover\Interfaces\Environment;
use GuzzleHttp\Client;

/**
 * Class Logging
 * @package Krossover\Traits
 */
trait Logging
{
    /**
     * @param $message
     * @return string
     */
    public function log($message)
    {
        try {
            $body = [
                'message' => $message,
                'src' => 'SDK'
            ];

            $client = new Client();

            $options = [];
            $options['json'] = $body;
            $response = $client->request('POST', Environment::LOGGLY_URI, $options);

            return $response->getBody()->getContents();
        } catch (\Exception $e) {
            return null;
        }
    }
}
