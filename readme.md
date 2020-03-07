### Use the WordPress transient API as a PSR-16 cache

```shell
composer require lachlanarthur/psr16-wordpress-transients
```

```php
use LachlanArthur\Psr16WordPressTransients\WordPressTransientAdapter

new WordPressTransientAdapter( 'prefix-' );
```

### NOTES

- This cache cannot store `null` or `false`.
  This is because the WP transient API returns `false` for a cache miss, and the PSR-16 spec must return `null` for a cache miss.

- Key length is restricted to [172 characters](https://codex.wordpress.org/Transients_API#Saving_Transients), including the prefix.
