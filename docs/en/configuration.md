# Configuration

## Environment Variables

You can configure the Vite integration using environment variables. This is useful for setting different configurations for development, testing, and production environments.


**VITE_DEV_SERVER_URL**: Sets the URL to the Vite HMR development server.
  ```
  VITE_SERVER_URL=http://127.0.0.1:5173
  ```

**VITE_DEV_SERVER_CHECK_URL**: Sets the URL to check if the Vite development server is running.  Defaults to `VITE_SERVER_URL` if not set.
  ```
  VITE_DEV_SERVER_CHECK_URL=http://host.docker.internal:5173
  ```


## Manifest Provider

**build_path**: This is the path to the directory where the Vite build artifacts are stored. It must be set in the `ManifestProvider` configuration. Example:
  ```php
  ManifestProvider::config()->set('build_path', 'path/to/build');
  ```
  or
  ```yml
  Somar\Vite\ManifestProvider:
    build_path: 'path/to/build'
  ```
**manifest_path**: This is the path to the manifest file relative to the build path. It defaults to `.vite/manifest.json` but can be customized in the `ManifestProvider` configuration. Example:
  ```php
  ManifestProvider::config()->set('manifest_path', 'custom_manifest.json');
  ```  
  or
  ```yml
  Somar\Vite\ManifestProvider:
    manifest_path: 'custom_manifest.json'
  ```
