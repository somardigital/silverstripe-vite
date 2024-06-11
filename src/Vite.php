<?php

namespace Somar\Vite;

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\HTML;
use SilverStripe\View\Requirements;

/**
 * Class for requiring app files from a Vite manifest
 *
 * {@link https://vitejs.dev/guide/backend-integration.html}
 */
class Vite implements RequirementsInterface
{
    use Injectable;

    private static $dependencies = [
        'manifestProvider' => '%$' . ManifestProvider::class,
        'resourceURLGenerator' => '%$' . ResourceURLGenerator::class,
    ];

    public ManifestProvider $manifestProvider;

    public ResourceURLGenerator $resourceURLGenerator;

    /**
     * URL to the Vite HMR dev server
     */
    private ?string $devServerUrl;

    /**
     * URL to check if the dev server is running. This defaults to the same as
     * the server URL but can be defined separately if running in a docker setup
     */
    private ?string $devServerCheckUrl;

    /**
     * Whether the Vite dev server config has been initialized
     */
    private bool $devServerInit = false;

    /**
     * Whether the React refresh runtime has been initialized
     */
    private bool $reactRefreshRuntimeInit = false;

    /**
     * Whether the Vite dev server is running
     */
    private bool $isDevServerRunning = false;

    /**
     * Paths to disable nonce for (to prevent files being loaded multiple times)
     */
    private array $disabledNonceFiles = [];

    /**
     * Manually initialise the Vite dev server. This is useful for when you want
     * to insert the react refresh runtime script.
     */
    public static function configDevServer(bool $isReact = false): void
    {
        self::singleton()->initDevServer();

        if ($isReact) {
            self::singleton()->insertReactRefresh();
        }
    }

    /**
     * Register the given JavaScript file as required.
     *
     * This will also recursivly load any imports/css from the chunk as per vite
     * manifest specs
     *
     * @param string $file The javascript file to load, relative to site root
     * @param array $options List of options. Available options include:
     * - 'preload' : Preload the resource (defaults to true)
     * - 'provides' : List of scripts files included in this file
     * - 'async' : Boolean value to set async attribute to script tag
     * - 'defer' : Boolean value to set defer attribute to script tag
     * - 'type' : Override script type= value.
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     */
    public static function javascript(string $file, array $options = []): void
    {
        self::singleton()->requireJavascript($file, $options);
    }

    /**
     * Register the given stylesheet into the list of requirements.
     *
     * @param string $file The CSS file to load, relative to site root
     * @param string $media Comma-separated list of media types to use in the link tag
     *                      (e.g. 'screen,projector')
     * @param array $options List of options. Available options include:
     * - 'preload' : Preload the resource (defaults to true)
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     */
    public static function css(string $file, ?string $media = null, array $options = []): void
    {
        self::singleton()->requireCss($file, $media, $options);
    }

    /**
     * Preload the given file into the head of the document.
     *
     * @param string $file The file to preload, relative to site root
     * @param string $as The type of resource to preload
     *                   (e.g. 'script', 'style', 'font', 'image', 'document')
     * @param string $type The MIME type of the resource
     */
    public static function preload(string $file, ?string $as = null, ?string $type = null): void
    {
        self::singleton()->requirePreload($file, $as, $type);
    }

    /**
     * Resolve resource path from the manifest
     */
    public static function resourcePath(string $resource): ?string
    {
        return static::singleton()
            ->getManifestProvider()
            ->resolvePath($resource);
    }

    /**
     * Resolve resource URL from the manifest
     */
    public static function resourceURL(string $resource): ?string
    {
        return static::singleton()
            ->getManifestProvider()
            ->resolveURL($resource);
    }

    /**
     * Register the given JavaScript file as required.
     *
     * This will also recursivly load any imports/css from the chunk as per vite
     * manifest specs
     *
     * @param string $file The javascript file to load, relative to site root
     * @param array $options List of options. Available options include:
     * - 'preload' : Preload the resource (defaults to true)
     * - 'provides' : List of scripts files included in this file
     * - 'async' : Boolean value to set async attribute to script tag
     * - 'defer' : Boolean value to set defer attribute to script tag
     * - 'type' : Override script type= value.
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     */
    public function requireJavascript(string $file, array $options = []): void
    {
        if ($this->isDevServerRunning()) {
            Requirements::javascript($this->devServerResourceUrl($file), [
                'type' => 'module',
                ...$options,
            ]);
            return;
        }

        $preload = $options['preload'] ?? true;

        $preloads = [];
        $this->addJsFromManifest($file, $options, $preloads);

        if ($preload) {
            $this->insertPreloadTags($preloads);
        }
    }

