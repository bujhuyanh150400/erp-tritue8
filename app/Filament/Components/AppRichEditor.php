<?php

namespace App\Filament\Components;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\ToolbarButtonGroup;

class AppRichEditor extends RichEditor
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            // 1. Cấu hình thanh công cụ (Giữ lại các nút cần thiết, loại bỏ các nút rườm rà)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h2', 'h3'],
                [ToolbarButtonGroup::make('Heading', ['h1', 'h2', 'h3'])->icon('fi-o-heading')],
                [ToolbarButtonGroup::make('Alignment', ['alignStart', 'alignCenter', 'alignEnd', 'alignJustify'])],
                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
            ])
            ->floatingToolbars([
                'paragraph' => [
                    'bold', 'italic', 'underline', 'strike', 'subscript', 'superscript',
                ],
                'heading' => [
                    'h1', 'h2', 'h3',
                ],
                'table' => [
                    'tableAddColumnBefore', 'tableAddColumnAfter', 'tableDeleteColumn',
                    'tableAddRowBefore', 'tableAddRowAfter', 'tableDeleteRow',
                    'tableMergeCells', 'tableSplitCell',
                    'tableToggleHeaderRow', 'tableToggleHeaderCell',
                    'tableDelete',
                ],
            ])
            ->extraInputAttributes([
                'style' => 'min-height: 300px;',
            ])
            ->fileAttachmentsDisk('public') // Lưu vào disk public (storage/app/public)
            ->fileAttachmentsDirectory('rich-editor/images') // Thư mục lưu ảnh
            ->fileAttachmentsVisibility('public') // Ảnh có thể truy cập công khai
            ->placeholder('Nhập nội dung tại đây...')
            ->columnSpanFull(); // Thường rich editor sẽ chiếm toàn bộ chiều ngang
    }
}
