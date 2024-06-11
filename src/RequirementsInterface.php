<?php

namespace Somar\Vite;

interface RequirementsInterface
{
    /**
     * Register the given JavaScript file as required.
     *
     * @param string $file The javascript file to load, relative to site root
     * @param array $options List of options.
     */
    public static function javascript(
        string $file,
        array $options = []
    ): void;

    /**
     * Register the given stylesheet into the list of requirements.
     *
     * @param string $file The CSS file to load, relative to site root
     * @param string $media Comma-separated list of media types to use in the link tag
     *                      (e.g. 'screen,projector')
     * @param array $options List of options
     */
    public static function css(
        string $file,
        ?string $media = null,
        array $options = []
    ): void;

    /**
     * Preload the given file into the head of the document.
     */
    public static function preload(
        string $path,
        ?string $as = null,
        ?string $type = null,
    ): void;

    /**
     * Check whether the given file is disabled for nonce
     */
    public function isDisabledNonceFile(
        string $file
    ): bool;

    /**
     * Check whether the given path is disabled for nonce
     */
    public function isDisabledNoncePath(
        string $path
    ): bool;

    /**
     * Add a file to the list of files to disable nonce for
     */
    public function addDisabledNonceFile(
        string $file,
        string $path
    ): void;

    /**
     * Remove a file from the list of files to disable nonce for
     */
    public function removeDisabledNonceFile(
        string $file
    ): void;
}
