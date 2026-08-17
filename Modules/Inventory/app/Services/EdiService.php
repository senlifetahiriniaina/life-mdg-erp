<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Carbon\Carbon;

/**
 * EDI Service — X12 850/856/810 parser & generator
 * Uses simple regex-based parsing (not a full ASN.1 library).
 */
class EdiService
{
    // ─── Parse 850 (Purchase Order) ─────────────────────────────────────

    /**
     * Parse an X12 850 EDI Purchase Order string into internal PO format.
     */
    public function parse850(string $ediContent): array
    {
        $segments = $this->splitSegments($ediContent);

        $po = [
            'type'          => '850',
            'transaction_id' => null,
            'po_number'     => null,
            'po_date'       => null,
            'vendor_id'     => null,
            'buyer_id'      => null,
            'ship_to'       => [],
            'bill_to'       => [],
            'currency'      => 'USD',
            'items'         => [],
            'notes'         => [],
        ];

        $currentItem = null;

        foreach ($segments as $seg) {
            $els = $this->elements($seg);
            if (empty($els)) {
                continue;
            }

            switch ($els[0]) {
                case 'ST':
                    // ST*850*0001
                    $po['transaction_id'] = $els[2] ?? null;
                    break;

                case 'BEG':
                    // BEG*00*SA*PO12345**20240101
                    $po['po_number'] = $els[3] ?? null;
                    $rawDate = $els[5] ?? null;
                    $po['po_date'] = $rawDate ? $this->parseEdiDate($rawDate) : null;
                    break;

                case 'CUR':
                    // CUR*BY*USD
                    $po['currency'] = $els[2] ?? 'USD';
                    break;

                case 'N1':
                    // N1*BT*BUYER NAME  or  N1*ST*SHIP-TO NAME
                    $qualifier = $els[1] ?? '';
                    $name      = $els[2] ?? '';
                    if ($qualifier === 'BT') {
                        $po['bill_to']['name'] = $name;
                        $po['bill_to']['id_code'] = $els[4] ?? null;
                    } elseif ($qualifier === 'ST') {
                        $po['ship_to']['name'] = $name;
                        $po['ship_to']['id_code'] = $els[4] ?? null;
                    } elseif ($qualifier === 'SE' || $qualifier === 'VN') {
                        $po['vendor_id'] = $els[4] ?? $name;
                    }
                    break;

                case 'N3':
                    // N3*123 MAIN ST
                    $lastN1 = $po['_last_n1'] ?? null;
                    if ($lastN1 === 'ST') {
                        $po['ship_to']['address'] = $els[1] ?? '';
                    } elseif ($lastN1 === 'BT') {
                        $po['bill_to']['address'] = $els[1] ?? '';
                    }
                    break;

                case 'N4':
                    // N4*CITY*STATE*ZIP*COUNTRY
                    $lastN1 = $po['_last_n1'] ?? null;
                    if ($lastN1 === 'ST') {
                        $po['ship_to']['city']    = $els[1] ?? '';
                        $po['ship_to']['state']   = $els[2] ?? '';
                        $po['ship_to']['zip']     = $els[3] ?? '';
                        $po['ship_to']['country'] = $els[4] ?? '';
                    }
                    break;

                case 'PO1':
                    // PO1*1*10*EA*25.00**VP*VENDSKU*BP*BUYERSKU
                    if ($currentItem !== null) {
                        $po['items'][] = $currentItem;
                    }
                    $currentItem = [
                        'line_number'  => $els[1] ?? null,
                        'quantity'     => (float) ($els[2] ?? 0),
                        'unit'         => $els[3] ?? 'EA',
                        'unit_price'   => (float) ($els[4] ?? 0),
                        'vendor_sku'   => null,
                        'buyer_sku'    => null,
                        'description'  => null,
                    ];
                    // Parse qualifier-value pairs -- element 5 is the (often
                    // blank) basis-of-price-code, qualifier/value pairs start at 6.
                    $i = 6;
                    while (isset($els[$i], $els[$i + 1])) {
                        $qual  = $els[$i];
                        $value = $els[$i + 1];
                        if ($qual === 'VP') {
                            $currentItem['vendor_sku'] = $value;
                        } elseif ($qual === 'BP' || $qual === 'IN') {
                            $currentItem['buyer_sku'] = $value;
                        }
                        $i += 2;
                    }
                    break;

                case 'PID':
                    // PID*F****Product description
                    if ($currentItem !== null) {
                        $currentItem['description'] = $els[5] ?? null;
                    }
                    break;

                case 'CTT':
                    // CTT*totalLineItems
                    if ($currentItem !== null) {
                        $po['items'][] = $currentItem;
                        $currentItem = null;
                    }
                    $po['total_line_items'] = (int) ($els[1] ?? 0);
                    break;

                case 'SE':
                    if ($currentItem !== null) {
                        $po['items'][] = $currentItem;
                        $currentItem = null;
                    }
                    break;
            }

            // Track last N1 qualifier for N3/N4 context
            if ($els[0] === 'N1') {
                $po['_last_n1'] = $els[1] ?? null;
            }
        }

        unset($po['_last_n1']);

        return $po;
    }

