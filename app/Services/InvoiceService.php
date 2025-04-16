<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    protected $client;
    protected $apiUrl;
    protected $clientId;
    protected $secretKey;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->apiUrl = env('API_BASE_URL');
        $this->clientId = env('API_CLIENT_ID');
        $this->secretKey = env('API_SECRET_KEY');
    }

    public function sendInvoiceToApi($xmlFilePath, $invoiceUuid)
    {
        try {
            // Validate inputs
            if (empty($this->apiUrl) || empty($this->clientId) || empty($this->secretKey)) {
                $errorMessage = 'Missing API configuration (URL, Client ID, or Secret Key).';
                Log::error($errorMessage, ['invoice_uuid' => $invoiceUuid]);
                return [
                    'status' => false,
                    'message' => $errorMessage,
                ];
            }

            // Check if XML file exists
            if (!file_exists($xmlFilePath)) {
                $errorMessage = 'XML file not found at path: ' . $xmlFilePath;
                Log::error($errorMessage, ['invoice_uuid' => $invoiceUuid, 'path' => $xmlFilePath]);
                return [
                    'status' => false,
                    'message' => $errorMessage,
                ];
            }

            // Read XML content
            $xmlContent = file_get_contents($xmlFilePath);
            if ($xmlContent === false) {
                $errorMessage = 'Failed to read XML file: ' . $xmlFilePath;
                Log::error($errorMessage, ['invoice_uuid' => $invoiceUuid, 'path' => $xmlFilePath]);
                return [
                    'status' => false,
                    'message' => $errorMessage,
                ];
            }

            // Base64 encode the XML content
            $base64EncodedXml = base64_encode($xmlContent);

            // Prepare the request body
            $body = json_encode([
                'invoice' => $base64EncodedXml,
            ], JSON_THROW_ON_ERROR);

            // Log request details
            Log::info('Sending invoice to API', [
                'invoice_uuid' => $invoiceUuid,
                'api_url' => $this->apiUrl,
                'client_id' => $this->clientId,
                'body_length' => strlen($body),
            ]);

            // Prepare headers
            $headers = [
                'Client-Id' => $this->clientId,
                'Secret-Key' => $this->secretKey,
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($body),
            ];

            // Make the API request using Guzzle (replacing cURL for consistency with constructor)
            $response = $this->client->post($this->apiUrl, [
                'headers' => $headers,
                'body' => $body,
                'timeout' => 30, // Set a timeout to avoid hanging
            ]);

            // Get HTTP status code
            $httpStatusCode = $response->getStatusCode();

            // Log raw response
            $rawResponse = $response->getBody()->getContents();
            Log::info('API response received', [
                'invoice_uuid' => $invoiceUuid,
                'http_status' => $httpStatusCode,
                'response' => $rawResponse,
            ]);

            // Decode JSON response
            $responseData = json_decode($rawResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorMessage = 'Failed to parse API response: ' . json_last_error_msg();
                Log::error($errorMessage, [
                    'invoice_uuid' => $invoiceUuid,
                    'raw_response' => $rawResponse,
                ]);
                return [
                    'status' => false,
                    'message' => $errorMessage,
                ];
            }

            // Check for API-specific errors
            if (
                isset($responseData['EINV_RESULTS']['status']) &&
                $responseData['EINV_RESULTS']['status'] === 'ERROR'
            ) {
                $errorDetails = $responseData['EINV_RESULTS']['ERRORS'][0] ?? [];
                $errorMessage = $errorDetails['EINV_MESSAGE'] ?? 'Unknown API error';
                $errorCode = $errorDetails['EINV_CODE'] ?? null;

                Log::error('API returned an error', [
                    'invoice_uuid' => $invoiceUuid,
                    'error_message' => $errorMessage,
                    'error_code' => $errorCode,
                    'error_details' => $errorDetails,
                ]);

                return [
                    'status' => false,
                    'message' => $errorMessage,
                    'error_code' => $errorCode,
                    'details' => $errorDetails,
                ];
            }

            // Handle non-200 status codes
            if ($httpStatusCode < 200 || $httpStatusCode >= 300) {
                $errorMessage = 'API returned non-success status: ' . $httpStatusCode;
                Log::error($errorMessage, [
                    'invoice_uuid' => $invoiceUuid,
                    'http_status' => $httpStatusCode,
                    'response_data' => $responseData,
                ]);
                return [
                    'status' => false,
                    'message' => $errorMessage,
                    'http_status' => $httpStatusCode,
                    'details' => $responseData,
                ];
            }

            // Successful response
            Log::info('Invoice sent successfully', [
                'invoice_uuid' => $invoiceUuid,
                'response_data' => $responseData,
            ]);

            return [
                'status' => true,
                'data' => $responseData,
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Handle 4xx errors (e.g., 401 Unauthorized, 400 Bad Request)
            $response = $e->getResponse();
            $httpStatusCode = $response ? $response->getStatusCode() : null;
            $rawResponse = $response ? $response->getBody()->getContents() : '';
            $responseData = json_decode($rawResponse, true) ?: [];

            $errorMessage = 'API client error: ' . $e->getMessage();
            Log::error($errorMessage, [
                'invoice_uuid' => $invoiceUuid,
                'http_status' => $httpStatusCode,
                'response' => $rawResponse,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => $errorMessage,
                'http_status' => $httpStatusCode,
                'details' => $responseData,
            ];
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            // Handle 5xx errors (e.g., 500 Internal Server Error)
            $response = $e->getResponse();
            $httpStatusCode = $response ? $response->getStatusCode() : null;
            $rawResponse = $response ? $response->getBody()->getContents() : '';
            $responseData = json_decode($rawResponse, true) ?: [];

            $errorMessage = 'API server error: ' . $e->getMessage();
            Log::error($errorMessage, [
                'invoice_uuid' => $invoiceUuid,
                'http_status' => $httpStatusCode,
                'response' => $rawResponse,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => $errorMessage,
                'http_status' => $httpStatusCode,
                'details' => $responseData,
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Handle network errors (e.g., timeout, DNS failure)
            $errorMessage = 'Network error connecting to API: ' . $e->getMessage();
            Log::error($errorMessage, [
                'invoice_uuid' => $invoiceUuid,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => $errorMessage,
            ];
        } catch (\Exception $e) {
            // Catch any other unexpected exceptions
            $errorMessage = 'Unexpected error: ' . $e->getMessage();
            Log::error($errorMessage, [
                'invoice_uuid' => $invoiceUuid,
                'exception' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => $errorMessage,
            ];
        }
    }
}
