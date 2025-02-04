<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class AiService
{
    private Client $client;
    private string $ocrUrl;
    private string $sttUrl;
    private string $voiceSplitterUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->ocrUrl = strval(config('ai-services.ocr.url'));
        $this->sttUrl = strval(config('ai-services.stt.url'));
        $this->voiceSplitterUrl = strval(config('ai-services.stt.voice-splitter-url'));
    }

    /**
     * Submits a file to OCR processing.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitToOcr(string $filePath, bool $isImage = false, ?string $language = null): array
    {
        $url = $isImage ? "{$this->ocrUrl}original" : $this->ocrUrl;

        $fileResource = fopen($filePath, 'r');
        if ($fileResource === false) {
            throw new Exception("Failed to open file: {$filePath}");
        }

        $options = [
            RequestOptions::MULTIPART => [
                [
                    'name' => 'file',
                    'contents' => $fileResource,
                ]
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if ($language !== null) {
            $options[RequestOptions::QUERY] = ['lang' => $language];
        }

        return $this->sendRequest('POST', $url, $options, 'submit image to OCR');
    }

    /**
     * Downloads a searchable PDF file.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function downloadSearchableFile(string $link): string
    {
        $response = $this->sendRequest(
            'GET',
            "{$this->ocrUrl}downloadSearchablePdfLink/{$link}",
            [],
            'download searchable PDF',
            false
        );

        return is_string($response) ? $response : throw new Exception('Invalid response for downloadable PDF.');
    }

    /**
     * Submits an audio file to ASR processing.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitToAsr(string $filePath): array
    {
        $fileResource = fopen($filePath, 'r');
        if ($fileResource === false) {
            throw new Exception("Failed to open audio file: {$filePath}");
        }

        $options = [
            RequestOptions::MULTIPART => [
                [
                    'name' => 'audio_file',
                    'contents' => $fileResource,
                ]
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        return $this->sendRequest('POST', $this->sttUrl, $options, 'submit audio file to ASR');
    }

    /**
     * Retrieves voice windows from a given audio content.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function getVoiceWindows(string $content): array
    {
        $options = [
            RequestOptions::MULTIPART => [
                [
                    'name' => 'file',
                    'contents' => $content,
                    'filename' => 'name.wav'
                ],
                [
                    'name' => 'simple_cut',
                    'contents' => 'true'
                ]
            ],
            RequestOptions::TIMEOUT => 900
        ];

        $response = $this->sendRequest('POST', $this->voiceSplitterUrl, $options, 'get voice windows');

        if (!isset($response['prediction']) || !is_array($response['prediction'])) {
            throw new Exception("Invalid response format for voice windows: " . json_encode($response));
        }

        return $response['prediction'];
    }

    /**
     * Helper function to send HTTP requests.
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $options
     * @param string $errorMessage
     * @param bool $decodeJson
     * @return array<string, mixed>|string
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendRequest(
        string $method,
        string $url,
        array $options,
        string $errorMessage,
        bool $decodeJson = true
    ): array|string {
        $response = $this->client->request($method, $url, $options);

        if ($response->getStatusCode() !== 200) {
            throw new Exception("Failed to $errorMessage, status code: {$response->getStatusCode()}");
        }

        $body = $response->getBody()->getContents();
        Log::info($body);

        if (!$decodeJson) {
            return $body;
        }

        if (!is_array(json_decode($body, true, 512, JSON_THROW_ON_ERROR))) {
            throw new Exception("Failed to decode JSON: " . json_encode($body));
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
