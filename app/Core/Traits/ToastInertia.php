<?php

namespace App\Core\Traits;

trait ToastInertia
{
    private function flashToast(string $type, string $message, ?string $title = null): self
    {
        session()->flash($type, [
            'message' => $message,
            'title' => $title ?? $this->getDefaultTitle($type),
        ]);

        return $this;
    }

    public function success(string $message, ?string $title = null): self
    {
        return $this->flashToast('success', $message, $title);
    }

    public function error(string $message, ?string $title = null): self
    {
        return $this->flashToast('error', $message, $title);
    }

    public function warning(string $message, ?string $title = null): self
    {
        return $this->flashToast('warning', $message, $title);
    }

    public function info(string $message, ?string $title = null): self
    {
        return $this->flashToast('info', $message, $title);
    }

    private function getDefaultTitle(string $type): string
    {
        return match ($type) {
            'success' => 'Thành công',
            'error' => 'Lỗi hệ thống',
            'warning' => 'Cảnh báo',
            default => 'Thông báo',
        };
    }
}
