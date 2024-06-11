# Usage

## JavaScript and CSS Handling
To include a JavaScript or CSS file in your project:
```php
Vite::javascript('path/to/your/script.js');
Vite::css('path/to/your/style.css');
```

## Preloading Resources
To preload resources for performance optimization:
```php
Vite::preload('path/to/resource', 'asType', 'mimeType');
```

## Development Server
To manually initialize the Vite development server and React refresh:
```php
Vite::configDevServer($isReact = true);
```

## Methods Overview
- `javascript(string $file, array $options = [])`: Registers and loads a JavaScript file.
- `css(string $file, ?string $media = null, array $options = [])`: Registers and loads a CSS file.
- `preload(string $file, ?string $as = null, ?string $type = null)`: Preloads a specified resource.
- `resourcePath(string $resource)`: Resolves the path of a resource using the manifest.
- `resourceURL(string $resource)`: Resolves the URL of a resource using the manifest.

## Development Tips
- Use the `isDevServerRunning()` method to conditionally run code only when the Vite server is active.
- Utilize the `getDevServerUrl()` and `getDevServerCheckUrl()` for custom integrations or checks in your development environment.
