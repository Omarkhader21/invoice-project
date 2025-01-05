<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;

class InvoiceXmlService extends InvoiceService
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
            'cac:AdditionalDocumentReference' => [ // عداد الفاتورة
                'cbc:ID' => 'ICV', // ثابت
                'cbc:UUID' => $this->generateInvoiceNumber(), // عداد الفاتورة
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
                        'cbc:RegistrationName' => $invoiceData->registrationname,
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $invoiceData->{'customer-schemetype'}],
                            '_value' => $invoiceData->customerno,
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
            'cac:SellerSupplierParty' => [ // البيانات الخاصة بتسلسل مصدر الدخل للبائع
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => $this->getCompanyInfo()->partyidentificationid, // تسلسل مصدر الدخل للبائع
                    ],
                ],
            ],
            'cac:AllowanceCharge' => [ // تفاصيل الخصم
                'cbc:ChargeIndicator' => 'false', // يشير إلى أن هذا خصم وليس تكلفة
                'cbc:AllowanceChargeReason' => 'discount', // سبب الخصم
                'cbc:Amount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // عملة الخصم
                    '_value' => sprintf("%.9f",$invoiceData->amount), // مجموع الخصم
                ],
            ],
            'cac:TaxTotal' => [
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // عملة الخصم
                    '_value' => sprintf("%.9f",$invoiceData->taxamount), // مجموع الخصم
                ],
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ قبل الضريبة
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount), // اجمالي الفاتورة قبل الخصم
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ شامل الضريبة
                    '_value' => sprintf("%.9f",$invoiceData->taxinclusiveamount), // اجمالي الفاتورة
                ],
                'cbc:AllowanceTotalAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة مجموع الخصم
                    '_value' => sprintf("%.9f", $invoiceData->amount), // مجموع قيمة الخصم
                ],
                'cbc:PrepaidAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة الدفع المسبق
                    '_value' => '0', // الدفع المسبق ثابت هنا 0
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ المستحق
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount - $invoiceData->amount + $invoiceData->taxamount), // اجمالي الفاتورة بعد الخصم
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                // حساب القيمة الإجمالية للخط (السعر * الكمية - الخصم)
                // $lineExtensionAmount = ($item->priceamount * $item->invoicedquantity) - $item->amount;

                // النسبة الافتراضية للضريبة (الضريبة القياسية)
                $taxCategory = 'S';  // الفئة الافتراضية هي "S" للضريبة القياسية
                $taxPercent = $item->percent;  // النسبة الافتراضية للضريبة (مثال: 0.16 -> 16%)

                // تطبيق النسب المحددة
                $validTaxPercentages = [0, 1, 2, 3, 4, 5, 7, 8, 10, 16]; // النسب المتاحة
                $roundedTaxPercent = round($item->percent * 100); // تقريب النسبة إلى أقرب عدد صحيح

                // تحقق مما إذا كانت النسبة المدخلة موجودة في النسب المعتمدة
                if (in_array($roundedTaxPercent, $validTaxPercentages)) {
                    $taxPercent = $roundedTaxPercent;
                } else {
                    // إذا كانت النسبة غير معتمدة، قم بتعيينها إلى 0% أو أي قيمة أخرى مناسبة
                    $taxPercent = 0;
                    $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                }

                // خاصة للحالات التي فيها النسبة 0.000 أو قريبة منها
                if ($item->taxamount == 0 || $taxPercent == 0) {
                    $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                    $taxPercent = 0;     // تعيين النسبة إلى 0%
                }

                return [
                    'cbc:ID' => $item->linenu,
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'],
                        '_value' => sprintf("%.0f",$item->invoicedquantity),
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'],
                        '_value' => sprintf("%.9f", $item->lineextensionamount),  // Corrected LineExtensionAmount calculation
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf( "%.9f",$item->taxamount),  // Tax value
                        ],
                        'cbc:RoundingAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f",$item->roundingamount),  // Rounding amount, if any
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f",$item->taxamount),  // Tax value (repeated for sub-total)
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => $taxCategory,  // Tax category ("O" for 0% or "S" for standard)
                                ],
                                'cbc:Percent' => $taxPercent,  // Tax percentage (e.g., 16% or 0% based on the condition)
                                'cac:TaxScheme' => [
                                    'cbc:ID' => [
                                        '_attributes' => [
                                            'schemeAgencyID' => '6',
                                            'schemeID' => 'UN/ECE 5153',
                                        ],
                                        '_value' => 'VAT',  // VAT tax scheme
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'cac:Item' => [
                        'cbc:Name' => $item->itemname,  // Item description
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f",$item->priceamount),  // Unit price before tax
                        ],
                        'cac:AllowanceCharge' => [
                            'cbc:ChargeIndicator' => 'false',  // For discount (set to false for discount)
                            'cbc:AllowanceChargeReason' => 'DISCOUNT',  // Reason for the charge (discount)
                            'cbc:Amount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f",$item->amount),  // Discount amount
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
            'cbc:ID' => $invoiceData->id, // رقم فاتورة الارجاع
            'cbc:UUID' => $invoiceData->uuid, // رقم متسلسل لفاتورة الارجاع
            'cbc:IssueDate' => date('Y-m-d', strtotime($invoiceData->issuedate)), // تاريخ فاتورة الارجاع
            'cbc:InvoiceTypeCode' => [
                '_attributes' => ['name' => $invoiceData->invoicetypecode], // طريقة الدفع
                '_value' => '381', // فاتورة ارجاع
            ],
            'cbc:DocumentCurrencyCode' => 'JOD', // عملة المستند
            'cbc:TaxCurrencyCode' => 'JOD', // عملة الضريبة
            'cac:BillingReference' => [ // معلومات الفاتورة المراد الارجاع منها
                'cac:InvoiceDocumentReference' => [
                    'cbc:ID' => $invoiceData->{'ref-id'}, // رقم الفاتورة الأصلية المراد الإرجاع منها
                    'cbc:UUID' => $invoiceData->{'ref-uuid'}, // الرقم المتسلسل للفاتورة الأصلية
                    'cbc:DocumentDescription' => $invoiceData->{'ref-total'}, // إجمالي الفاتورة الأصلية
                ],
            ],
            'cac:AdditionalDocumentReference' => [ // عداد الفاتورة
                'cbc:ID' => 'ICV', // ثابت
                'cbc:UUID' => $this->generateInvoiceNumber(), // عداد الفاتورة
            ],
            'cac:AccountingSupplierParty' => [ // البيانات الخاصة بالبائع (المكلف)
                'cac:Party' => [
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    'cac:PartyTaxScheme' => [
                        'cbc:CompanyID' => env('TAX_ID'),
                        'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname,
                    ],
                ],
            ],
            'cac:AccountingCustomerParty' => [
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => [
                            '_attributes' => ['schemeID' => $invoiceData->{'customer-schemetype'}],
                            '_value' => $invoiceData->customerno,
                        ],
                    ],
                    'cac:PostalAddress' => [
                        'cac:Country' => ['cbc:IdentificationCode' => 'JO'],
                    ],
                    // 'cac:PartyTaxScheme' => [
                    //     'cbc:CompanyID' => '1',
                    //     'cac:TaxScheme' => ['cbc:ID' => 'VAT'],
                    // ],
                    'cac:PartyLegalEntity' => [
                        'cbc:RegistrationName' => $invoiceData->registrationname,
                    ],
                ],
            ],
            'cac:SellerSupplierParty' => [ // البيانات الخاصة بتسلسل مصدر الدخل للبائع
                'cac:Party' => [
                    'cac:PartyIdentification' => [
                        'cbc:ID' => $this->getCompanyInfo()->partyidentificationid, // تسلسل مصدر الدخل للبائع
                    ],
                ],
            ],
            'cac:PaymentMeans' => [ // سبب الإرجاع
                'cbc:PaymentMeansCode' => [
                    '_attributes' => ['listID' => 'UN/ECE 4461'], // رمز الإرجاع
                    '_value' => '10', // القيمة الثابتة
                ],
                'cbc:InstructionNote' => $invoiceData->{'instruction-note'}, // سبب الإرجاع
            ],
            'cac:AllowanceCharge' => [ // تفاصيل الخصم
                'cbc:ChargeIndicator' => 'false', // يشير إلى أن هذا خصم وليس تكلفة
                'cbc:AllowanceChargeReason' => 'discount', // سبب الخصم
                'cbc:Amount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // عملة الخصم
                    '_value' => sprintf("%.9f",  $invoiceData->amount), // مجموع الخصم
                ],
            ],
            'cac:TaxTotal' => [ // تفاصيل الضرائب
                'cbc:TaxAmount' => [
                    '_attributes' => ['currencyID' => 'JOD'], // عملة الضريبة
                    '_value' => $invoiceData->taxamount, // مجموع قيم الضريبة المراد إرجاعها
                ],
                'cac:TaxSubtotal' => $invoiceData->items->map(function ($item) {

                    // حساب القيمة الإجمالية للخط (السعر * الكمية - الخصم)
                    $lineExtensionAmount = ($item->priceamount * $item->invoicedquantity) - $item->amount;

                    // النسبة الافتراضية للضريبة (الضريبة القياسية)
                    $taxCategory = 'S';  // الفئة الافتراضية هي "S" للضريبة القياسية
                    $taxPercent = $item->percent * 100;  // النسبة الافتراضية للضريبة (مثال: 0.16 -> 16%)

                    // تطبيق النسب المحددة
                    $validTaxPercentages = [0, 1, 2, 3, 4, 5, 7, 8, 10, 16]; // النسب المتاحة
                    $roundedTaxPercent = round($item->percent * 100); // تقريب النسبة إلى أقرب عدد صحيح

                    // تحقق مما إذا كانت النسبة المدخلة موجودة في النسب المعتمدة
                    if (in_array($roundedTaxPercent, $validTaxPercentages)) {
                        $taxPercent = $roundedTaxPercent;
                    } else {
                        // إذا كانت النسبة غير معتمدة، قم بتعيينها إلى 0% أو أي قيمة أخرى مناسبة
                        $taxPercent = 0;
                        $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                    }

                    // خاصة للحالات التي فيها النسبة 0.000 أو قريبة منها
                    if ($item->taxamount == 0 || $taxPercent == 0) {
                        $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                        $taxPercent = 0;     // تعيين النسبة إلى 0%
                    }

                    return [
                        'cbc:TaxableAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // عملة المبلغ الخاضع للضريبة
                            '_value' => sprintf("%.9f", $item->lineextensionamount), // المبلغ الخاضع للضريبة
                        ],
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'], // عملة الضريبة
                            '_value' => sprintf("%.9f", $item->taxamount), // قيمة الضريبة
                        ],
                        'cac:TaxCategory' => [
                            'cbc:ID' => [
                                '_attributes' => [
                                    'schemeAgencyID' => '6',
                                    'schemeID' => 'UN/ECE 5305',
                                ],
                                '_value' => $taxCategory // الفئة الضريبية
                            ],
                            'cbc:Percent' => $item->percent, // نسبة الضريبة
                            'cac:TaxScheme' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5153',
                                    ],
                                    '_value' => 'VAT', // نوع الضريبة
                                ],
                            ],
                        ],
                    ];
                })->toArray(), // Map through items to generate tax details
            ],
            'cac:LegalMonetaryTotal' => [
                'cbc:TaxExclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ قبل الضريبة
                    '_value' => sprintf("%.9f", $invoiceData->taxexclusiveamount), // اجمالي الفاتورة قبل الخصم
                ],
                'cbc:TaxInclusiveAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ شامل الضريبة
                    '_value' => sprintf("%.9f", $invoiceData->taxinclusiveamount), // اجمالي الفاتورة
                ],
                'cbc:AllowanceTotalAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة مجموع الخصم
                    '_value' => sprintf("%.9f", $invoiceData->amount), // مجموع قيمة الخصم
                ],
                'cbc:PrepaidAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة الدفع المسبق
                    '_value' => '0', // الدفع المسبق ثابت هنا 0
                ],
                'cbc:PayableAmount' => [
                    '_attributes' => ['currencyID' => 'JO'], // عملة المبلغ المستحق
                    '_value' => sprintf("%.9f",$invoiceData->taxexclusiveamount - $invoiceData->amount + $invoiceData->taxamount), // اجمالي الفاتورة بعد الخصم
                ],
            ],
            'cac:InvoiceLine' => $invoiceData->items->map(function ($item) {
                // حساب القيمة الإجمالية للخط (السعر * الكمية - الخصم)
                $lineExtensionAmount = ($item->priceamount * $item->invoicedquantity) - $item->amount;

                // النسبة الافتراضية للضريبة (الضريبة القياسية)
                $taxCategory = 'S';  // الفئة الافتراضية هي "S" للضريبة القياسية
                $taxPercent = $item->percent * 100;  // النسبة الافتراضية للضريبة (مثال: 0.16 -> 16%)

                // تطبيق النسب المحددة
                $validTaxPercentages = [0, 1, 2, 3, 4, 5, 7, 8, 10, 16]; // النسب المتاحة
                $roundedTaxPercent = round($item->percent * 100); // تقريب النسبة إلى أقرب عدد صحيح

                // تحقق مما إذا كانت النسبة المدخلة موجودة في النسب المعتمدة
                if (in_array($roundedTaxPercent, $validTaxPercentages)) {
                    $taxPercent = $roundedTaxPercent;
                } else {
                    // إذا كانت النسبة غير معتمدة، قم بتعيينها إلى 0% أو أي قيمة أخرى مناسبة
                    $taxPercent = 0;
                    $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                }

                // خاصة للحالات التي فيها النسبة 0.000 أو قريبة منها
                if ($item->taxamount == 0 || $taxPercent == 0) {
                    $taxCategory = 'O'; // فئة ضريبة "O" تعني لا ضريبة
                    $taxPercent = 0;     // تعيين النسبة إلى 0%
                }

                return [
                    'cbc:ID' => $item->linenu,
                    'cbc:InvoicedQuantity' => [
                        '_attributes' => ['unitCode' => 'PCE'],
                        '_value' => number_format($item->invoicedquantity, 0, '.', ''),
                    ],
                    'cbc:LineExtensionAmount' => [
                        '_attributes' => ['currencyID' => 'JOD'],
                        '_value' => sprintf("%.9f",$item->lineextensionamount),  // Corrected LineExtensionAmount calculation
                    ],
                    'cac:TaxTotal' => [
                        'cbc:TaxAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f",$item->taxamount),  // Tax value
                        ],
                        'cbc:RoundingAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf("%.9f",$item->roundingamount),  // Rounding amount, if any
                        ],
                        'cac:TaxSubtotal' => [
                            'cbc:TaxAmount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f",$item->taxamount),  // Tax value (repeated for sub-total)
                            ],
                            'cac:TaxCategory' => [
                                'cbc:ID' => [
                                    '_attributes' => [
                                        'schemeAgencyID' => '6',
                                        'schemeID' => 'UN/ECE 5305',
                                    ],
                                    '_value' => $taxCategory,  // Tax category ("O" for 0% or "S" for standard)
                                ],
                                'cbc:Percent' => $item->percent,  // Tax percentage (e.g., 16% or 0% based on the condition)
                                'cac:TaxScheme' => [
                                    'cbc:ID' => [
                                        '_attributes' => [
                                            'schemeAgencyID' => '6',
                                            'schemeID' => 'UN/ECE 5153',
                                        ],
                                        '_value' => 'VAT',  // VAT tax scheme
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'cac:Item' => [
                        'cbc:Name' => $item->itemname,  // Item description
                    ],
                    'cac:Price' => [
                        'cbc:PriceAmount' => [
                            '_attributes' => ['currencyID' => 'JOD'],
                            '_value' => sprintf( "%.9f",$item->priceamount),  // Unit price before tax
                        ],
                        'cac:AllowanceCharge' => [
                            'cbc:ChargeIndicator' => 'false',  // For discount (set to false for discount)
                            'cbc:AllowanceChargeReason' => 'DISCOUNT',  // Reason for the charge (discount)
                            'cbc:Amount' => [
                                '_attributes' => ['currencyID' => 'JOD'],
                                '_value' => sprintf("%.9f", $item->amount),  // Discount amount
                            ],
                        ],
                    ],
                ];
            })->toArray(),
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
            '1.0',
            []
        );
    }

    protected function generateInvoiceNumber()
    {
        // Get the last invoice number from the database (assume the table name is invoices)
        $lastInvoice = DB::connection('odbc')->table('fawtara-01')->count();

        // If there's no invoice, start the counter at 1
        $nextInvoiceNumber = $lastInvoice ? $lastInvoice + 1 : 1;

        // Return the updated UUID
        return $nextInvoiceNumber;
    }

    protected function getCompanyInfo()
    {
        $companyInfo = DB::connection('odbc')->select("SELECT * FROM [fawtara-00]");

        [$companyInfo] = $companyInfo;

        return $companyInfo;
    }
}
