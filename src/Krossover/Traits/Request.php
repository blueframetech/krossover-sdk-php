<?php
namespace Krossover\Traits;

use GuzzleHttp\Client;

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

    /**
     * @var array
     */
    private $typesDictionary;

    /**
     * @var string
     */
    private $krossoverUri;

    /**
     * Set the headers our requests use
     *
     * @param $krossoverToken
     * @param $clientId
     */
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

    protected function setKrossoverUri($isProductionEnvironment) {
        $this->krossoverUri = ($isProductionEnvironment) ? self::KO_PROD_URI : self::KO_PREPROD_URI;
    }

    /**
     * @param $type
     * @param $body
     * @return mixed
     */
    protected function transformBodyToJsonApiSpec($type, $body)
    {
        $transformedBody = [];

        if (!is_array($body)) {
            $bodyArray = $body->toArray();
        } else {
            $bodyArray = $body;
        }

        if (key_exists('id', $bodyArray)) {
            unset($bodyArray['id']);
        }

        if ($id = $this->extractId($body)) {
            $transformedBody['id'] = $id;
        }

        $transformedBody['type'] = $type;
        $transformedBody['attributes'] = $this->getAttributesFromBody($bodyArray);
        $transformedBody['relationships'] = $this->getRelationsFromBody($bodyArray);

        return $transformedBody;
    }

    /**
     * @param $body
     * @return bool
     */
    private function extractId($body) {
        return (!empty($body->id)) ? $body->id : false;
    }

    /**
     * @param array $attributes
     * @return mixed
     */
    private function getAttributesFromBody(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if (substr($key, -2) === 'Id') {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /**
     * @param array $body
     * @return array
     */
    private function getRelationsFromBody(array $body)
    {
        $relationships = [];

        foreach ($body as $key => $value) {
            if (substr($key, -2) === 'Id') {
                if (!empty($value) && !is_null($value)) {
                    $relationshipKey = substr($key, 0, strlen($key) - 2);
                    if (empty($relationships[$relationshipKey])) {
                        $relationships[$relationshipKey] = $this->getRelationship($relationshipKey, $value);
                    }
                }
            }
        }
        
        return $relationships;
    }

    /**
     * @param $relationshipKey
     * @param $value
     * @return array
     */
    private function getRelationship($relationshipKey, $value)
    {
        $type = (!empty($this->typesDictionary[$relationshipKey])) ?
                $this->typesDictionary[$relationshipKey]
                : $this->singularToPlural($this->camelCaseToDashes($relationshipKey));

        return [
            'data' =>
                [
                    'type' => $type,
                    'id' => (string) $value
                ],
        ];
    }

    /**
     * @param $singular
     * @return mixed
     */
    private function singularToPlural($singular)
    {
        if (substr($singular, -1) !== 's') {
            return $singular.'s';
        } else {
            return $singular;
        }
    }

    /**
     * @param $string
     * @return mixed
     */
    private function camelCaseToDashes($string)
    {
        $dashedString = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);
        $dashedString = preg_replace('/([^-])([A-Z])([a-z])/', '$1-$2$3', $dashedString);

        return strtolower($dashedString);
    }

    /**
     * @param $method
     * @param $uri
     * @param null $json
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    protected function jsonRequest($method, $uri, $json = null)
    {
        $client = new Client(['base_uri' => $this->krossoverUri]);
        $headers = $this->jsonHeader;

        $options = ['headers' => $headers];

        if (!is_null($json)) {
            $options['json'] = $json;
        }

        try {
            $response = $client->request($method, $uri, $options);
        } catch (\Exception $e) {
            throw new \Exception("Can't connect to Krossover API. Check the provided token and vars. (message: {$e->getResponse()->getBody()->getContents()})");
        }

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param $method
     * @param $uri
     * @param null $json
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    protected function koJsonApiRequest($method, $uri, $json = null)
    {
        try {
            $response = $this->jsonRequest($method, $uri, $json);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $this->transformJsonApiResponse($response);
    }

    /**
     * @param $response
     * @return mixed
     * @throws \Exception
     */
    protected function transformJsonApiResponse($response)
    {
        if ((!empty($response['data'])) && (key_exists('attributes', $response['data'])) && (is_array($response['data']['attributes']))) {
            $body = $response['data']['attributes'];

        } elseif ((key_exists('attributes', $response)) && (is_array($response['attributes']))) {
            $body = $response['attributes'];

        } elseif ((key_exists('data', $response)) && (is_array($response['data']))) {
            $body = [];

            foreach ($response['data'] as $item) {
                $body[] = $this->transformJsonApiResponse($item);
            }

        } elseif (!empty($response['meta'])) {
            //If the body doesn't have attributes but meta, we return the meta as the response
            return $response['meta'];

        } else {
            throw new \Exception('The response obtained is not in a JSON Api format or there are no attributes.');

        }

        if ($id = $this->extractResponseId($response)) {
            $body['id'] = $id;
        }

        $relationships = $this->extractRelationships($response);

        return array_merge($body, $relationships);
    }

    /**
     * @param $response
     * @return array
     */
    private function extractRelationships($response)
    {
        $body = [];

        $relationships = $this->getRelationships($response);
        $includes = $this->getIncludes($response);

        foreach ($relationships as $key => $relationship) {
            if (key_exists('id', $relationship['data'])) {
                $idKey = "{$key}Id";
                $id = $relationship['data']['id'];
                $body[$idKey] = $id;

                if (!empty($includes[$relationship['data']['type']][$id])) {
                    $body[$key] = $this->transformJsonApiResponse($includes[$relationship['data']['type']][$id]);
                }
            } else {
                $idKey = "{$key}Id";
                $body[$key] = [];
                foreach ($relationship['data'] as $itemRelationship) {
                    if (key_exists('id', $itemRelationship)) {
                        $id = $itemRelationship['id'];
                        $body[$idKey][] = $id;

                        if (!empty($includes[$itemRelationship['type']][$id])) {
                            if (empty($body[$key])) {
                                $body[$key] = [];
                            }
                            $body[$key][] = $this->transformJsonApiResponse($includes[$itemRelationship['type']][$id]);
                        }
                    }
                }
            }
        }

        return $body;
    }

    /**
     * @param $response
     * @return bool
     */
    private function extractResponseId($response)
    {
        if (!empty($response['data'])) {
            if (key_exists('id', $response['data'])) {
                return $response['data']['id'];
            }
        }
        if (key_exists('id', $response)) {
            return $response['id'];
        }

        return false;
    }

    /**
     * @param $response
     * @return array
     */
    private function getRelationships($response)
    {
        if (!empty($response['data']['relationships'])) {
            $relationships = $response['data']['relationships'];
        } elseif (!empty($response['relationships'])) {
            $relationships = $response['relationships'];
        } else {
            //There are no relationships on this body
            return [];
        }

        return $relationships;
    }

    /**
     * @param $response
     * @return array
     */
    private function getIncludes($response) {
        if (!empty($response['data']['included'])) {
            $includes = $response['data']['included'];
        } elseif (!empty($response['included'])) {
            $includes = $response['included'];
        } else {
            //There are no relationships on this body
            return [];
        }

        foreach ($includes as $include) {
            if (empty($includes[$include['type']])) {
                $includes[$include['type']] = [];
            }

            $includes[$include['type']][$include['id']] = $include;
        }

        return $includes;
    }
}
