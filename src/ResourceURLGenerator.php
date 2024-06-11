<?php

namespace Somar\Vite;

use InvalidArgumentException;
use SilverStripe\Control\SimpleResourceURLGenerator;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Manifest\ModuleResource;

/**
 * Prevent adding the nonce suffix to vite resources. This prevents the same
 * file being loaded multiple times.
 */
class ResourceURLGenerator extends SimpleResourceURLGenerator
{
    use Configurable;

    /**
     * Disable adding the nonce suffix to given resources
     */
    private static array $disabled_nonce_paths = [];

    /**
     * Return the URL for a resource, prefixing with Director::baseURL() and suffixing with a nonce
     *
     * @param string|ModuleResource $relativePath File or directory path relative to BASE_PATH
     * @throws InvalidArgumentException If the resource doesn't exist
     */
    public function urlForResource($relativePath): string
    {
        // Don't override for module resources
        if ($relativePath instanceof ModuleResource) {
            return parent::urlForResource($relativePath);
        }

        // If the path is not disabled, use the default behaviour
        if (
            !in_array($relativePath, $this->config()->get('disabled_nonce_paths')) &&
            !Vite::singleton()->isDisabledNoncePath($relativePath)
        ) {
            return parent::urlForResource($relativePath);
        }

        // Disable nonce
        $nonceStyle = $this->getNonceStyle();
        $this->setNonceStyle(null);

        // Perform main function
        $url = parent::urlForResource($relativePath);

        // Reset style back to previous
        $this->setNonceStyle($nonceStyle);

        return $url;
    }
}
