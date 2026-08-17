<?php
if (! defined('ABSPATH')) exit;

/**
 * Blogcard拡張機能
 *
 * [blogcard] ショートコードに target 属性を追加する機能
 */

$GLOBALS['gti_keni_tools']['gti-blogcard-expander'] = [
    'title' => 'Blogcard拡張',
    'menu'  => 'Blogcard拡張',
    'callback' => 'gti_blogcard_expander_page',
    'priority' => 20,
];

/**
 * 設定画面
 */
function gti_blogcard_expander_page()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    // 設定保存処理
    if (
        isset($_POST['gti_blogcard_expander_nonce']) &&
        wp_verify_nonce($_POST['gti_blogcard_expander_nonce'], 'gti_blogcard_expander_save')
    ) {
        $enabled = isset($_POST['gti_blogcard_expander_enabled']) ? '1' : '0';
        update_option('gti_blogcard_expander_enabled', $enabled);
        gti_keni_notice('設定を保存しました。', 'success');
    }

    $enabled = get_option('gti_blogcard_expander_enabled', '0');

?>
    <div class="wrap">
        <h1>Blogcard拡張設定</h1>
        <p>SYNテーマの [blogcard] ショートコードに target 属性（例: target="_self"）を指定できるようにします。</p>

        <form method="post">
            <?php wp_nonce_field('gti_blogcard_expander_save', 'gti_blogcard_expander_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">機能を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox"
                                name="gti_blogcard_expander_enabled"
                                value="1"
                                <?php checked($enabled, '1'); ?>>
                            Blogcard拡張機能を有効にする
                        </label>
                    </td>
                </tr>
            </table>

            <h2>使い方</h2>
            <p>ショートコードに <code>target</code> 属性を追加してください。</p>
            <p><code>[blogcard url="https://example.com" target="_self"]</code></p>
            <p>※ 指定しない場合はテーマのデフォルト（通常は <code>_blank</code>）になります。</p>

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
    if (get_option('gti_blogcard_expander_enabled', '0') !== '1') {
        return;
    }

    // blogcard ショートコードの出力をフィルタリングして target 属性を追加
    add_filter('do_shortcode_tag', function ($output, $tag, $attr, $m) {
        if ($tag !== 'blogcard') {
            return $output;
        }

        if (is_array($attr) && isset($attr['target']) && $attr['target']) {
            $target = esc_attr($attr['target']);
            
            // output内に target属性があるかチェック
            if (preg_match('/<a\s+[^>]*target=["\']([^"\']*)["\']/', $output)) {
                // target="..." がある場合は置換
                $output = preg_replace('/(<a\s+[^>]*target=["\'])([^"\']*)(["\'])/', '$1' . $target . '$3', $output, 1);
            } else {
                // target="..." がない場合は挿入 (<a の直後)
                $output = preg_replace('/<a\s+/', '<a target="' . $target . '" ', $output, 1);
            }
        }

        return $output;
    }, 10, 4);
}, 999);
