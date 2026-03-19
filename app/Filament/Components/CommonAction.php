<?php

namespace App\Filament\Components;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;

class CommonAction
{
    /**
     * Tạo action quay lại
     * @param $resource
     * @return Action
     */
    public static function backAction($resource): Action
    {
        return Action::make('back')
            ->label("Quay lại")
            ->color('gray')
            ->url(fn() => $resource::getUrl('index'))
            ->icon(Heroicon::ChevronLeft);
    }

    public static function createAction(string $label = "Tạo mới"): CreateAction
    {
        return CreateAction::make()
            ->color('primary')
            ->icon(Heroicon::Plus)
            ->label($label);
    }
    public static function editAction(string $label = "Chỉnh sửa"): Action
    {
        return EditAction::make()
            ->label($label)
            ->color('primary')
            ->icon(Heroicon::Pencil);
    }

    /**
     * Tạo action xóa
     * @param string $label
     * @return DeleteAction
     */
    public static function deleteAction(string $label = "Xóa"): DeleteAction
    {
        return DeleteAction::make()
            ->label($label)
            ->tooltip("Xóa dữ liệu vĩnh viễn")
            ->icon(Heroicon::Trash)
            ->requiresConfirmation()
            ->modalHeading("Xóa dữ liệu")
            ->modalDescription("Bạn có chắc chắn muốn xóa dữ liệu này không?")
            ->modalSubmitActionLabel("Xóa")
            ->modalCancelActionLabel("Hủy");
    }
}
