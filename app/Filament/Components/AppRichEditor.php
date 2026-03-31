<?php

namespace App\Filament\Components;

use Filament\Forms\Components\RichEditor;

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
                ['alignStart', 'alignCenter', 'alignEnd'],
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
            // 2. Cấu hình Upload Ảnh
            ->fileAttachmentsDisk('public') // Lưu vào disk public (storage/app/public)
            ->fileAttachmentsDirectory('rich-editor/images') // Thư mục lưu ảnh
            ->fileAttachmentsVisibility('public') // Đảm bảo ảnh có thể xem được trên web
            ->extraInputAttributes([
                'style' => 'min-height: 300px;',
            ])
            // 3. Tối ưu trải nghiệm
            ->placeholder('Nhập nội dung tại đây...')
            ->columnSpanFull(); // Thường rich editor sẽ chiếm toàn bộ chiều ngang
    }
}
