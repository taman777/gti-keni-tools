<?php
if (! defined('ABSPATH')) exit;

/**
 * 賢威ショートコード互換機能
 * 
 * 賢威テーマのショートコードを SYN テーマでも動作させるための互換レイヤー
 * - keni-linkcard を blogcard に変換
 * - cc（共通コンテンツ）ショートコード対応
 */

$GLOBALS['gti_keni_tools']['gti-keni-shortcode-compat'] = [
    'title' => '賢威ショートコード互換',
    'menu'  => 'ショートコード互換',
    'callback' => 'gti_keni_shortcode_compat_page',
];

/**
 * 設定画面
 */
function gti_keni_shortcode_compat_page()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    // 設定保存処理
    if (
        isset($_POST['gti_keni_shortcode_compat_nonce']) &&
        wp_verify_nonce($_POST['gti_keni_shortcode_compat_nonce'], 'gti_keni_shortcode_compat_save')
    ) {
        $enabled = isset($_POST['gti_keni_shortcode_compat_enabled']) ? '1' : '0';
        update_option('gti_keni_shortcode_compat_enabled', $enabled);
        gti_keni_notice('設定を保存しました。', 'success');
    }

    $enabled = get_option('gti_keni_shortcode_compat_enabled', '0');

?>
    <div class="wrap">
        <h1>賢威ショートコード互換設定</h1>
        <p>賢威テーマのショートコードを SYN テーマでも利用可能にします。</p>

        <form method="post">
            <?php wp_nonce_field('gti_keni_shortcode_compat_save', 'gti_keni_shortcode_compat_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">機能を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                name="gti_keni_shortcode_compat_enabled"
                                value="1"
                                <?php checked($enabled, '1'); ?>>
                            賢威ショートコード互換機能を有効にする
                        </label>
                    </td>
                </tr>

            </table>

            <h2>提供される機能</h2>
            <ul style="list-style:disc;margin-left:2em;">
                <li><strong>[keni-linkcard]</strong> → <strong>[blogcard]</strong> に自動変換</li>
                <li><strong>[cc id="123"]</strong> 共通コンテンツショートコード</li>
            </ul>

            <p class="submit">
                <input type="submit" class="button button-primary" value="設定を保存">
            </p>
        </form>
    </div>
<?php
}

/**
 * 機能の初期化（有効時のみ実行）
 */
add_action('init', function () {
    if (get_option('gti_keni_shortcode_compat_enabled', '0') !== '1') {
        return;
    }

    // 旧ショートコード [keni-linkcard] を [blogcard] に置き換えて処理
    add_shortcode('keni-linkcard', function ($atts = [], $content = null) {
        if (shortcode_exists('blogcard')) {
            return do_shortcode('[blogcard ' . shortcode_atts_to_string($atts) . ']' . $content . '[/blogcard]');
        }
        return ''; // blogcard が存在しない場合は空文字
    });

    // 共通コンテンツショートコード
    add_shortcode('cc', 'get_keni_common_contents');
});

/**
 * 連想配列 → ショートコード引数文字列変換用
 */
function shortcode_atts_to_string($atts)
{
    $pairs = [];
    foreach ($atts as $key => $value) {
        $pairs[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
    }
    return implode(' ', $pairs);
}

/**
 * 共通コンテンツ取得
 */
function get_keni_common_contents($atts, $comm_post_id = null)
{
    $id = null;
    extract(shortcode_atts(array(
        'id' => $comm_post_id,
    ), $atts));
    $content = get_post($id, "ARRAY_A");

    if (isset($content['post_content']) && $content['post_status'] == "publish") {
        $content_body = apply_filters('keni_cc_content', keni_richtext_formats($content['post_content']), $content['post_content']);
        return do_shortcode($content_body);
    } else {
        return "";
    }
}

/**
 * 賢威リッチテキストフォーマット
 */
if (!function_exists('keni_richtext_formats')) {
    function keni_richtext_formats($content)
    {
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content); // brタグを改行に変換
        $content = wpautop($content); // 段落自動挿入
        $content = preg_replace('/\n+/', "\n", $content); // 連続する改行を1つに
        return $content;
    }
}
