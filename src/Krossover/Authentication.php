<?php
namespace Krossover;

use Krossover\Models;
use GuzzleHttp\Client;

class Authentication implements Interfaces\Environment
{
    use Traits\Request;

    /**
     * @var string
     */
    var $code;

    /**
     * @var int
     */
    private $xClientId;

    /**
     * @var Client
     */
    private $client;

    /**
     * Authentication constructor.
     * @param $isProductionEnvironment
     * @param $xClientId
     */
    public function __construct(
        $isProductionEnvironment,
        $xClientId
    ) {
        $this->setKrossoverUri($isProductionEnvironment);
        $this->client = new Client(['base_uri' => $this->krossoverUri]);
    }

    /**
     * @param $username
     * @param $password
     * @return mixed
     * @throws \Exception
     */
    public function getKOOauthToken($username, $password)
    {
        $uri = $this->krossoverUri.'/intelligence-api/oauth/authorize?response_type=code&client_id=cid&state=xyz';

        $body = [
            [
                'name' => 'authorized',
                'contents' => 'yes'
            ],
            [
                'name' => 'username',
                'contents' => $username
            ],
            [
                'name' => 'password',
                'contents' => $password
            ],
        ];

        $options = ['multipart' => $body];

        try {
            $response = $this->client->post($uri,  $options);
        } catch (\Exception $e) {
            throw new \Exception("Can't connect to Krossover API. Check the provided login and password. (message: {$e->getResponse()->getBody()->getContents()})");
        }
        $responseBody = json_decode($response->getBody()->getContents(), true);

        if (isset($responseBody['code'])) {
            return $this->getToken($responseBody['code']);
        } else {
            throw new \Exception('Can\'t connect to Krossover API');
        }
    }

    /**
     * @param $code
     * @return mixed
     * @throws \Exception
     */
    private function getToken($code)
    {
        $uri = $this->krossoverUri.'/intelligence-api/oauth/token';

        $body = [
            [
                'name' => 'grant_type',
                'contents' => 'authorization_code'
            ],
            [
                'name' => 'code',
                'contents' => $code
            ],
        ];

        $headers = [
            'X-Client-Id' => $this->xClientId,
            'Authorization' => 'Basic Y2lkOmNzZWNyZXQ='
        ];

        $options = ['headers' => $headers,  'multipart' => $body];

        try {
            $response = $this->client->post($uri,  $options);
        } catch (\Exception $e) {
            throw new \Exception("Can't connect to Krossover API. (message: {$e->getResponse()->getBody()->getContents()})");
        }

        $responseBody = json_decode($response->getBody()->getContents(), true);

        if (isset($responseBody["access_token"])) {
            return $responseBody["access_token"];
        } else {
            throw new \Exception('Can\'t connect to Krossover API');
        }
    }
}
