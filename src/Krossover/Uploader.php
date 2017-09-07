<?php
namespace Krossover;

use GuzzleHttp\Client;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

/**
 * Class Uploader
 * @package Krossover
 */
class Uploader
{
    use Traits\Request;

    const UPLOAD_URI = '/intelligence-api/v3/upload';
    const AWS_REGION = 'us-east-1';
    const AWS_ACL = 'public-read';
    const AWS_VERSION = 'latest';
    const KO_PREPROD_URI = 'http://v2-pre-prod-app.krossover.com';
    const KO_PROD_URI = 'https://app.krossover.com';

    /**
     * @var array
     */
    private $credentials;

    /**
     * @var string
     */
    private $krossoverUrl;

    /**
     * @var string
     */
    private $awsBucket;

    /**
     * @var string
     */
    private $guid;

    /**
     * @var string
     */
    private $fileName;

    /**
     * Uploader constructor.
     * @param array $credentials - Array containing the credentials for Amazon AWS
     * @param boolean $isProductionEnvironment - true if is a production environment/false if testing or developing
     * @param string $krossoverToken - KO token obtained after authenticating
     * @param int $clientId - KO client ID - provided by KO Tech team.
     */
    public function __construct($credentials, $isProductionEnvironment, $krossoverToken, $clientId)
    {
        $this->credentials = $credentials;
        $this->krossoverUrl = ($isProductionEnvironment) ? self::KO_PROD_URI : self::KO_PREPROD_URI;
        $this->setHeaders($krossoverToken, $clientId);
    }

    /**
     * Uploads a file to KO s3 buckets and signals the API to start the upload workflow
     *
     * @param string $fileName
     * @param string $filePath
     * @return boolean
     * @throws \Exception
     */
    public function uploadFile($fileName, $filePath)
    {
        //Get required instances and information to upload the file
        $this->setRequiredKOUploadInformation();
        $s3Client = $this->getS3Client();

        //Sets the key/filename
        $fileNameExplode = explode(".", $fileName);
        $extension = end($fileNameExplode);
        $this->fileName = $this->guid."_1.{$extension}";

        //Stats a multipart upload
        $uploader = new MultipartUploader(
            $s3Client,
            "{$filePath}{$fileName}",
            [
                'bucket' => $this->awsBucket,
                'key'    => $this->fileName,
            ]
        );

        try {
            $uploader->upload();
            //Signals KO to start the uploader workflow
            $this->signalCompletedFileUpload();
        } catch (MultipartUploadException $e) {
            throw new \Exception("There was an error uploading the specified file. (message: {$e->getMessage()})");
        }

        return true;
    }

    /**
     * Sets the variables needed for uploading videos to this KO environment
     *
     * @throws \Exception
     */
    protected function setRequiredKOUploadInformation()
    {
        $krossoverResponse = json_decode($this->getRequiredKOUploadInformation()->getBody()->getContents(), true);

        $this->guid = $krossoverResponse['guid'];
        $this->awsBucket = $krossoverResponse['rawBucket'];
    }

    /**
     * Gets a unique GUID for the upload and information about the S3 Bucket
     *
     * @return mixed
     * @throws \Exception
     */
    private function getRequiredKOUploadInformation()
    {
        $client = new Client(['base_uri' => $this->krossoverUrl]);

        $headers = $this->formUrlEncodedHeader;

        $body = [
            'partCount' => 1
        ];
        try {
            $response = $client->request('POST', self::UPLOAD_URI, ['headers' => $headers, 'form_params' => $body]);
        } catch (\Exception $e) {
            throw new \Exception("Can't connect to Krossover API. Check the provided token. (message: {$e->getMessage()})");
        }

        return $response;
    }

    /**
     * Gets a S3 client to perform the upload
     *
     * @return S3Client
     */
    private function getS3Client()
    {
        $client = new S3Client([
            $this->credentials,
            'region' => self::AWS_REGION,
            'version' => self::AWS_VERSION
        ]);

        return $client;
    }

    /**
     * Signals the API that the file was uploaded successfuly and that it should start the upload workflow
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function signalCompletedFileUpload()
    {
        $client = new Client(['base_uri' => $this->krossoverUrl]);

        $uri = self::UPLOAD_URI."/file/{$this->guid}";

        $headers = $this->formUrlEncodedHeader;

        $body = [
            'identifier' => $this->fileName,
            'sequence' => 1
        ];

        try {
            $response = $client->request('POST', $uri, ['headers' => $headers, 'form_params' => $body]);
        } catch (\Exception $e) {
            throw new \Exception("Can't connect to Krossover API. Check the provided token. (message: {$e->getMessage()})");
        }

        return $response;
    }

    /**
     * Returns the GUID of the uploaded video.
     *
     * @return string
     */
    public function getGuid()
    {
        return $this->guid;
    }
}