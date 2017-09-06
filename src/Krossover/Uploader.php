<?php
namespace Krossover;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Class Uploader
 * @package Krossover
 */
class Uploader
{
    const UPLOAD_URI = '/intelligence-api/v3/upload';

    private $awsPublicKey;

    private $awsPrivateKey;

    private $krossoverUrl;

    private $krossoverToken;

    private $awsBucket;

    private $fileName;

    private $filePath;

    private $guid;

    /**
     * Uploader constructor.
     * @param $awsPublicKey
     * @param $awsPrivateKey
     * @param $krossoverUrl
     * @param $krossoverToken
     */
    public function __construct($awsPublicKey, $awsPrivateKey, $krossoverUrl, $krossoverToken)
    {
        $this->awsPublicKey = $awsPublicKey;
        $this->awsPrivateKey = $awsPrivateKey;
        $this->krossoverUrl = $krossoverUrl;
        $this->krossoverToken = $krossoverToken;
    }

    /**
     *
     * @param $fileName
     * @param $filePath
     */
    protected function uploadFile($fileName, $filePath)
    {
        $this->setRequiredUploadInformation();
    }

    protected function setRequiredUploadInformation()
    {
        $krossoverResponse = json_decode($this->getRequiredUploadInformation()->getBody()->getContents(), true);
    }

    /**
     * Gets a unique GUID for the upload and information about the S3 Bucket
     * @return mixed
     * @throws \Exception
     */
    private function getRequiredUploadInformation()
    {
        $client = new Client(['base_uri' => $this->krossoverUrl]);

        $headers = ['Authorization' => $this->krossoverToken];
        $response =  $client->request('GET', self::UPLOAD_URI, ['headers' => $headers]);

        return $response;
    }
}