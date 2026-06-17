<?php

namespace App\Enums;

enum SalaryStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
}
