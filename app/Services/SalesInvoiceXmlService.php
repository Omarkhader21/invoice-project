<?php

namespace App\Services;

use Spatie\ArrayToXml\ArrayToXml;

class SalesInvoiceXmlService extends InvoiceXmlService
{
    public function generateGeneralSalesInvoiceXml($invoiceData)
    {
        $invoiceArray = [
            'cbc:ProfileID' => 'reporting:1.0',
            'cbc:ID' => $invoiceData->id,
            'cbc:UUID' => $invoiceData->uuid,
            'cbc:IssueDate' => date('Y-m-d', strtotime($invoiceData->issuedate)),
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['name' => $invoiceData->invoicetypecode],
                '_value' => '388',
            ],
            'cbc:DocumentCurrencyCode' => 'JOD',
            'cbc:TaxCurrencyCode' => 'JOD',
            'cac:AdditionalDocumentReference' => [
                'cbc:ID' => 'ICV',
                'cbc:UUID' => $this->generateInvoiceNumber(),
            ],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => env('TAX_ID'),
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $this->getCompanyInfo()->suppliername,
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $invoiceData->{'customer_schemetype'}],
                            '_value' => sprintf("%.0f", $invoiceData->customerno),
                        ],
                    ],
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => '1',
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname,
                    ],
                ],
            ],
            'cac:SellerSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => $this->getCompanyInfo()->PartyIdentificationId,
                    ],
                ],
            ],
            'cac:AllowanceCharge' => [
                'cbc:ChargeIndicator' => 'false',
                'cbc:AllowanceChargeReason' => 'discount',
                'cbc:Amount' => [
                    '_attributes' => ['currencyID' => 'JOD'],
                    '_value' => sprintf("%.9f", $invoiceData->amount),
                ],
            ],
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'],
                    '_value' => sprintf("%.9f", $invoiceData->taxamount),
                ],
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount),
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxinclusiveamount),
                ],
                'cbc:AllowanceTotalAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->amount),
                ],
                'cbc:PrepaidAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => '0',
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount - $invoiceData->amount + $invoiceData->taxamount),
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                return [
                    'cbc:ID' => $item->linenu,
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'],
                        '_value' => sprintf("%.9f", $item->InvoicedQuantity),
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'],
                        '_value' => sprintf("%.9f", $item->lineextensionamount),
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->taxamount),
                        ],
                        'cbc:RoundingAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->roundingamount),
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f", $item->taxamount),
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => $item->tax_type,
                                ],
                                'cbc:Percent' => sprintf("%.9f", $item->percent),
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
                        'cbc:Name' => $item->itemname,
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->priceamount),
                        ],
                        'cac:AllowanceCharge' => [
                            'cbc:ChargeIndicator' => 'false',
                            'cbc:AllowanceChargeReason' => 'DISCOUNT',
                            'cbc:Amount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f", $item->amount),
                            ],
                        ],
                    ],
                ];
            })->toArray(),
        ];

        return ArrayToXml::convert(
            array_filter($invoiceArray),
            [
                'rootElementName' => 'Invoice',
                '_attributes' => [
                    'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                ],
            ],
            true,
            'UTF-8',
            '1.0',
            []
        );
    }

    public function generateCreditInvoiceXml($invoiceData)
    {
        $invoiceArray = [
            'cbc:ID' => $invoiceData->id,
            'cbc:UUID' => $invoiceData->uuid,
            'cbc:IssueDate' => date('Y-m-d', strtotime($invoiceData->issuedate)),
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['name' => $invoiceData->invoicetypecode],
                '_value' => '381',
            ],
            'cbc:DocumentCurrencyCode' => 'JOD',
            'cbc:TaxCurrencyCode' => 'JOD',
            'cac:BillingReference' => [
                'cac:InvoiceDocumentReference' => [
                    'cbc:ID' => $invoiceData->{'ref_id'},
                    'cbc:UUID' => $invoiceData->{'ref_uuid'},
                    'cbc:DocumentDescription' => $invoiceData->{'ref_total'},
                ],
            ],
            'cac:AdditionalDocumentReference' => [
                'cbc:ID' => 'ICV',
                'cbc:UUID' => $this->generateInvoiceNumber(),
            ],
            'cac:AccountingSupplierParty' => [
                'cac:Party' => [
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => env('TAX_ID'),
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $this->getCompanyInfo()->suppliername,
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $invoiceData->{'customer_schemetype'}],
                            '_value' => sprintf("%.0f", $invoiceData->customerno),
                        ],
                    ],
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname,
                    ],
                ],
            ],
            'cac:SellerSupplierParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => $this->getCompanyInfo()->PartyIdentificationId,
                    ],
                ],
            ],
            'cac:PaymentMeans' => [
                'cbc:PaymentMeansCode' => [
                    '_attributes' => ['listID' => 'UN/ECE 4461'],
                    '_value' => '10',
                ],
                'cbc:InstructionNote' => $invoiceData->{'instruction_note'},
            ],
            'cac:AllowanceCharge' => [
                'cbc:ChargeIndicator' => 'false',
                'cbc:AllowanceChargeReason' => 'discount',
                'cbc:Amount' => [
                    '_attributes' => ['currencyID' => 'JOD'],
                    '_value' => sprintf("%.9f", $invoiceData->amount),
                ],
            ],
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'],
                    '_value' => sprintf("%.9f", $invoiceData->taxamount),
                ],
                'cac:TaxSubtotal' => $invoiceData->items->map(function ($item) {
                    return [
                        'cbc:TaxableAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->lineextensionamount),
                        ],
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->taxamount),
                        ],
                        'cac:TaxCategory' => [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeAgencyID' => '6',
                                    'schemeID' => 'UN/ECE 5305',
                                ],
                                '_value' => $item->tax_type,
                            ],
                            'cbc:Percent' => sprintf("%.0f", $item->percent),
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
                    ];
                })->toArray(),
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount),
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxinclusiveamount),
                ],
                'cbc:AllowanceTotalAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->amount),
                ],
                'cbc:PrepaidAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => '0',
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JO'],
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount - $invoiceData->amount + $invoiceData->taxamount),
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                return [
                    'cbc:ID' => $item->linenu,
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'],
                        '_value' => sprintf("%.9f", $item->InvoicedQuantity),
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'],
                        '_value' => sprintf("%.9f", $item->lineextensionamount),
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->taxamount),
                        ],
                        'cbc:RoundingAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->roundingamount),
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f", $item->taxamount),
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => $item->tax_type,
                                ],
                                'cbc:Percent' => sprintf("%.9f", $item->percent),
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
                        'cbc:Name' => $item->itemname,
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f", $item->priceamount),
                        ],
                        'cac:AllowanceCharge' => [
                            'cbc:ChargeIndicator' => 'false',
                            'cbc:AllowanceChargeReason' => 'DISCOUNT',
                            'cbc:Amount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f", $item->amount),
                            ],
                        ],
                    ],
                ];
            })->toArray(),
        ];

        return ArrayToXml::convert(
            array_filter($invoiceArray),
            [
                'rootElementName' => 'Invoice',
                '_attributes' => [
                    'xmlns' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
                    'xmlns:cac' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
                    'xmlns:cbc' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
                    'xmlns:ext' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2',
                ],
            ],
            true,
            'UTF-8',
            '1.0',
            []
        );
    }
}
