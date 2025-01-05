<?php

// app/Services/InvoiceService.php
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
        $this->apiUrl = env('API_BASE_URL'); // URL for the API endpoint
        $this->clientId = env('API_CLIENT_ID');  // Your Client ID
        $this->secretKey = env('API_SECRET_KEY'); // Your Secret Key
    }

    public function sendInvoiceToApi($xmlFilePath)
    {
        try {
            // Read the content of the XML file
            if (!file_exists($xmlFilePath)) {
                throw new Exception('XML file not found at path: ' . $xmlFilePath);
            }

            $xmlContent = file_get_contents($xmlFilePath);

            // Debug the XML content being read
            // Log::info('XML Content:', ['xml_content' => $xmlContent]);

            // Base64 encode the XML content
            $base64EncodedXml = base64_encode($xmlContent);

            // Prepare the body data as JSON
            $body = json_encode([
                'invoice' => $base64EncodedXml,
            ]);

            // Debug the exact body being sent
            // Log::info('Body Sent to API:', ['body' => $body]);

            // Prepare the cURL headers
            $headers = [
                'Client-Id: ' . $this->clientId,
                'Secret-Key: ' . $this->secretKey,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set the cURL options
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Execute the cURL request
            $response = curl_exec($ch);

            // Check for cURL errors
            if ($response === false) {
                $errorMessage = curl_error($ch);
                Log::error('cURL Error:', ['error' => $errorMessage]);
                curl_close($ch);
                return [
                    'status' => false,
                    'message' => 'cURL error: ' . $errorMessage,
                ];
            }

            // Close the cURL session
            curl_close($ch);

            // Debug the raw API response
            Log::info('Raw API Response:', ['response' => $response]);

            // Decode the JSON response
            $responseData = json_decode($response, true);

            // Handle API errors in the response
            if (
                isset($responseData['EINV_RESULTS']['status']) &&
                $responseData['EINV_RESULTS']['status'] === 'ERROR'
            ) {
                $errorDetails = $responseData['EINV_RESULTS']['ERRORS'][0] ?? [];
                $errorMessage = $errorDetails['EINV_MESSAGE'] ?? 'Unknown error';

                Log::error('API Error:', ['message' => $errorMessage, 'details' => $errorDetails]);

                return [
                    'status' => false,
                    'message' => $errorMessage,
                    'details' => $errorDetails,
                ];
            }

            // Successful response
            return [
                'status' => true,
                'data' => $responseData,
            ];
        } catch (Exception $e) {
            Log::error('API request failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => false,
                'message' => 'API request failed: ' . $e->getMessage(),
            ];
        }
    }
}