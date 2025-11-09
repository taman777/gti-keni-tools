<?php
if (!defined('ABSPATH')) exit;

/**
 * SYN TOC 自動挿入マネージャー
 * （GTI Keni Tools モジュール）
 */

// --------------------------------------------------
// モジュール登録
// --------------------------------------------------
$GLOBALS['gti_keni_tools']['gti-keni-toc-manager'] = [
    'title'    => 'SYN目次（TOC）設定',
    'menu'     => 'SYN目次設定',
    'callback' => 'gti_keni_toc_manager_page',
];

// --------------------------------------------------
// 管理画面UI
// --------------------------------------------------
function gti_keni_toc_manager_page()
{
    // 設定値を取得
    $options = [
        'enable'   => get_option('gti_synx_toc_enable', true),
        'position' => get_option('gti_synx_toc_position', 'before_h2'),
        'depth'    => get_option('gti_synx_toc_depth', 2),
    ];

    // 保存処理
    if (isset($_POST['gti_synx_toc_nonce']) && wp_verify_nonce($_POST['gti_synx_toc_nonce'], 'gti_synx_toc_save')) {
        $options['enable']   = !empty($_POST['gti_synx_toc_enable']);
        $options['position'] = sanitize_text_field($_POST['gti_synx_toc_position']);
        $options['depth']    = intval($_POST['gti_synx_toc_depth']);

        update_option('gti_synx_toc_enable', $options['enable']);
        update_option('gti_synx_toc_position', $options['position']);
        update_option('gti_synx_toc_depth', $options['depth']);

        gti_keni_notice('SYN目次設定を保存しました。', 'success');
    }

?>
    <div class="wrap">
        <h1>SYN 目次（TOC）設定</h1>
        <form method="post">
            <?php wp_nonce_field('gti_synx_toc_save', 'gti_synx_toc_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">自動表示</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gti_synx_toc_enable" value="1" <?php checked($options['enable']); ?>>
                            目次を自動的に挿入する
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">挿入位置</th>
                    <td>
                        <select name="gti_synx_toc_position">
                            <option value="before_h2" <?php selected($options['position'], 'before_h2'); ?>>最初のH2の前</option>
                            <option value="title" <?php selected($options['position'], 'title'); ?>>タイトル下</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">見出しの深さ</th>
                    <td>
                        <select name="gti_synx_toc_depth">
                            <option value="1" <?php selected($options['depth'], 1); ?>>h2 のみ</option>
                            <option value="2" <?php selected($options['depth'], 2); ?>>h2〜h3 まで</option>
                            <option value="3" <?php selected($options['depth'], 3); ?>>h2〜h4 まで</option>
                        </select>
                    </td>
                </tr>
            </table>

            <?php submit_button('保存する'); ?>
        </form>
    </div>
<?php
}

// --------------------------------------------------
// 自動挿入処理
// --------------------------------------------------
add_filter('the_content', function ($content) {

    $enable   = get_option('gti_synx_toc_enable', true);
    $position = get_option('gti_synx_toc_position', 'before_h2');
    $depth    = get_option('gti_synx_toc_depth', 2);

    // 無効化または手動ショートコード存在時はスキップ
    if (!$enable || strpos($content, '[synx_toc') !== false) {
        return $content;
    }

    // TOC生成
    $toc = do_shortcode(sprintf('[synx_toc depth="%d"]', $depth));

    if ($position === 'title') {
        // 記事タイトル直下
        return $toc . $content;
    } else {
        // 最初のh2直前
        return preg_replace('/(<h2[^>]*>)/i', $toc . '$1', $content, 1);
    }
});
