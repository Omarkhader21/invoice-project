<?php

namespace App\Http\Controllers\Api\Invoices;

use SimpleXMLElement;
use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class SendInvoiceController extends Controller
{
    public function sendInvoice($id)
    {
        // Fetch invoice data
        $invoiceData = DB::connection("odbc")->select("SELECT * FROM [fawtara-01] WHERE [uuid] = ?", [$id]);

        if (empty($invoiceData)) {
            flash()->error("The invoice does not exist.");
            return back();
        }

        [$invoiceData] = $invoiceData; // Extract the first (and only) record

        // Fetch related items
        $items = DB::connection("odbc")->table("fawtara-02")->where("uuid", $id)->get();
        $invoiceData->items = $items;

        // Choose the correct function based on invoice type
        $xml = null;

        if ($invoiceData->invoicetypecode === '388') { // General Sales Invoice
            $xml = $this->generateGeneralSalesInvoiceXml($invoiceData);
        } elseif ($invoiceData->invoicetypecode === '381') { // Credit Invoice
            $xml = $this->generateCreditInvoiceXml($invoiceData);
        } else {
            flash()->error("Unsupported invoice type.");
            return back();
        }

        // Save the XML to a file
        $directory = storage_path('invoices');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = strtolower($invoiceData->invoicetypecode === '388' ? 'general_sales_invoice_' : 'credit_sales_invoice_') . $id . '.xml';
        $filePath = $directory . '/' . $fileName;
        file_put_contents($filePath, $xml);

        // Optionally, send the XML to the API
        return response()->download($filePath, $fileName);
    }


    protected function generateGeneralSalesInvoiceXml($invoiceData)
    {
        // Prepare the XML structure
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
            ['rootElementName' => 'Invoice', '_attributes' => [
                'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
            ]]
        );
    }

    protected function generateCreditInvoiceXml($invoiceData)
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
                    'cbc:ID' => $invoiceData->original_invoice_id, // Original invoice number
                    'cbc:UUID' => $invoiceData->original_invoice_uuid, // Original invoice UUID
                    'cbc:DocumentDescription' => $invoiceData->original_invoice_description, // Original invoice details
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
                        '_attributes' => ['unitCode' => 'PCE'], // Example unit code
                        '_value' => $item->invoicedquantity, // Returned quantity
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'], // Currency
                        '_value' => -1 * $item->lineextensionamount, // Negative amount for returned items
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // Currency
                            '_value' => -1 * $item->taxamount, // Negative tax amount
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'], // Currency
                                '_value' => -1 * $item->taxamount, // Negative tax amount
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
                    '_value' => -1 * $invoiceData->taxexclusiveamount, // Negative total before tax
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => -1 * $invoiceData->taxinclusiveamount, // Negative total including tax
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // Currency
                    '_value' => -1 * $invoiceData->taxinclusiveamount, // Payable amount
                ],
            ],
            'cbc:Note' => $invoiceData->reason, // Reason for return
        ];

        // Generate XML using Spatie's ArrayToXml
        return ArrayToXml::convert(
            array_filter($invoiceArray), // Remove null values
            ['rootElementName' => 'Invoice', '_attributes' => [
                'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
            ]]
        );
    }
}
