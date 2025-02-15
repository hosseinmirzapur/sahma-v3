<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitToOcr(string $filePath, bool $isImage = false): array
    {
        $client = new Client();
        $url = strval(config('ai-services.ocr.url'));
        if ($isImage) {
            $url = $url . 'original';
        }
        $response = $client->request('POST', $url, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r')
                ]
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception('An error occurred in submit image to OCR');
        }

        return (array)json_decode($response->getBody(), true);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitToOcrEng(string $filePath): array
    {
        $client = new Client();
        $url = strval(config('ai-services.ocr.url'));
        $response = $client->request('POST', $url, [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($filePath, 'r')
                ]
            ],
            'query' => [
                'lang' => 'eng'
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if ($response->getStatusCode() != 200) {
            throw new Exception('An error occurred in submit image to OCR');
        }

        return (array)json_decode($response->getBody(), true);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function downloadSearchableFile(string $link): string
    {
        $client = new Client();
        $response = $client->request(
            'GET',
            strval(config('ai-services.ocr.downloadSearchablePdfLink') . $link)
        );


        if ($response->getStatusCode() != 200) {
            throw new Exception('An error occurred in submit image to OCR');
        }
        return $response->getBody();
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function submitToAsr(string $filePath): array
    {
        $client = new Client();

        $response = $client->request('POST', strval(config('ai-services.stt.url')), [
            'multipart' => [
                [
                    'name' => 'audio_file',
                    'contents' => fopen($filePath, 'r')
                ]
            ],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $responseBody = $response->getBody()->getContents();
        Log::info($responseBody);
        return (array)json_decode($responseBody, true);
    }

    /**
     * @throws Exception
     * @throws GuzzleException
     */
    public function getVoiceWindows(string $content): mixed
    {
        $url = strval(config('ai-services.stt.voice-splitter-url'));
        $client = new Client();
        $response = $client->request('POST', $url, [
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
        ]);
        $body = (array)json_decode(
            json: $response->getBody(),
            associative: true,
            flags: JSON_THROW_ON_ERROR
        );
        if (!isset($body['prediction'])) {
            throw new Exception("Failed to get prediction. Response: {$response->getBody()}");
        }

        return $body['prediction'];
    }
}
