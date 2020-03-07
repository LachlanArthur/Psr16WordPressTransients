NOTE: This cache cannot store `null` or `false`.
This is because the WP transient API returns `false` for a cache miss, and the PSR-16 spec returns `null` for a cache miss.
