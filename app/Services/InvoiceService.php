<?php

// app/Services/InvoiceService.php
namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\Log;


class InvoiceService
{
    protected $client;
    protected $apiUrl;
    protected $apiKey;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->apiUrl = env('API_BASE_URL');
        $this->apiKey = env('API_SECRET_KEY');
    }

    public function sendInvoiceToApi($xmlData)
    {
        try {
            // Initialize the Guzzle client
            $client = new Client();

            // Send the POST request
            $response = $client->post($this->apiUrl, [
                'headers' => [
                    'Authorization' => "Bearer $this->apiKey",
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xmlData,
            ]);

            // Get the response body
            $responseBody = $response->getBody();
            $responseData = json_decode($responseBody, true); // Assuming the response is JSON

            // Log the full response for debugging
            Log::info('API Response:', $responseData);
        } catch (Exception $e) {
            // Return an error message if the API request fails
            return ['status' => false, 'message' => 'API request failed: ' . $e->getMessage()];
        }
    }

    public function generateGeneralSalesInvoiceXml($invoiceData)
    {
        $invoiceArray = [
            'cbc:ID' => $invoiceData->id, // Invoice number
            'cbc:UUID' => $invoiceData->uuid, // Unique identifier
            'cbc:IssueDate' => date('Y-m-d', strtotime($invoiceData->issuedate)), // YYYY-MM-DD format
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['name' => $invoiceData->invoicetypecode], // Payment method
                '_value' => '388', // General Sales Invoice type
            ],
            'cbc:DocumentCurrencyCode' => 'JOD',
            'cbc:TaxCurrencyCode' => 'JOD',
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => '12345678', // Example Seller's TIN
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname, // Seller name
                    ],
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                return [
                    'cbc:ID' => $item->linenu, // Line number
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'], // Example unit code
                        '_value' => $item->invoicedquantity, // Quantity
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'], // Currency
                        '_value' => $item->lineextensionamount, // Amount
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => $item->taxamount, // Tax amount
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'], // Currency
                                '_value' => $item->taxamount, // Tax amount
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => 'S', // Example tax category
                                ],
                                'cbc:Percent' => $item->percent * 100, // Tax percentage
                                'cac:TaxScheme' => [
                                    'cbc:ID' => [
                                        '_attributes' => [
                                            'schemeAgencyID' => '6',
                                            'schemeID' => 'UN/ECE 5153',
                                        ],
                                        '_value' => 'VAT',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'cac:Item' => [
                        'cbc:Name' => $item->itemname, // Item name
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => $item->priceamount, // Unit price
                        ],
                    ],
                ];
            })->toArray(), // Convert collection to array
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => $invoiceData->taxexclusiveamount, // Total before tax
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => $invoiceData->taxinclusiveamount, // Total including tax
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => $invoiceData->taxinclusiveamount, // Payable amount
                ],
            ],
        ];

        // Generate XML using Spatie's ArrayToXml
        return ArrayToXml::convert(
            array_filter($invoiceArray), // Remove null values
            [
                'rootElementName' => 'Invoice',
                '_attributes' => [
                    'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                ],
            ],
            true, // Third parameter: Preserve attribute formatting
            'UTF-8', // Fourth parameter: Specify UTF-8 encoding
            false // Fifth parameter: Disable escaping special characters
        );
    }

    public function generateCreditInvoiceXml($invoiceData)
    {
        $invoiceArray = [
            'cbc:ID' => $invoiceData->id, // Credit invoice number
            'cbc:UUID' => $invoiceData->uuid, // Unique identifier
            'cbc:IssueDate' => date('Y-m-d', strtotime($invoiceData->issuedate)), // YYYY-MM-DD format
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['name' => $invoiceData->invoicetypecode], // Payment method
                '_value' => '381', // Credit Invoice type
            ],
            'cbc:DocumentCurrencyCode' => 'JOD',
            'cbc:TaxCurrencyCode' => 'JOD',
            'cac:BillingReference' => [ // Reference to the original invoice
                'cac:InvoiceDocumentReference' => [
                    'cbc:ID' => $invoiceData->{'ref-id'}, // Original invoice number
                    'cbc:UUID' => $invoiceData->{'ref-uuid'}, // Original invoice UUID
                    'cbc:DocumentDescription' => $invoiceData->{'ref-total'}, // Original invoice details
                ],
            ],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => '12345678', // Example Seller's TIN
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname, // Seller name
                    ],
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                return [
                    'cbc:ID' => $item->linenu, // Line number
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'], // Unit code, e.g., PCE for pieces
                        '_value' => $item->invoicedquantity, // Quantity invoiced
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'], // Currency
                        '_value' => -abs($item->lineextensionamount), // Ensure negative for returned items
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => -abs($item->taxamount), // Negative tax amount
                        ],
                        'cbc:RoundingAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => -abs($item->roundingamount), // Negative rounding amount
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxableAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'], // Currency
                                '_value' => -abs($item->lineextensionamount), // Taxable amount
                            ],
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'], // Currency
                                '_value' => -abs($item->taxamount), // Tax amount
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => 'S', // Tax category code
                                ],
                                'cbc:Percent' => $item->percent * 100, // Tax percentage in percent format
                                'cac:TaxScheme' => [
                                    'cbc:ID' => [
                                        '_attributes' => [
                                            'schemeAgencyID' => '6',
                                            'schemeID' => 'UN/ECE 5153',
                                        ],
                                        '_value' => 'VAT', // Tax scheme identifier
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'cac:Item' => [
                        'cbc:Name' => $item->itemname, // Item or service name
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => $item->priceamount, // Unit price before tax
                        ],
                        'cbc:BaseQuantity' => [
                            '_attributes' => ['unitCode' => 'C62'], // Unit of measure, e.g., C62 for "unit"
                            '_value' => 1, // Base quantity
                        ],
                        'cac:AllowanceCharge' => [
                            'cbc:ChargeIndicator' => false, // Indicates discount
                            'cbc:AllowanceChargeReason' => 'DISCOUNT', // Reason for allowance/charge
                            'cbc:Amount' => [
                                '_attributes' => ['currencyID' => 'JOD'], // Currency
                                '_value' => $item->amount, // Discount amount
                            ],
                        ],
                    ],
                ];
            })->toArray(),
            // Convert collection to array
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => -abs($invoiceData->taxexclusiveamount), // Ensure value is always negative
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => -abs($invoiceData->taxinclusiveamount), // Ensure value is always negative
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => -abs($invoiceData->taxinclusiveamount), // Ensure value is always negative
                ],
            ],
            'cbc:Note' => $invoiceData->{'instruction-note'}, // Reason for return
        ];

        // Generate XML using Spatie's ArrayToXml
        return ArrayToXml::convert(
            array_filter($invoiceArray), // Remove null values
            [
                'rootElementName' => 'Invoice',
                '_attributes' => [
                    'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                ],
            ],
            true, // Third parameter: Preserve attribute formatting
            'UTF-8', // Fourth parameter: Specify UTF-8 encoding
            false // Fifth parameter: Disable escaping special characters
        );
    }
}
