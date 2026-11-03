<?php

namespace App\Support;

final class LeadFormOptions
{
    /**
     * @return list<string>
     */
    public static function companySizes(): array
    {
        return ['1-10', '11-50', '51-200', '201-1000', '1000+'];
    }

    /**
     * @return list<string>
     */
    public static function budgets(): array
    {
        return ['Under $5k', '$5k-$25k', '$25k-$50k', '$50k+', 'Not sure yet'];
    }
}
