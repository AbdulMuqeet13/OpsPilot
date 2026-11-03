<?php

namespace App\Enums;

enum LeadImportStatus: string
{
    case Uploaded = 'uploaded';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Waiting for column mapping',
            self::Queued => 'Queued',
            self::Processing => 'Importing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function isInProgress(): bool
    {
        return $this === self::Queued || $this === self::Processing;
    }
}
