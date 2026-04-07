<?php

namespace App\Repositories;

use App\Core\Repository\BaseRepository;
use App\Models\TuitionInvoiceLog;

class TuitionInvoiceLogRepository extends BaseRepository
{
    public function getModel(): string
    {
        return TuitionInvoiceLog::class;
    }
}
