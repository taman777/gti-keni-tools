<?php

/**
 * アイキャッチ互換ポリシー（賢威 → SYN）
 * SYNテーマを改変せず、賢威の「出す/出さない」ポリシーを復元する
 */

if (! defined('ABSPATH')) exit;

// モジュール登録
$GLOBALS['gti_keni_tools']['gti_eyecatch_policy'] = [
    'title'    => 'SYNアイキャッチ互換設定',
    'menu'     => 'アイキャッチ互換設定',
    'callback' => 'gti_eyecatch_policy_page',
];

/*--------------------------------------------------------------
  設定画面
--------------------------------------------------------------*/
function gti_eyecatch_policy_page()
{
    // 保存処理
    if (isset($_POST['gti_synx_eyecatch_policy_enabled'])) {
        update_option(
            'gti_synx_eyecatch_policy_enabled',
            sanitize_text_field($_POST['gti_synx_eyecatch_policy_enabled'])
        );

        echo '<div class="updated"><p>設定を保存しました。</p></div>';
    }

    $enabled = get_option('gti_synx_eyecatch_policy_enabled', '1');
?>
    <div class="wrap">
        <h1>SYNアイキャッチ互換設定（賢威 → SYN）</h1>

        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row">互換ポリシーを有効にする</th>
                    <td>
                        <label>
                            <input type="radio" name="gti_synx_eyecatch_policy_enabled" value="1" <?php checked($enabled, '1'); ?>>
                            有効（賢威の設定を継承する）
                        </label><br>

                        <label>
                            <input type="radio" name="gti_synx_eyecatch_policy_enabled" value="0" <?php checked($enabled, '0'); ?>>
                            無効（SYN標準の挙動のまま）
                        </label>

                        <p class="description">
                            有効にすると、賢威時代の「出す/出さない」「個別設定」や全体設定を復元します。<br>
                            無効にすると、SYNの `_synx_eyecatch` のみで制御されます。
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

/*--------------------------------------------------------------
  賢威 → SYN アイキャッチポリシー本体
  `_synx_eyecatch` に値が無い時だけ賢威の設定を適用
--------------------------------------------------------------*/
add_filter('get_post_metadata', function ($value, $post_id, $meta_key, $single) {

    if ($meta_key !== '_synx_eyecatch' || !$single) {
        return $value;
    }

    // OFFなら一切介入しない
    if (get_option('gti_synx_eyecatch_policy_enabled', '1') !== '1') {
        return $value;
    }

    // ① SYN個別設定（最優先）
    if ($value !== null) {
        return $value;
    }

    // ② 賢威の個別設定
    $keni_post = get_post_meta($post_id, 'keni_thumbnail_disp_post', true);
    if ($keni_post === 'hide') return true;   // 非表示
    if ($keni_post === 'show') return false;  // 表示

    // ③ 賢威の全体設定
    $keni_global = get_option('keni_thumbnail_disp', 'true');

    if ($keni_global === 'false' || $keni_global === '0') {
        return true;  // 全体で非表示
    }

    return false; // 表示
}, 10, 4);


/*--------------------------------------------------------------
  賢威の設定を編集画面に「表示のみ」するメタボックス
--------------------------------------------------------------*/
add_action('add_meta_boxes', function () {

    // OFFなら表示しない
    if (get_option('gti_synx_eyecatch_policy_enabled', '1') !== '1') {
        return;
    }

    add_meta_box(
        'gti_keni_eyecatch_info',
        '賢威8のアイキャッチ設定（読み取り専用）',
        'gti_render_keni_eyecatch_meta',
        ['post', 'page'],
        'side',
        'default'
    );
});


function gti_render_keni_eyecatch_meta($post)
{

    $keni_post = get_post_meta($post->ID, 'keni_thumbnail_disp_post', true);
    $keni_global = get_option('keni_thumbnail_disp', 'true');

    // ラベル整形
    $keni_post_label =
        ($keni_post === '') ? 'デフォルト（全体に従う）' : ($keni_post === 'show' ? '表示' : '非表示');

    $keni_global_label =
        ($keni_global === 'false' || $keni_global === '0')
        ? '非表示' : '表示';

    echo '<p><strong>賢威：記事個別の設定</strong><br>' . esc_html($keni_post_label) . '</p>';
    echo '<p><strong>賢威：全体設定</strong><br>' . esc_html($keni_global_label) . '</p>';
    echo '<p style="font-size:12px;color:#666;">これは賢威8での設定値です（読み取り専用）</p>';
}
