<?php

// Required by cd-lookup.php to not exit early.
define('ABSPATH', '/tmp/');

// WordPress function stubs.
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $args = 1): void {}
}
if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void {}
}
if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args = []): void {}
}
if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return 'test_nonce';
    }
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 24 * 60 * 60);
}
if (!function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['stub_transients'][$key] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['stub_transients'][$key] = $value;
        return true;
    }
}
// WordPress class stubs.
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params;

        public function __construct(string $method = 'POST', string $route = '', array $params = [])
        {
            $this->params = $params;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(
            private mixed $data = null,
            private int $status = 200
        ) {}

        public function get_data(): mixed { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

// HTTP function stubs — defined before LookupDistrict.php is loaded so the
// function_exists guards there skip the real curl-based implementations.
function get_district(string $address): array
{
    $GLOBALS['stub_get_district_calls'] = ($GLOBALS['stub_get_district_calls'] ?? 0) + 1;
    $GLOBALS['stub_get_district_args'] = ['address' => $address];
    if (!empty($GLOBALS['stub_get_district_throws'])) {
        throw new RuntimeException($GLOBALS['stub_get_district_throws']);
    }
    if (!empty($GLOBALS['stub_get_district_throws_invalid_address'])) {
        throw new InvalidAddressException($GLOBALS['stub_get_district_throws_invalid_address']);
    }
    return $GLOBALS['stub_get_district_return'] ?? ['CA', '12'];
}

function fetch_html(string $url): string
{
    $GLOBALS['stub_fetch_html_url'] = $url;
    return file_get_contents(__DIR__ . '/data/12th_congressional_district.html');
}
