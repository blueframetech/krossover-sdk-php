<?php
namespace Krossover\Traits;

/**
 * Class Request
 * @package Krossover\Traits
 */
trait Request
{
    /**
     * @var array
     */
    protected $formUrlEncodedHeader;

    /**
     * @var array
     */
    protected $jsonHeader;

    protected function setHeaders($krossoverToken, $clientId)
    {
        $this->formUrlEncodedHeader = [
            'Authorization' => 'Bearer '.$krossoverToken,
            'X-Client-Id' => $clientId,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $this->jsonHeader = [
            'Authorization' => 'Bearer '.$krossoverToken,
            'X-Client-Id' => $clientId,
            'Content-Type' => 'application/json'
        ];
    }
}