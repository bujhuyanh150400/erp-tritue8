<?php

namespace App\Core\Helpers;

class Snowflake
{
    /**
     * Epoch bắt đầu (mốc thời gian).
     * Bạn có thể giữ mốc của bạn: 1700000000000
     * (Tương đương: Thứ Năm, 15/11/2023, 05:33:20 GMT+0)
     */
    private static int $epoch = 1700000000000;

    /**
     * Số bits cho thời gian (42 bits, dùng được ~139 năm)
     */
    private const TIME_BITS = 42;

    /**
     * Số bits cho phần ngẫu nhiên (21 bits)
     */
    private const RANDOM_BITS = 21;

    /**
     * Giá trị ngẫu nhiên tối đa (2^21 - 1)
     */
    private const MAX_RANDOM = 0x1FFFFF;

    /**
     * Tạo và trả về một ID 63-bit (dạng BIGINT).
     *
     * @return int
     * @throws \Exception
     */
    public static function id(): int
    {
        // 1. Lấy 42 bits thời gian
        // (int) (microtime(true) * 1000) -> Lấy timestamp (ms) hiện tại
        $time = (int) (microtime(true) * 1000) - self::$epoch;

        // 2. Lấy 21 bits ngẫu nhiên
        // Dùng random_int() vì nó an toàn (cryptographically secure) hơn mt_rand()
        $random = random_int(0, self::MAX_RANDOM);

        // 3. Ghép chúng lại
        // ($time << 21) -> Dịch trái 42 bits thời gian, chừa 21 bits trống bên phải
        // | $random     -> Nối 21 bits ngẫu nhiên vào 21 bits trống đó
        $id = ($time << self::RANDOM_BITS) | $random;

        // Đảm bảo ID là số dương (mặc dù với 63 bits thì luôn dương)
        return $id & 0x7FFFFFFFFFFFFFFF;
    }
}

