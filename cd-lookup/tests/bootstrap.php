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
if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return $GLOBALS['stub_options'][$key] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option(string $key, mixed $value): bool
    {
        $GLOBALS['stub_options'][$key] = $value;
        return true;
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim($value);
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('add_options_page')) {
    function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback): void {}
}
if (!function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = []): void
    {
        $GLOBALS['stub_registered_settings'][$option_name] = $args;
    }
}
if (!function_exists('add_settings_section')) {
    function add_settings_section(string $id, string $title, callable $callback, string $page): void {}
}
if (!function_exists('add_settings_field')) {
    function add_settings_field(string $id, string $title, callable $callback, string $page, string $section = 'default'): void
    {
        $GLOBALS['stub_settings_fields'][$id] = $callback;
    }
}
if (!function_exists('settings_fields')) {
    function settings_fields(string $option_group): void {}
}
if (!function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void {}
}
if (!function_exists('submit_button')) {
    function submit_button(): void {}
}
if (!function_exists('__return_false')) {
    function __return_false(): bool
    {
        return false;
    }
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 24 * 60 * 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 60 * 60);
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

function fetch_members(string $state, string $district, string $api_key): array
{
    $GLOBALS['stub_fetch_members_calls'] = ($GLOBALS['stub_fetch_members_calls'] ?? 0) + 1;
    $GLOBALS['stub_fetch_members_args'] = ['state' => $state, 'district' => $district, 'api_key' => $api_key];
    if (!empty($GLOBALS['stub_fetch_members_throws'])) {
        throw new RuntimeException($GLOBALS['stub_fetch_members_throws']);
    }
    return $GLOBALS['stub_fetch_members_return'] ?? [
        'senators' => [
            [
                'full_name' => 'Jane Senator',
                'role'      => 'Senator',
                'party'     => 'Democratic',
                'phone'     => '(202) 224-0000',
                'website'   => 'https://senator.senate.gov',
                'photo_url' => 'https://www.congress.gov/img/member/s000001_200.jpg',
            ],
        ],
        'representatives' => [
            [
                'full_name' => 'John Representative',
                'role'      => 'Representative',
                'party'     => 'Republican',
                'phone'     => '(202) 225-0000',
                'website'   => 'https://representative.house.gov',
                'photo_url' => 'https://www.congress.gov/img/member/r000001_200.jpg',
            ],
        ],
    ];
}
