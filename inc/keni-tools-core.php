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

// =====================================================
// APIキー未設定時の管理者宛てお知らせ (Admin Notices)
// =====================================================
add_action('admin_notices', function () {
    // 管理権限がない、またはすでにキーが登録されている場合は表示しない
    if (! current_user_can('manage_options') || ! empty(get_option('gti_syn_tools_api_key', ''))) {
        return;
    }

    $settings_url = admin_url('admin.php?page=gti-keni-tools');
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>【重要・GTI SYN管理ツール】アップデート移行とAPIキー設定のお願い</strong><br>
        本バージョン（Ver 1.7.0）はGitHub経由での最終アップデートです。2026年10月中にGitHub配信は終了します。<br>
        今後も新バージョンの自動アップデートを受け取るために、お手数ですが <a href="<?php echo esc_url($settings_url); ?>">こちら（基本設定）</a> から「ウェブサポーターズ APIキー」の設定を行ってください。</p>
    </div>
    <?php
});

function gti_keni_get_modules()
{
    $modules = $GLOBALS['gti_keni_tools'] ?? [];
    
    // priority（優先度）の昇順でソート（指定がない場合はデフォルト 100）
    uasort($modules, function ($a, $b) {
        $pa = isset($a['priority']) ? intval($a['priority']) : 100;
        $pb = isset($b['priority']) ? intval($b['priority']) : 100;
        return $pa <=> $pb;
    });

    return $modules;
}

function gti_keni_tools_dashboard()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    // APIキーの保存処理
    if (
        isset($_POST['gti_syn_core_settings_nonce']) &&
        wp_verify_nonce($_POST['gti_syn_core_settings_nonce'], 'gti_syn_core_settings_save')
    ) {
        $api_key = isset($_POST['gti_syn_tools_api_key']) ? sanitize_text_field(wp_unslash($_POST['gti_syn_tools_api_key'])) : '';
        update_option('gti_syn_tools_api_key', $api_key);
        gti_keni_notice('基本設定を保存しました。', 'success');
    }

    $api_key = get_option('gti_syn_tools_api_key', '');

    echo '<div class="wrap"><h1>GTI SYN管理ツール</h1>';
    echo '<p>賢威テーマからSYNテーマへ移行・管理する際の補助ツール群です。</p>';

    // APIキー未登録時の警告表示
    if (empty($api_key)) {
        ?>
        <div class="notice notice-warning inline" style="max-width: 800px; margin-top: 20px; border-left-color: #ffb900;">
            <p><strong>【重要】アップデート配信元 移行のお願い</strong><br>
            本バージョン（Ver 1.7.0）は、GitHub経由で配信される最後のバージョンとなります（2026年10月中にGitHubの配信元は閉鎖されます）。<br>
            今後も安全に自動アップデートを受け取るために、以下の「基本設定」にて<strong>「ウェブサポーターズ APIキー」</strong>を入力し、保存をお願いいたします。</p>
        </div>
        <?php
    }

    // 基本設定のカード枠
    ?>
    <div class="card" style="max-width: 800px; margin-top: 20px; padding: 15px 20px;">
        <h2>🔑 基本設定</h2>
        <form method="post" action="">
            <?php wp_nonce_field('gti_syn_core_settings_save', 'gti_syn_core_settings_nonce'); ?>
            <table class="form-table" role="presentation" style="margin-top: 0;">
                <tr>
                    <th scope="row" style="width: 200px; padding: 10px 0;"><label for="gti_syn_tools_api_key">ウェブサポーターズ APIキー</label></th>
                    <td style="padding: 10px 0;">
                        <input type="text" id="gti_syn_tools_api_key" name="gti_syn_tools_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                        <p class="description" style="margin-top: 5px;">自社サーバーからのプラグインアップデート確認・ダウンロードに使用するライセンス/APIキーを入力してください。</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('基本設定を保存', 'primary', 'submit', false); ?>
        </form>
    </div>

    <div style="margin-top: 30px;">
        <h2>🛠️ 機能別ツール一覧</h2>
        <p>各機能の設定・移行ツールは以下から選択してください。</p>
        <ul style="list-style:disc;margin-left:2em;">
        <?php
        foreach (gti_keni_get_modules() as $slug => $tool) {
            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url(admin_url('admin.php?page=' . $slug)),
                esc_html($tool['title'])
            );
        }
        ?>
        </ul>
    </div>
    </div>
    <?php
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
