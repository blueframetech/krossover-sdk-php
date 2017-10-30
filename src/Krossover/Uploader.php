<?php
namespace Krossover;

use Aws\Multipart\UploadState;
use GuzzleHttp\Client;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

/**
 * Class Uploader
 * @package Krossover
 */
class Uploader implements Interfaces\Environment
{
    use Traits\Request;
    use Traits\Logging;

    const UPLOAD_URI = '/intelligence-api/v3/upload';
    const AWS_REGION = 'us-east-1';
    const AWS_ACL = 'public-read';
    const AWS_VERSION = 'latest';

    /**
     * @var array
     */
    private $credentials;

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
     * @var
     */
    private $tries = 0;

    /**
     * @var int
     */
    private $retries;

    /**
     * Uploader constructor.
     * @param array $credentials - Array containing the credentials for Amazon AWS
     * @param boolean $isProductionEnvironment - true if is a production environment/false if testing or developing
     * @param string $krossoverToken - KO token obtained after authenticating
     * @param int $clientId - KO client ID - provided by KO Tech team.
     * @param int $retries
     */
    public function __construct($credentials, $isProductionEnvironment, $krossoverToken, $clientId, $retries = 5)
    {
        $this->credentials = $credentials;
        $this->setKrossoverUri($isProductionEnvironment);
        $this->setHeaders($krossoverToken, $clientId);
        $this->retries = $retries;
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

        $success = true;

        while ($this->tries < $this->retries) {
            $success = true;
            $this->tries++;

            try {
                $uploader->upload();
            } catch (MultipartUploadException $e) {
                $success = false;
                $this->log("{$e->getCode()}: {$this->fileName} - {$e->getMessage()}");
                $this->log("{$e->getTraceAsString()}");
            }

            if ($success) {
                break;
            }
        }

        //If after retrying the status is still fail
        if (!$success) {
            $this->log("Stopped trying to upload {$this->fileName}");
            throw new \Exception('There was an error uploading the file');
        } else {
            //Signals KO to start the uploader workflow
            $this->signalCompletedFileUpload();
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
        $client = new Client(['base_uri' => $this->krossoverUri]);

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
        $client = new Client(['base_uri' => $this->krossoverUri]);

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
