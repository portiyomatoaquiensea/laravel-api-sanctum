<?php

namespace App\Services;
use Illuminate\Support\Facades\Redis;

class RedisService
{

    /**
     * Get TTL (time left in seconds)
     *
     * @param string $key
     * @return int
     */
    public function ttl(string $key): ?int
    {
        $ttl = Redis::ttl($key);
        if ($ttl === -2) {
            // Key does not exist
            return null;
        }

        if ($ttl === -1) {
            // Key exists but has no expiration
            return null;
        }

        // Convert seconds to minutes (rounded up)
        $remainingMinutes = ceil($ttl / 60);

        return $remainingMinutes;
    }


    public function ttlInSeconds(string $key): ?int
    {
        $ttl = Redis::ttl($key);

        if ($ttl === -2) {
            // Key does not exist
            return null;
        }

        if ($ttl === -1) {
            // Key exists but has no expiration
            return null;
        }

        // Return TTL in seconds
        return $ttl;
    }


    /**
     * Increment the value of the given key by 1.
     *
     * @param string $key
     * @return int
     */
    public function increment($key)
    {
        return Redis::incr($key);
    }

    /**
     * Set the expiration time for the given key.
     *
     * @param string $key
     * @param int $seconds
     * @return bool
     */
    public function expire($key, $seconds)
    {
        return Redis::expire($key, $seconds);
    }

    /**
     * Get the value of the given key.
     *
     * @param string $key
     * @return string|null
     */
    public function get($key)
    {
        return Redis::get($key);
    }

    /**
     * Set a value for the given key with an optional expiration time.
     *
     * @param string $key
     * @param string $value
     * @param int|null $seconds
     * @return bool
     */
    public function set($key, $value, $seconds = null)
    {
        if ($seconds) {
            return Redis::setex($key, $seconds, $value); // Set with expiration
        }

        return Redis::set($key, $value); // Set without expiration
    }

    /**
     * Delete the given key.
     *
     * @param string $key
     * @return int
     */
    public function delete($key)
    {
        return Redis::del($key);
    }

    /**
     * Check if a key exists in Redis.
     *
     * @param string $key
     * @return bool
     */
    public function exists($key)
    {
        return Redis::exists($key);
    }

    public function listByPrefix($prefix)
    {
        $keys = [];
        $iterator = '';

        try {
            do {
                $result = Redis::scan($iterator, 'match', $prefix . '*');
                // Check if the result is valid and merge the found keys
                if (isset($result[1]) && is_array($result[1])) {
                    $keys = array_merge($keys, $result[1]); // Merge the found keys
                }
            } while ($iterator > 0); // Continue scanning until the iterator is 0
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            return []; // Return an empty array in case of an error
        }

        // Filter keys to ensure they match the prefix
        return array_filter($keys, function($key) use ($prefix) {
            return strpos($key, $prefix) === 0; // Ensure the key starts with the prefix
        });
    }

    public function deleteByPrefix($prefix)
    {
        $iterator = null;
        $deletedCount = 0;

        do {
            // Scan for keys matching the prefix
            $keys = Redis::scan($iterator, 'match', $prefix . '*');

            if (!empty($keys)) {
                // Delete the keys found
                $deletedCount += Redis::del(...$keys); // Use splat operator to pass keys as arguments
            }
        } while ($iterator !== 0); // Continue until the iterator returns to 0

        return $deletedCount; // Return the total number of deleted keys
    }

}