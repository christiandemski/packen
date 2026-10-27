<?php

namespace App\Enums;

enum AuthSource: string
{
    case BearId = 'bearid';
    case Local = 'local';
}
