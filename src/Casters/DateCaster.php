<?php
declare(strict_types=1);

namespace ImageSpark\SpreadsheetParser\Casters;

use Illuminate\Support\Carbon;
use ImageSpark\SpreadsheetParser\Contracts\CasterInterface;

class DateCaster implements CasterInterface
{
    /**
     * Undocumented function
     *
     * @param string $value
     * @param string|null $format
     * @return Carbon
     */
    public function cast(string $value, ?string $format = null)
    {
        return Carbon::parse($value);
    }
}