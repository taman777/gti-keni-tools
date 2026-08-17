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
    'priority' => 60,
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
 * 連想配列 → ショートコード引数文字列変換用（プラグイン内部用）
 */
function gti_keni_shortcode_atts_to_string($atts)
{
    // functions.php の関数が存在すればそちらを使用
    if (function_exists('shortcode_atts_to_string')) {
        return shortcode_atts_to_string($atts);
    }

    $pairs = [];
    foreach ($atts as $key => $value) {
        $pairs[] = sprintf('%s="%s"', esc_attr($key), esc_attr($value));
    }
    return implode(' ', $pairs);
}

/**
 * 賢威リッチテキストフォーマット（プラグイン内部用）
 */
function gti_keni_richtext_formats($content)
{
    // functions.php の関数が存在すればそちらを使用
    if (function_exists('keni_richtext_formats')) {
        return keni_richtext_formats($content);
    }

    $content = preg_replace('/<br\s*\/?>/i', "\n", $content); // brタグを改行に変換
    $content = wpautop($content); // 段落自動挿入
    $content = preg_replace('/\n+/', "\n", $content); // 連続する改行を1つに
    return $content;
}

/**
 * 共通コンテンツ取得（プラグイン内部用）
 */
function gti_keni_get_common_contents($atts, $comm_post_id = null)
{
    $id = null;
    extract(shortcode_atts(array(
        'id' => $comm_post_id,
    ), $atts));
    $content = get_post($id, "ARRAY_A");

    if (isset($content['post_content']) && $content['post_status'] == "publish") {
        $content_body = apply_filters('keni_cc_content', gti_keni_richtext_formats($content['post_content']), $content['post_content']);
        return do_shortcode($content_body);
    } else {
        return "";
    }
}

/**
 * 機能の初期化（有効時のみ実行）
 */
add_action('init', function () {
    if (get_option('gti_keni_shortcode_compat_enabled', '0') !== '1') {
        return;
    }

    // 旧ショートコード [keni-linkcard] を [blogcard] に置き換えて処理（既存がなければ登録）
    if (!shortcode_exists('keni-linkcard')) {
        add_shortcode('keni-linkcard', function ($atts = [], $content = null) {
            if (shortcode_exists('blogcard')) {
                return do_shortcode('[blogcard ' . gti_keni_shortcode_atts_to_string($atts) . ']' . $content . '[/blogcard]');
            }
            return ''; // blogcard が存在しない場合は空文字
        });
    }

    // 共通コンテンツショートコード（既存がなければ登録）
    if (!shortcode_exists('cc')) {
        // functions.php に既存の関数があればそちらを使用
        if (function_exists('get_keni_common_contents')) {
            add_shortcode('cc', 'get_keni_common_contents');
        } else {
            add_shortcode('cc', 'gti_keni_get_common_contents');
        }
    }

    // blogcard ショートコードの出力をフィルタリングして target 属性を追加
    add_filter('do_shortcode_tag', function ($output, $tag, $attr, $m) {
        if ($tag !== 'blogcard') {
            return $output;
        }

        if (is_array($attr) && isset($attr['target']) && $attr['target']) {
            $target = esc_attr($attr['target']);
            // 既に target 属性がある場合は置換、なければ追加
            if (strpos($output, ' target=') !== false) {
                $output = preg_replace('/ target=["\'](.*?)["\']/', ' target="' . $target . '"', $output, 1);
            } else {
                $output = str_replace('<a ', '<a target="' . $target . '" ', $output);
            }
        }

        return $output;
    }, 10, 4);
}, 999);
