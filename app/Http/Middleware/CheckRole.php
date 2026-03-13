<?php

namespace App\Http\Middleware;

use App\Constants\UserRole;
use App\Core\Traits\HandleApi;
use App\Core\Traits\ToastInertia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    use HandleApi, ToastInertia;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // Kiểm tra đăng nhập
        if (! $user) {
            if ($request->expectsJson()) {
                return $this->sendError(
                    message: 'Unauthenticated.',
                    code: 401
                );
            }

            return redirect()->route('login');

        }

        // Chuyển đổi mảng string (admin, teacher) thành mảng các giá trị Enum tương ứng
        $allowedRoles = [];
        foreach ($roles as $roleName) {
            // Chuyển roleName về đúng định dạng Case (vd: admin -> Admin)
            $caseName = ucfirst(strtolower($roleName));

            // Lấy giá trị từ Enum thông qua tên Case
            // Sử dụng Try/Catch hoặc check tồn tại để tránh lỗi nếu gõ sai tên trong Route
            foreach (UserRole::cases() as $roleEnum) {
                if ($roleEnum->name === $caseName) {
                    $allowedRoles[] = $roleEnum;
                    break;
                }
            }
        }

        // Kiểm tra quyền của User hiện tại
        if (in_array($user->role, $allowedRoles)) {
            return $next($request);
        }
        //  Xử lý phản hồi khi sai quyền
        // Nếu là API, trả về lỗi JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->sendError(
                message: 'Bạn không có quyền thực hiện hành động này.',
                code: 403
            );
        }
        // Nếu là Web, quay lại trang trước kèm thông báo Toast
        $this->error(
            message: 'Bạn không có quyền truy cập vào khu vực này.',
            title: 'Truy cập bị từ chối'
        );

        return redirect()->back();
    }
}
