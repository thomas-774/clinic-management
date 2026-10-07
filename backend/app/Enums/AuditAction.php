<?php

namespace App\Enums;

/**
 * What an audit log row records (NFR-S.4).
 */
enum AuditAction: string
{
    case Viewed = 'viewed';
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Exported = 'exported';
    case Printed = 'printed';
}