    /**
     * Register the given stylesheet into the list of requirements.
     *
     * @param string $file The CSS file to load, relative to site root
     * @param string $media Comma-separated list of media types to use in the link tag
     *                      (e.g. 'screen,projector')
     * @param array $options List of options. Available options include:
     * - 'preload' : Preload the resource (defaults to true)
     * - 'integrity' : SubResource Integrity hash
     * - 'crossorigin' : Cross-origin policy for the resource
     */
    public function requireCss(string $file, ?string $media = null, array $options = []): void
    {
        if ($this->isDevServerRunning()) {
            Requirements::css($this->devServerResourceUrl($file), $media, $options);
            return;
        }

        $preload = $options['preload'] ?? true;

        $preloads = [];
        $this->addCssFromManifest($file, $media, $options, $preloads);

        if ($preload) {
            $this->insertPreloadTags($preloads);
        }
    }

    /**
     * Preload the given file into the head of the document.
     *
     * @param string $file The file to preload, relative to site root
     * @param string $as The type of resource to preload
     *                   (e.g. 'script', 'style', 'font', 'image', 'document')
     * @param string $type The MIME type of the resource
     */
    public function requirePreload(string $file, ?string $as = null, ?string $type = null): void
    {
        // Don't do anything if dev server is running, this is only for production
        if ($this->isDevServerRunning()) {
            return;
        }

        $preloads = [];
        $this->preloadFileFromManifest($file, $as, $type, $preloads);
        $this->insertPreloadTags($preloads);
    }

    /**
     * Add a JS file to the list of files to be loaded
     *
     * This will also recursivly load any imports/css from the chunk as per vite
     * manifest specs
     */
    protected function addJsFromManifest(string $file, array $options = [], &$preloads = []): void
    {
        $resource = $this->manifestProvider->resolveResource($file);
        if (!$resource) {
            return;
        }

        $path = $this->manifestProvider->resolvePath($resource);
        if (!$path) {
            return;
        }

        $this->addDisabledNonceFile($file, $path);
        $preloads[$file] = [
            'path' => $path,
            'as' => 'script',
        ];

        foreach ($resource['imports'] ?? [] as $import) {
            $this->addJsFromManifest($import, $preloads);
        }

        foreach ($resource['css'] ?? [] as $css) {
            // The css is not necessarily an entry point so we need to dummy
            // resolve it to get the full path
            $cssPath = $this->manifestProvider->resolvePath([
                'file' => $css,
            ]);
            $this->addDisabledNonceFile($css, $cssPath);
            $preloads[$css] = [
                'path' => $cssPath,
                'as' => 'style',
            ];

            Requirements::css($cssPath);
        }

        Requirements::javascript($path, [
            'type' => 'module',
            ...$options,
        ]);
    }

    /**
     * Add a CSS file to the list of files to be loaded
     */
    protected function addCssFromManifest(
        string $file,
        ?string $media = null,
        array $options = [],
        array &$preloads = []
    ): void {
        $path = $this->manifestProvider->resolvePath($file);

        if (!$path) {
            return;
        }

        $this->addDisabledNonceFile($file, $path);
        $preloads[$file] = [
            'path' => $path,
            'as' => 'style',
        ];

        Requirements::css($path, $media, $options);
    }

    /**
     * Preload a file from the manifest
     */
    protected function preloadFileFromManifest(
        string $file,
        string $as,
        ?string $type = null,
        array &$preloads = []
    ): void {
        $path = $this->manifestProvider->resolvePath($file);

        if (!$path) {
            return;
        }

        $this->disabledNonceFiles[$file] = $path;
        $preloads[$file] = [
            'path' => $path,
            'as' => $as,
            'type' => $type,
        ];
    }

    /**
     * Determine whether the given path is a JS file.
     */
    protected function isJsPath(string $path): bool
    {
        return preg_match('/\.(js|cjs|mjs)$/', $path) === 1;
    }

