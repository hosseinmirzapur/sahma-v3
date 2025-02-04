<?php

namespace App\Enums;

enum FileDisk: string
{
    case LOCAL = 'local';
    case IMAGE = 'image';
    case WORD = 'word';
    case CSV = 'csv';
    case VOICE = 'voice';
    case VIDEO = 'video';
    case PDF = 'pdf';
    case ZIP = 'zip';
    case EXCEL = 'excel';
    case PUBLIC = 'public';
    case S3 = 's3';
}
