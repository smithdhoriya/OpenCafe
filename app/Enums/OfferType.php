<?php

namespace App\Enums;

enum OfferType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