    /**
     * Insert preload tags into the head for the given files
     */
    protected function insertPreloadTags(array $files): void
    {
        // Add preload tags to head
        foreach (array_values($files) as $file) {
            if (!isset($file['path'])) {
                continue;
            }

            // Resolve full preloads url AFTER disabling nonce paths
            $fullPath = $this->resourceURLGenerator->urlForResource($file['path']);

            if (empty($fullPath)) {
                continue;
            }

            $rel = $this->isJsPath($file['path']) ? 'modulepreload' : 'preload';
            unset($file['path']);

            $tag = HTML::createTag('link', [
                'rel' => $rel,
                'href' => $fullPath,
                'crossorigin' => true,
                ...$file,
            ]);

            Requirements::insertHeadTags($tag);
        }
    }

    /**
     * Generate and insert the React refresh runtime script
     */
    public function insertReactRefresh()
    {
        if (!$this->isDevServerRunning()) {
            return;
        }

        if ($this->reactRefreshRuntimeInit) {
            return;
        }

        Requirements::customScriptWithAttributes(sprintf(
            <<<JAVASCRIPT
                import RefreshRuntime from '%s'
                RefreshRuntime.injectIntoGlobalHook(window)
                window.$RefreshReg$ = () => {}
                window.$RefreshSig$ = () => (type) => type
                window.__vite_plugin_react_preamble_installed__ = true
            JAVASCRIPT,
            $this->devServerResourceUrl('@react-refresh')
        ), [
            'type' => 'module',
        ]);

        $this->reactRefreshRuntimeInit = true;
    }

    /**
     * Initialize the Vite dev server and check if it's running
     */
    protected function initDevServer(): void
    {
        // Allow lazy initialization
        if ($this->devServerInit) {
            return;
        }

        // Set the flag to prevent re-initialization
        $this->devServerInit = true;

        // We don't want to run this in live mode
        if (Director::isLive()) {
            return;
        }

        $this->devServerUrl = rtrim(Environment::getEnv('VITE_SERVER_URL'), '/');
        if (!$this->devServerUrl) {
            return;
        }

        $this->devServerCheckUrl = Environment::getEnv('VITE_SERVER_CHECK_URL') ?: $this->devServerUrl;

        // Check if the dev server is running
        $parsedUrl = parse_url($this->devServerCheckUrl);
        $handle = @fsockopen($parsedUrl['host'], $parsedUrl['port']);
        if ($handle) {
            $this->isDevServerRunning = true;
            fclose($handle);
        }

        if (!$this->isDevServerRunning) {
            return;
        }

        // Add the vite client to the head
        Requirements::javascript($this->devServerResourceUrl('@vite/client'), [
            'type' => 'module'
        ]);
    }

    /**
     * Check whether Vite dev server is actually running
     */
    public function isDevServerRunning(): bool
    {
        // Initialize the dev server
        $this->initDevServer();

        return $this->isDevServerRunning;
    }

    /**
     * URL to the Vite HMR dev server
     */
    public function getDevServerUrl(): string
    {
        return $this->devServerUrl;
    }

    /**
     * URL to check if the dev server is running. This defaults to the same as
     * the server URL but can be defined separately if running in a docker setup
     */
    public function getDevServerCheckUrl(): string
    {
        return $this->devServerCheckUrl;
    }

    /**
     * Get the URL for a resource from the Vite dev server
     */
    public function devServerResourceUrl(string $file): string
    {
        // Initialize the dev server
        $this->initDevServer();

        return "{$this->devServerUrl}/{$file}";
    }

    /**
     * Check whether the given file is disabled for nonce
     */
    public function isDisabledNonceFile(string $file): bool
    {
        return isset($this->disabledNonceFiles[$file]);
    }

    /**
     * Check whether the given path is disabled for nonce
     */
    public function isDisabledNoncePath(string $path): bool
    {
        return in_array($path, $this->disabledNonceFiles);
    }

    /**
     * Add a file to the list of files to disable nonce for
     */
    public function addDisabledNonceFile(string $file, string $path): void
    {
        $this->disabledNonceFiles[$file] = $path;
    }

    /**
     * Remove a file from the list of files to disable nonce for
     */
    public function removeDisabledNonceFile(string $file): void
    {
        unset($this->disabledNonceFiles[$file]);
    }

    /**
     * Get the Vite asset manifest provider
     */
    public function getManifestProvider(): ManifestProvider
    {
        return $this->manifestProvider;
    }
}
