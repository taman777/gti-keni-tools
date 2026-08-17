<?php
if (! defined('ABSPATH')) exit;

/**
 * GTI SYN メニュー内検索追加モジュール
 * 
 * スマホ用ドロワーメニューおよびPC用グローバルナビゲーションの最後に
 * 自動的に検索フォームを追加する機能を提供します。
 */

// --------------------------------------------------
// モジュール登録
// --------------------------------------------------
$GLOBALS['gti_keni_tools']['gti-syn-menu-search'] = [
    'title'    => 'メニュー内検索設定',
    'menu'     => 'メニュー検索設定',
    'callback' => 'gti_syn_menu_search_page',
    'priority' => 10, // 一番上に表示するための高い優先度
];

// --------------------------------------------------
// 管理画面UI
// --------------------------------------------------
function gti_syn_menu_search_page()
{
    if (! current_user_can('manage_options')) {
        wp_die('権限がありません');
    }

    // 設定保存処理
    if (
        isset($_POST['gti_syn_menu_search_nonce']) &&
        wp_verify_nonce($_POST['gti_syn_menu_search_nonce'], 'gti_syn_menu_search_save')
    ) {
        $pc_enabled = isset($_POST['gti_syn_menu_search_pc_enabled']) ? '1' : '0';
        $sp_enabled = isset($_POST['gti_syn_menu_search_sp_enabled']) ? '1' : '0';
        update_option('gti_syn_menu_search_pc_enabled', $pc_enabled);
        update_option('gti_syn_menu_search_sp_enabled', $sp_enabled);
        gti_keni_notice('設定を保存しました。', 'success');
    }

    $pc_enabled = get_option('gti_syn_menu_search_pc_enabled', '0');
    $sp_enabled = get_option('gti_syn_menu_search_sp_enabled', '0');

?>
    <div class="wrap">
        <h1>GTI SYN メニュー内検索設定</h1>
        <p>グローバルナビゲーション（PC）とドロワーメニュー（スマホ）に、検索フォームを自動挿入します。</p>

        <form method="post">
            <?php wp_nonce_field('gti_syn_menu_search_save', 'gti_syn_menu_search_nonce'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">PC用グローバルナビ</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gti_syn_menu_search_pc_enabled" value="1" <?php checked($pc_enabled, '1'); ?>>
                            PC用のグローバルナビゲーションの右端に検索フォームを追加する
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">スマホ用ドロワーメニュー</th>
                    <td>
                        <label>
                            <input type="checkbox" name="gti_syn_menu_search_sp_enabled" value="1" <?php checked($sp_enabled, '1'); ?>>
                            スマホ用のドロワーメニュー（ハンバーガーメニュー）の最後に検索フォームを追加する
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button('設定を保存'); ?>
        </form>
    </div>
<?php
}

// --------------------------------------------------
// 自動挿入・表示ロジック
// --------------------------------------------------

// 1. スタイルの読み込み (CSS)
add_action('wp_enqueue_scripts', function() {
    $pc_enabled = get_option('gti_syn_menu_search_pc_enabled', '0');
    $sp_enabled = get_option('gti_syn_menu_search_sp_enabled', '0');

    // いずれかが有効な場合にCSSをロード
    if ($pc_enabled !== '1' && $sp_enabled !== '1') {
        return;
    }

    wp_enqueue_style(
        'gti-syn-menu-search-style',
        GTI_KENI_TOOLS_URL . 'assets/menu-search.css',
        array(),
        GTI_KENI_TOOLS_VERSION
    );
});

// 2. スマホ用ドロワーメニュー内に検索フォームを追加 (wp_nav_menu_items フィルター)
add_filter('wp_nav_menu_items', function($items, $args) {
    if (get_option('gti_syn_menu_search_sp_enabled', '0') !== '1') {
        return $items;
    }

    // 引数 $args がオブジェクトでない場合はスキップ（エラー防止）
    if (!is_object($args)) {
        return $items;
    }

    // スマホドロワーメニューの theme_location が 'global-navigation' かつ menu_class に 'common-nav' が含まれる場合に対象とする
    if (
        isset($args->theme_location) && $args->theme_location === 'global-navigation' &&
        isset($args->menu_class) && strpos($args->menu_class, 'common-nav') !== false
    ) {
        $search_form = '<li class="menu-search">' . get_search_form(array('echo' => false)) . '</li>';
        $items .= $search_form;
    }

    return $items;
}, 10, 2);

// 3. PC用グローバルナビゲーションの横に検索フォームを追加 (wp_nav_menu フィルター)
add_filter('wp_nav_menu', function($nav_menu, $args) {
    if (get_option('gti_syn_menu_search_pc_enabled', '0') !== '1') {
        return $nav_menu;
    }

    // 引数 $args がオブジェクトでない場合はスキップ（エラー防止）
    if (!is_object($args)) {
        return $nav_menu;
    }

    // PCグローバルナビの theme_location が 'global-navigation' かつ menu_class に 'ddmenu__list' が含まれる場合に対象とする
    if (
        isset($args->theme_location) && $args->theme_location === 'global-navigation' &&
        isset($args->menu_class) && strpos($args->menu_class, 'ddmenu__list') !== false
    ) {
        $search_html = '<div class="ddmenu-search">' . get_search_form(array('echo' => false)) . '</div>';
        $nav_menu .= $search_html;
    }

    return $nav_menu;
}, 10, 2);
