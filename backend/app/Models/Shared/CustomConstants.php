<?php

namespace App\Models\Shared;

class CustomConstants
{
    /**
     * Response status codes (status.code in every response)
     */
    const RESPONSE_STATUS_SUCCESS = 1;
    const RESPONSE_STATUS_FAILED = 2;
    const RESPONSE_STATUS_PAYLOAD_VALIDATION_FAIL = 3;
    const RESPONSE_STATUS_RECORD_NOT_FOUND = 4;
    const RESPONSE_STATUS_RECORD_EXISTS = 5;
    const RESPONSE_STATUS_UPDATE_FAILED = 6;
    const RESPONSE_STATUS_UNAUTHORIZED = 7;

    /**
     * Response messages
     */
    const RESPONSE_MESSAGE_SUCCESS = 'Success';
    const RESPONSE_MESSAGE_FAILED = 'Failed';
    const RESPONSE_MESSAGE_PAYLOAD_VALIDATION_FAIL = 'Data validation failed';
    const RESPONSE_MESSAGE_RECORD_NOT_FOUND = 'Record not found';
    const RESPONSE_MESSAGE_RECORD_EXISTS = 'Record already exists';
    const RESPONSE_MESSAGE_UPDATED_FAILED = 'Update failed';
    const RESPONSE_MESSAGE_RECORD_CREATED = 'Record created';
    const RESPONSE_MESSAGE_RECORD_UPDATED = 'Record updated';
    const RESPONSE_MESSAGE_RECORD_DELETED = 'Record deleted';
    const RESPONSE_MESSAGE_INVALID_LOGIN_CREDENTIALS = 'Invalid email or password';
    const RESPONSE_MESSAGE_UNAUTHORIZED = 'Unauthenticated. Please log in again.';
    const RESPONSE_MESSAGE_LOGGED_OUT = 'Logged out';
    const RESPONSE_MESSAGE_CATEGORY_IN_USE = 'Category is used by transactions or budgets and cannot be deleted';
    const RESPONSE_MESSAGE_SPLITS_TOTAL_MISMATCH = 'Split amounts must add up to the transaction amount';
    const RESPONSE_MESSAGE_CATEGORY_TYPE_MISMATCH = 'Category type must match the transaction type';

    /**
     * Transaction / category types
     */
    const TYPE_INCOME = 'income';
    const TYPE_EXPENSE = 'expense';
    const TYPES = [self::TYPE_INCOME, self::TYPE_EXPENSE];

    /**
     * Pagination
     */
    const DEFAULT_PAGE_SIZE = 10;
    const MAX_PAGE_SIZE = 100;

    /**
     * Import
     */
    const MAX_IMPORT_ROWS = 500;
}
