<?php

namespace VietnamWorks;

use GuzzleHttp\Psr7\MultipartStream;
use Http\Client\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Http\Message\ResponseInterface;
use VietnamWorks\Constants\ExceptionMessages;
use VietnamWorks\Exceptions\ExpiredToken;
use VietnamWorks\Exceptions\GenericHTTPError;
use VietnamWorks\Exceptions\InvalidCredentials;
use VietnamWorks\Exceptions\MissingEndpoint;
use VietnamWorks\Exceptions\MissingRequiredParameters;
use VietnamWorks\TokenStorageInterface;

class VnwRestClient
{
    /**
     * @var HttpClient|null
     */
    protected $httpClient;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $apiSecret;

    /**
     * @var string
     */
    protected $apiHost;

    /**
     * The version of the API to use
     * @var string
     */
    protected $apiVersion = 'v2';

    /**
     * If we should use SSL or not
     * @var bool
     */
    protected $sslEnabled = true;

    /**
     * @var null
     */
    protected $accessToken = null;

    /**
     * @var null
     */
    protected $tokenStorage = null;


    /**
     * @param $apiKey
     * @param $apiSecret
     * @param $apiHost
     * @param HttpClient|null $httpClient
     */
    public function __construct($apiKey, $apiSecret, $apiHost, HttpClientInterface $httpClient = null)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->apiHost = $apiHost;
        $this->httpClient = $httpClient;
    }

    /**
     * @param $method
     * @param $uri
     * @param string $accessToken
     * @param null $body
     * @param array $headers
     * @param array $files
     * @return \stdClass
     * @throws ExpiredToken
     * @throws GenericHTTPError
     * @throws InvalidCredentials
     * @throws MissingEndpoint
     * @throws MissingRequiredParameters
     */
    public function send($method, $uri, $accessToken = '', $body = null, $headers = array(), $files = array())
    {
        $options = [];
        $client = $this->getHttpClient();
        if (!empty($accessToken)) {
            $options['auth_bearer'] = $accessToken;
        }

        // if (!empty($files)) {
        //     $body = new MultipartStream($files);
        //     $headers = array_merge($headers, ['Content-Type' => 'multipart/form-data; boundary=' . $body->getBoundary()]);
        // }

        $client->withOptions([
            'headers' => $headers
        ]);

        if ($headers) {
            $options['headers'] = $headers;
        }

        if ($body) {
            $options['body'] = $body;
        } 

        $response = $client->request(
            $method,
            $this->getApiUrl($uri),
            $options
        );
        $data = (string)$response->getContent();
        return $this->responseHandler($response);
    }

    /**
     * @param $endpointUrl
     * @param string $accessToken
     * @param array $queryString
     * @return \stdClass
     */
    public function get($endpointUrl, $accessToken = '', $queryString = array(), $headers = array())
    {
        return $this->send('GET', $endpointUrl . '?' . http_build_query($queryString), $accessToken, null, $headers);
    }

    /**
     * @param $endpointUrl
     * @param $accessToken
     * @param array $postData
     * @param array $files
     * @return \stdClass
     */
    public function post($endpointUrl, $accessToken, $postData = array(), $files = array(), $headers = array())
    {
        $postDataMultipart = [];
        $postDataMultipart[] = array(
            'name' => 'json',
            'contents' => json_encode($postData)
        );

        $body['json'] = json_encode($postData);
        if (!empty($files)) {
            $postDataMultipart[] = array(
                'name' => $files['name'],
                'contents' => fopen($files['file'], 'r'),
                'filename' => $files['file_name']
            );
        }

        return $this->send('POST', $endpointUrl, $accessToken, [], $headers, $postDataMultipart);
    }

    /**
     * @param $endpointUrl
     * @param $accessToken
     * @param array $postData
     * @param array $files
     * @return \stdClass
     */
    public function postRequest($endpointUrl, $accessToken, $postData = array(), $files = array(), $headers = array())
    {
        if (empty($headers)) {
            $headers = array('Content-Type' => 'application/json');
        }

        return $this->send('POST', $endpointUrl, $accessToken, json_encode($postData), $headers);
    }

    /**
     * @param ResponseInterface $response
     * @return \stdClass
     * @throws ExpiredToken
     * @throws GenericHTTPError
     * @throws InvalidCredentials
     * @throws MissingEndpoint
     * @throws MissingRequiredParameters
     */
    private function responseHandler($response)
    {
        $httpResponseCode = $response->getStatusCode();
        $result = new \stdClass();
        $data = (string)$response->getContent();
        $jsonResponseData = json_decode($data, false);

        if ($httpResponseCode == 200) {
            // return response data as json if possible, raw if not
            $result->http_response_body = $data && $jsonResponseData === null ? $data : $jsonResponseData;
        } elseif ($httpResponseCode == 400) {
            throw new MissingRequiredParameters(ExceptionMessages::EXCEPTION_MISSING_REQUIRED_PARAMETERS . $this->getResponseExceptionMessage($response));
        } elseif ($httpResponseCode == 401) {
            if ($jsonResponseData->error == 'invalid_grant') {
                throw new ExpiredToken(ExceptionMessages::EXCEPTION_INVALID_CREDENTIALS);
            } else {
                throw new InvalidCredentials(ExceptionMessages::EXCEPTION_INVALID_CREDENTIALS);
            }
        } elseif ($httpResponseCode == 404) {
            throw new MissingEndpoint(ExceptionMessages::EXCEPTION_MISSING_ENDPOINT . $this->getResponseExceptionMessage($response));
        } else {
            throw new GenericHTTPError(ExceptionMessages::EXCEPTION_GENERIC_HTTP_ERROR, $httpResponseCode, $response->getBody());
        }

        $result->http_response_code = $httpResponseCode;

        return $result;
    }

    /**
     * @param ResponseInterface $responseObj
     *
     * @return string
     */
    protected function getResponseExceptionMessage(ResponseInterface $responseObj)
    {
        $body = (string) $responseObj->getBody();
        $response = json_decode($body);
        if (json_last_error() == JSON_ERROR_NONE && isset($response->message)) {
            return ' ' . $response->message;
        }
    }

    /**
     *
     * @return HttpClient
     */
    protected function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @param mixed $httpClient
     */
    public function setHttpClient($httpClient)
    {
        $this->httpClient = $httpClient;
    }


    /**
     * @param string $apiVersion
     *
     * @return RestClient
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;

        return $this;
    }

    /**
     * @param boolean $sslEnabled
     *
     * @return RestClient
     */
    public function setSslEnabled($sslEnabled)
    {
        $this->sslEnabled = $sslEnabled;

        return $this;
    }


    /**
     * @param $uri
     *
     * @return string
     */
    private function getApiUrl($uri)
    {
        return $this->generateEndpoint($this->apiHost, $this->apiVersion, $this->sslEnabled) . $uri;
    }


    /**
     * @param string $apiEndpoint
     * @param string $apiVersion
     * @param bool $ssl
     *
     * @return string
     */
    private function generateEndpoint($apiEndpoint, $apiVersion, $ssl)
    {
        if (!$ssl) {
            return 'http://' . $apiEndpoint . '/';
        } else {
            return 'https://' . $apiEndpoint . '/';
        }
    }

    /**
     * @return null
     */
    public function getTokenStorage()
    {
        return $this->tokenStorage;
    }

    /**
     * @param null $tokenStorage
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }
}
