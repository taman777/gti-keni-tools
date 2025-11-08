<?php
if (! defined('ABSPATH')) exit;

/**
 * GTI 賢威-SYN 管理ツール 基盤
 */

add_action('admin_menu', function () {
    add_menu_page(
        '賢威-SYNツール',
        '賢威-SYNツール',
        'manage_options',
        'gti-keni-tools',
        'gti_keni_tools_dashboard',
        'dashicons-admin-tools',
        81
    );

    foreach (gti_keni_get_modules() as $slug => $tool) {
        add_submenu_page(
            'gti-keni-tools',
            $tool['title'],
            $tool['menu'],
            'manage_options',
            $slug,
            $tool['callback']
        );
    }
});

function gti_keni_get_modules()
{
    return $GLOBALS['gti_keni_tools'] ?? [];
}

function gti_keni_tools_dashboard()
{
    echo '<div class="wrap"><h1>賢威-SYN 管理ツール</h1>';
    echo '<p>賢威テーマからSYNテーマへ移行する際の補助ツール群です。</p>';
    echo '<ul style="list-style:disc;margin-left:2em;">';
    foreach (gti_keni_get_modules() as $slug => $tool) {
        printf(
            '<li><a href="%s">%s</a></li>',
            esc_url(admin_url('admin.php?page=' . $slug)),
            esc_html($tool['title'])
        );
    }
    echo '</ul></div>';
}

function gti_keni_notice($message, $type = 'info')
{
    printf(
        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr($type),
        esc_html($message)
    );
}

function gti_keni_bar_notice($text, $icon = 'yes')
{
    add_action('admin_bar_menu', function ($wp_admin_bar) use ($text, $icon) {
        if (! current_user_can('manage_options')) return;
        $wp_admin_bar->add_node([
            'id'    => 'gti-keni-bar-notice',
            'title' => '<span class="ab-icon dashicons dashicons-' . esc_attr($icon) . '"></span> ' . esc_html($text),
        ]);
    }, 999);

    add_action('admin_footer', function () {
?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const bar = document.getElementById('wp-admin-bar-gti-keni-bar-notice');
                if (bar) {
                    setTimeout(() => bar.style.transition = "opacity 1s", 1000);
                    setTimeout(() => bar.style.opacity = "0", 3000);
                    setTimeout(() => bar.remove(), 4000);
                }
            });
        </script>
<?php
    });
}