    // ─── Parse 856 (ASN — Advance Ship Notice) ──────────────────────────

    /**
     * Parse an X12 856 EDI ASN into shipment receipt format.
     */
    public function parse856(string $ediContent): array
    {
        $segments = $this->splitSegments($ediContent);

        $asn = [
            'type'            => '856',
            'transaction_id'  => null,
            'shipment_id'     => null,
            'ship_date'       => null,
            'carrier'         => null,
            'tracking_number' => null,
            'ship_from'       => [],
            'ship_to'         => [],
            'packages'        => [],
        ];

        $currentPackage = null;
        $currentItem    = null;

        foreach ($segments as $seg) {
            $els = $this->elements($seg);
            if (empty($els)) {
                continue;
            }

            switch ($els[0]) {
                case 'ST':
                    $asn['transaction_id'] = $els[2] ?? null;
                    break;

                case 'BSN':
                    // BSN*00*SHIPMENT123*20240101*1200
                    $asn['shipment_id'] = $els[2] ?? null;
                    $asn['ship_date']   = isset($els[3]) ? $this->parseEdiDate($els[3]) : null;
                    break;

                case 'DTM':
                    // DTM*011*20240105 — estimated delivery
                    if (($els[1] ?? '') === '011') {
                        $asn['estimated_delivery'] = isset($els[2]) ? $this->parseEdiDate($els[2]) : null;
                    }
                    break;

                case 'HL':
                    // HL*1**S  (shipment), HL*2*1*O (order), HL*3*2*P (pack), HL*4*3*I (item)
                    $hlType = $els[3] ?? '';
                    if ($hlType === 'P') {
                        if ($currentItem !== null && $currentPackage !== null) {
                            $currentPackage['items'][] = $currentItem;
                            $currentItem = null;
                        }
                        if ($currentPackage !== null) {
                            $asn['packages'][] = $currentPackage;
                        }
                        $currentPackage = [
                            'hl_id'    => $els[1] ?? null,
                            'sscc'     => null,
                            'weight'   => null,
                            'items'    => [],
                        ];
                    } elseif ($hlType === 'I') {
                        if ($currentItem !== null && $currentPackage !== null) {
                            $currentPackage['items'][] = $currentItem;
                        }
                        $currentItem = [];
                    }
                    break;

                case 'MAN':
                    // MAN*GM*00012345678901234567 — SSCC
                    if ($currentPackage !== null && ($els[1] ?? '') === 'GM') {
                        $currentPackage['sscc'] = $els[2] ?? null;
                    }
                    break;

                case 'PRF':
                    // PRF*PO12345 — PO reference
                    $asn['po_number'] = $els[1] ?? null;
                    break;

                case 'TD1':
                    // TD1*CTN*1**G*100*LB
                    $asn['package_type'] = $els[1] ?? null;
                    $asn['package_count'] = (int) ($els[2] ?? 1);
                    break;

                case 'TD3':
                    // TD3*MC*CARRIER*TRACKING
                    $asn['carrier']          = $els[2] ?? null;
                    $asn['tracking_number']  = $els[3] ?? null;
                    break;

                case 'N1':
                    $qualifier = $els[1] ?? '';
                    if ($qualifier === 'SF') {
                        $asn['ship_from']['name'] = $els[2] ?? '';
                        $asn['ship_from']['id']   = $els[4] ?? null;
                    } elseif ($qualifier === 'ST') {
                        $asn['ship_to']['name'] = $els[2] ?? '';
                        $asn['ship_to']['id']   = $els[4] ?? null;
                    }
                    $asn['_last_n1'] = $qualifier;
                    break;

                case 'LIN':
                    // LIN**BP*BUYERSKU*VP*VENDORSKU
                    if ($currentItem !== null) {
                        $i = 1;
                        while (isset($els[$i], $els[$i + 1])) {
                            $qual  = $els[$i];
                            $value = $els[$i + 1];
                            if ($qual === 'BP' || $qual === 'IN') {
                                $currentItem['buyer_sku'] = $value;
                            } elseif ($qual === 'VP') {
                                $currentItem['vendor_sku'] = $value;
                            }
                            $i += 2;
                        }
                    }
                    break;

                case 'SN1':
                    // SN1**10*EA — quantity shipped
                    if ($currentItem !== null) {
                        $currentItem['quantity_shipped'] = (float) ($els[2] ?? 0);
                        $currentItem['unit']             = $els[3] ?? 'EA';
                    }
                    break;

                case 'CTT':
                    if ($currentItem !== null && $currentPackage !== null) {
                        $currentPackage['items'][] = $currentItem;
                        $currentItem = null;
                    }
                    if ($currentPackage !== null) {
                        $asn['packages'][] = $currentPackage;
                        $currentPackage = null;
                    }
                    $asn['total_line_items'] = (int) ($els[1] ?? 0);
                    break;
            }
        }

        unset($asn['_last_n1']);

        return $asn;
    }

