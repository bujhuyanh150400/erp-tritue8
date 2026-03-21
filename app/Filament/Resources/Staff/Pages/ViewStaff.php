<?php


namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStaff extends ViewRecord
{
    protected static string $resource = StaffResource::class;

    public function getTitle(): string
    {
        return 'Chi tiết nhân viên';
    }
}
