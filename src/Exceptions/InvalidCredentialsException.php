<?php

declare(strict_types=1);

namespace Andydefer\FcmNotifications\Exceptions;

use Exception;

/**
 * Exception thrown when FCM credentials are not properly configured.
 *
 * This exception is thrown when the credentials path is null or empty,
 * when the credentials file doesn't exist at the specified path,
 * when the file is not readable, or when the file contains invalid JSON.
 * It provides specific factory methods for different error scenarios
 * to help developers quickly identify and fix configuration issues.
 *
 * @package Andydefer\FcmNotifications\Exceptions
 */
class InvalidCredentialsException extends Exception
{
    /**
     * Create a new instance for missing credentials configuration.
     *
     * This is thrown when the credentials path is null or empty string,
     * indicating that the FCM configuration has not been properly set up.
     *
     * @return self
     */
    public static function missingConfiguration(): self
    {
        return new self(
            'FCM credentials path is not configured. Please set fcm.credentials in your config file.'
        );
    }

    /**
     * Create a new instance for missing credentials file.
     *
     * This is thrown when the configured path points to a file that does not exist.
     *
     * @param string $path The path that was checked
     * @return self
     */
    public static function fileNotFound(string $path): self
    {
        return new self(
            sprintf('FCM credentials file not found at path: %s', $path)
        );
    }

    /**
     * Create a new instance for unreadable credentials file.
     *
     * This is thrown when the file exists but cannot be read due to permissions.
     *
     * @param string $path The path to the unreadable file
     * @return self
     */
    public static function unreadableFile(string $path): self
    {
        return new self(
            sprintf('FCM credentials file is not readable at: %s', $path)
        );
    }

    /**
     * Create a new instance for invalid JSON file.
     *
     * This is thrown when the file exists and is readable but contains invalid JSON.
     *
     * @param string $path The path to the invalid file
     * @param string $error The JSON error message
     * @return self
     */
    public static function invalidJson(string $path, string $error): self
    {
        return new self(
            sprintf('FCM credentials file contains invalid JSON at %s: %s', $path, $error)
        );
    }

    /**
     * Create a new instance for invalid path type.
     *
     * This is thrown when the credentials path is not a string.
     *
     * @param string $type The actual type received
     * @return self
     */
    public static function invalidPathType(string $type): self
    {
        return new self(
            sprintf('FCM credentials path must be a string, %s given', $type)
        );
    }
}
