<?php

return [
    // Invoice Errors
    'INV_001' => 'Cannot delete invoices. Use cancellation instead',
    'INV_002' => 'Cannot reduce total below paid amount',
    'INV_003' => 'Cannot reactivate cancelled invoice',
    'INV_004' => 'Invoice is outside the allowed edit window',
    'INV_005' => 'Requested quantity not available in stock',
    'INV_006' => 'Invoice must contain at least one item',
    'INV_007' => 'Price must be greater than zero',
    'INV_008' => 'Cannot cancel a paid or partially paid invoice',

    // Collection Errors
    'COL_001' => 'Cannot delete collections. Use cancellation instead',
    'COL_002' => 'Amount must be greater than zero',
    'COL_003' => 'Invalid distribution method',
    'COL_004' => 'Selected invoice does not belong to this customer',
    'COL_005' => 'Total distribution exceeds collection amount',
    'COL_006' => 'Cannot cancel collection allocated to paid invoices',

    // Shipment Errors
    'SHP_001' => 'Shipment is settled and cannot be modified',
    'SHP_002' => 'Cannot add products to settled shipment',
    'SHP_003' => 'Sold quantity exceeds available quantity',
    'SHP_004' => 'Target shipment must be open',
    'SHP_005' => 'Cannot unsettle - carryover quantity was sold',
    'SHP_006' => 'Supplier must be the same in next shipment',
    'SHP_007' => 'Shipment is already settled',
    'SHP_008' => 'Cannot delete shipments',
    'SHP_009' => 'Shipment must contain at least one product',
    'SHP_010' => 'Quantity must be greater than zero',

    // Return Errors
    'RET_001' => 'Cannot delete returns',
    'RET_002' => 'Returned quantity exceeds sold quantity',
    'RET_003' => 'Return must be in the same month',
    'RET_004' => 'Cannot return unsold product',

    // Daily Report Errors
    'DAY_001' => 'This day is closed. Use reopen',
    'DAY_002' => 'Daily report is already closed',
    'DAY_003' => 'Daily report is already open',
    'DAY_004' => 'Must open a daily report before performing operations',
    'DAY_005' => 'Date is outside allowed range',
    'DAY_006' => 'Daily report not found',

    // Authentication Errors
    'AUTH_001' => 'Invalid credentials',
    'AUTH_002' => 'Account is not active',
    'AUTH_003' => 'You do not have permission for this operation',
    'AUTH_004' => 'Verification code expired',
    'AUTH_005' => 'Session expired. Please log in again',

    // System Errors
    'SYS_001' => 'System error occurred. Please try again',
    'SYS_002' => 'Service unavailable',
    'SYS_003' => 'Invalid request',
    'SYS_004' => 'No open working day',

    // Customer/Supplier Errors
    'CUS_001' => 'Cannot delete customer with transactions',
    'CUS_002' => 'Phone number already in use',
    'SUP_001' => 'Cannot delete supplier with shipments',
    'SUP_002' => 'Phone number already in use',

    // Account Errors
    'ACC_001' => 'Insufficient account balance',
    'ACC_002' => 'Invalid account type',

    // Correction Errors
    'COR_001' => 'Correction is not pending approval',
    'COR_002' => 'You cannot approve your own correction',
    'COR_003' => 'Invalid correction type',

    // Inventory Adjustment Errors
    'ADJ_001' => 'Cannot adjust settled shipment inventory',
    'ADJ_002' => 'Quantity cannot be negative',
    'ADJ_003' => 'Cannot reduce quantity below sold amount',
    'ADJ_004' => 'Adjustment is not pending approval',
    'ADJ_005' => 'You cannot approve your own adjustment',

    // Validation Messages
    'VALIDATION_001' => 'Invalid input data',
    'VALIDATION_002' => 'Field is required',
    'VALIDATION_003' => 'Incorrect format',
];
