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
                ['table'],
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
            // 3. Tối ưu trải nghiệm
            ->placeholder('Nhập nội dung tại đây...')
            ->columnSpanFull(); // Thường rich editor sẽ chiếm toàn bộ chiều ngang
    }
}
