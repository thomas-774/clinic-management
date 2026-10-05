<?php

namespace App\Enums;

/**
 * Payment state of one visit (PR-3). Never stored; computed by PaymentService.
 */
enum PaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Unpaid = 'unpaid';
}
