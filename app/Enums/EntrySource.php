<?php

namespace App\Enums;

enum EntrySource: string
{
    case Template = 'template';
    case Manual = 'manual';
    case Outfit = 'outfit';
    case GroupItem = 'group_item';
}
