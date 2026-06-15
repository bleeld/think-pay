<?php
declare(strict_types=1);

namespace bleeld\pay\Security;

/**
 * 防重放攻击保护
 * 通过nonce和时间戳防止请求被重放
 */
class ReplayAttackGuard
{
    /**
     * 已处理的nonce记录（使用缓存存储）
     */
    protected static array $processedNonces = [];

    /**
     * 检查请求是否为重放
     * 
     * @param string $nonce 随机字符串
     * @param int $timestamp 时间戳
     * @param int $tolerance 时间容差（秒）
     * @return bool true=合法请求，false=重放攻击
     */
    public static function check(string $nonce, int $timestamp, int $tolerance = 300): bool
    {
        // 1. 检查时间戳是否在允许范围内
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        // 2. 检查nonce是否已处理
        if (isset(self::$processedNonces[$nonce])) {
            return false;
        }

        return true;
    }

    /**
     * 记录已处理的nonce
     * 
     * @param string $nonce 随机字符串
     */
    public static function record(string $nonce): void
    {
        self::$processedNonces[$nonce] = time();

        // 定期清理过期记录
        if (count(self::$processedNonces) > 10000) {
            self::cleanup();
        }
    }

    /**
     * 清理过期记录
     * 
     * @param int $expireTime 过期时间（秒）
     */
    public static function cleanup(int $expireTime = 600): void
    {
        $now = time();
        foreach (self::$processedNonces as $nonce => $timestamp) {
            if ($now - $timestamp > $expireTime) {
                unset(self::$processedNonces[$nonce]);
            }
        }
    }

    /**
     * 清空所有记录
     */
    public static function clear(): void
    {
        self::$processedNonces = [];
    }
}
