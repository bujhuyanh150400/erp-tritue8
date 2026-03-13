<?php

namespace App\Core\Requests;

use App\Core\DTOs\FilterDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRequest extends FormRequest
{
    // --- Các giá trị mặc định ---
    protected int $defaultPerPage = 10;

    protected string $defaultSortBy = 'created_at';

    protected string $defaultDirection = 'desc';

    /**
     * @var array Các cột được phép sort.
     *            Class con NÊN override lại thuộc tính này.
     */
    protected array $allowedSorts = ['id', 'created_at'];

    /**
     * @var array Các key được phép filter.
     *            Class con NÊN override lại thuộc tính này.
     */
    protected array $allowedFilters = [];

    /**
     * Mặc định cho phép (class con có thể override nếu cần).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Định nghĩa các rules validation basic cho list request.
     * Class con có thể override để thêm rules cụ thể.
     */
    public function rules(): array
    {
        $rules = [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100', // Giới hạn max để bảo vệ DB
            'sort_by' => ['sometimes', 'string', Rule::in($this->allowedSorts)], // Chỉ cho phép sort các cột trong list
            'direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'filters' => 'sometimes|array',
        ];
        // Tự động thêm rules cơ bản cho các filter được phép
        foreach ($this->allowedFilters as $filterKey) {
            // Rule cơ bản là 'string', class con có thể override
            $rules["filters.{$filterKey}"] = 'sometimes|nullable|string|max:255';
        }

        return $rules;
    }

    /**
     * Lấy các tùy chọn filter đã được validate và gán giá trị mặc định.
     * Đây là logic cốt lõi.
     */
    public function getFilterOptions(): FilterDTO
    {
        // Lấy data đã được validate bởi hàm rules()
        $validated = $this->validated();

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? $this->defaultPerPage);
        $sortBy = $validated['sort_by'] ?? $this->defaultSortBy;
        $direction = $validated['direction'] ?? $this->defaultDirection;

        // Xử lý filter: Chỉ lấy các filter có trong $allowedFilters
        $filters = [];
        if (! empty($validated['filters'])) {
            if (! empty($this->allowedFilters)) {
                foreach ($this->allowedFilters as $key) {
                    // Chỉ lấy key-value nếu nó tồn tại và không null
                    if (isset($validated['filters'][$key])) {
                        $filters[$key] = $validated['filters'][$key];
                    }
                }
            } else {
                $filters = $validated['filters'];
            }
        }

        return new FilterDTO(
            page: $page,
            perPage: $perPage,
            sortBy: $sortBy,
            direction: strtolower($direction) === 'asc' ? 'asc' : 'desc',
            filters: $filters
        );
    }
}
