<?php

return [
    'po_number_format' => 'PO-{YYYY}-{MM}-{SEQUENCE}',
    'rfq_number_format' => 'RFQ-{YYYY}-{MM}-{SEQUENCE}',
    'default_currency' => 'USD',
    'multi_currency' => true,
    'tax_calculator' => 'standard', // or 'vat'
    'approval_required' => true,
    'min_po_amount_for_approval' => 1000,
    'inventory_sync' => true,
    'accounting_sync' => true,
];
