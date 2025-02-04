<?php

namespace App\Enums;

enum ActivityType: string
{
    case TYPE_CREATE = 'create';
    case TYPE_PRINT = 'print';
    case TYPE_DESCRIPTION = 'description';
    case TYPE_UPLOAD = 'upload';
    case TYPE_DELETE = 'delete';
    case TYPE_RENAME = 'rename';
    case TYPE_COPY = 'copy';
    case TYPE_EDIT = 'edit';
    case TYPE_TRANSCRIPTION = 'transcription';
    case TYPE_LOGIN = 'login';
    case TYPE_LOGOUT = 'logout';
    case TYPE_ARCHIVE = 'archive';
    case TYPE_RETRIEVAL = 'retrieval';
    case TYPE_MOVE = 'move';
    case TYPE_DOWNLOAD = 'download';
}
