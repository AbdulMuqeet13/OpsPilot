<?php

namespace App\Services\Leads;

/**
 * Upload limits for CSV imports. Demo mode is far stricter to keep costs and abuse in check.
 */
class LeadImportLimits
{
    public function maxKilobytes(): int
    {
        return config('demo.enabled') ? (int) config('demo.uploads.max_kilobytes') : 5120;
    }

    public function maxRows(): int
    {
        return config('demo.enabled') ? (int) config('demo.uploads.max_rows') : 2000;
    }

    /**
     * Total imports one user may upload, or null for no limit.
     */
    public function maxFilesPerUser(): ?int
    {
        return config('demo.enabled') ? (int) config('demo.uploads.max_files_per_user') : null;
    }
}