    // ─── Generate 810 (Invoice) ──────────────────────────────────────────

    /**
     * Generate an X12 810 EDI Invoice string from internal invoice data.
     *
     * Expected $invoiceData keys:
     *   invoice_number, invoice_date, po_number, currency,
     *   sender_id, receiver_id, sender_gs, receiver_gs,
     *   bill_from: {name, address, city, state, zip, country}
     *   bill_to:   {name, address, city, state, zip, country}
     *   items: [{line_number, sku, description, quantity, unit, unit_price, amount}]
     *   total_amount, tax_amount, freight_amount
     */
    public function generate810(array $invoiceData): string
    {
        $now     = Carbon::now();
        $date    = $now->format('Ymd');
        $time    = $now->format('Hi');
        $isaDate = $now->format('ymd');
        $isaTime = $now->format('Hi');

        $senderId   = str_pad($invoiceData['sender_id'] ?? 'WIDEHALO', 15);
        $receiverId = str_pad($invoiceData['receiver_id'] ?? 'PARTNER', 15);
        $ctrlNum    = str_pad((string) rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
        $gsCtrl     = str_pad((string) rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $stCtrl     = '0001';

        $invoiceNumber = $invoiceData['invoice_number'] ?? 'INV-001';
        $invoiceDate   = isset($invoiceData['invoice_date'])
            ? Carbon::parse($invoiceData['invoice_date'])->format('Ymd')
            : $date;
        $poNumber      = $invoiceData['po_number'] ?? '';
        $currency      = $invoiceData['currency'] ?? 'USD';

        $lines  = [];
        $segCnt = 0;

        // ISA — Interchange Control Header
        $lines[] = "ISA*00*          *00*          *ZZ*{$senderId}*ZZ*{$receiverId}*{$isaDate}*{$isaTime}*^*00501*{$ctrlNum}*0*P*>";
        $segCnt++;

        // GS — Functional Group Header
        $senderGs   = $invoiceData['sender_gs'] ?? 'WIDEHALO';
        $receiverGs = $invoiceData['receiver_gs'] ?? 'PARTNER';
        $lines[] = "GS*IN*{$senderGs}*{$receiverGs}*{$date}*{$time}*{$gsCtrl}*X*005010X231A1";
        $segCnt++;

        // ST — Transaction Set Header
        $lines[] = "ST*810*{$stCtrl}";
        $segCnt++;

        // BIG — Beginning Segment for Invoice
        $lines[] = "BIG*{$invoiceDate}*{$invoiceNumber}***{$poNumber}";
        $segCnt++;

        // CUR — Currency
        $lines[] = "CUR*BY*{$currency}";
        $segCnt++;

        // N1 — Bill From
        $billFrom = $invoiceData['bill_from'] ?? [];
        if (!empty($billFrom['name'])) {
            $lines[] = "N1*SF*" . ($billFrom['name'] ?? '') . "*92*" . ($billFrom['id'] ?? $senderGs);
            $segCnt++;
            if (!empty($billFrom['address'])) {
                $lines[] = "N3*" . $billFrom['address'];
                $segCnt++;
            }
            if (!empty($billFrom['city'])) {
                $lines[] = "N4*" . ($billFrom['city'] ?? '') . "*" . ($billFrom['state'] ?? '') . "*" . ($billFrom['zip'] ?? '') . "*" . ($billFrom['country'] ?? '');
                $segCnt++;
            }
        }

        // N1 — Bill To
        $billTo = $invoiceData['bill_to'] ?? [];
        if (!empty($billTo['name'])) {
            $lines[] = "N1*BT*" . ($billTo['name'] ?? '') . "*92*" . ($billTo['id'] ?? $receiverGs);
            $segCnt++;
            if (!empty($billTo['address'])) {
                $lines[] = "N3*" . $billTo['address'];
                $segCnt++;
            }
            if (!empty($billTo['city'])) {
                $lines[] = "N4*" . ($billTo['city'] ?? '') . "*" . ($billTo['state'] ?? '') . "*" . ($billTo['zip'] ?? '') . "*" . ($billTo['country'] ?? '');
                $segCnt++;
            }
        }

        // IT1 — Line Items
        $items       = $invoiceData['items'] ?? [];
        $lineNumbers = [];
        foreach ($items as $item) {
            $lineNum = $item['line_number'] ?? (count($lineNumbers) + 1);
            $lineNumbers[] = $lineNum;
            $qty    = number_format((float) ($item['quantity'] ?? 0), 2, '.', '');
            $price  = number_format((float) ($item['unit_price'] ?? 0), 2, '.', '');
            $unit   = $item['unit'] ?? 'EA';
            $sku    = $item['sku'] ?? '';
            $lines[] = "IT1*{$lineNum}*{$qty}*{$unit}*{$price}**BP*{$sku}";
            $segCnt++;

            // PID — Description
            if (!empty($item['description'])) {
                $desc    = substr((string) $item['description'], 0, 80);
                $lines[] = "PID*F****{$desc}";
                $segCnt++;
            }
        }

        // TDS — Total Dollar Amount
        $totalCents  = (int) round((float) ($invoiceData['total_amount'] ?? 0) * 100);
        $lines[] = "TDS*{$totalCents}";
        $segCnt++;

        // TXI — Tax
        if (!empty($invoiceData['tax_amount'])) {
            $taxCents = (int) round((float) $invoiceData['tax_amount'] * 100);
            $lines[] = "TXI*TX*{$taxCents}";
            $segCnt++;
        }

        // CAD — Freight
        if (!empty($invoiceData['freight_amount'])) {
            $freightCents = (int) round((float) $invoiceData['freight_amount'] * 100);
            $lines[] = "CAD****" . ($invoiceData['carrier'] ?? '') . "*****{$freightCents}";
            $segCnt++;
        }

        // CTT — Transaction Totals
        $lines[] = "CTT*" . count($items);
        $segCnt++;

        // SE — Transaction Set Trailer
        $segCnt += 2; // SE + GE counted after
        $lines[] = "SE*{$segCnt}*{$stCtrl}";

        // GE — Functional Group Trailer
        $lines[] = "GE*1*{$gsCtrl}";

        // IEA — Interchange Control Trailer
        $lines[] = "IEA*1*{$ctrlNum}";

        return implode("\n", $lines);
    }

    // ─── Detect transaction set type ────────────────────────────────────

    /**
     * Detect which X12 transaction set is in the EDI string (850/856/810 etc.)
     */
    public function detectTransactionSet(string $ediContent): ?string
    {
        if (preg_match('/\bST\*(\d{3})\*/', $ediContent, $m)) {
            return $m[1];
        }
        return null;
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    private function splitSegments(string $edi): array
    {
        // Detect element separator from ISA (position 3) and segment terminator
        $segTerminator = "\n";
        if (preg_match('/^ISA.{103}(.)/', str_replace(["\r\n", "\r"], "\n", $edi), $m)) {
            $segTerminator = $m[1] === "\n" ? "\n" : $m[1];
        }

        $segments = array_filter(
            array_map('trim', explode($segTerminator, $edi))
        );

        return array_values($segments);
    }

    private function elements(string $segment): array
    {
        return explode('*', $segment);
    }

    private function parseEdiDate(string $raw): ?string
    {
        try {
            $raw = trim($raw);
            if (strlen($raw) === 8) {
                return Carbon::createFromFormat('Ymd', $raw)->toDateString();
            }
            if (strlen($raw) === 6) {
                return Carbon::createFromFormat('ymd', $raw)->toDateString();
            }
        } catch (\Throwable) {
            // ignore
        }
        return null;
    }
}
