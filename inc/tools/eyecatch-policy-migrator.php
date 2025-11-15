<?php

/**
 * アイキャッチポリシー移行ツール（賢威8 → SYN）
 * 賢威全体・個別設定から、SYNの挙動に正しく変換する。
 * Dry-run対応、一覧表示対応。
 */

if (!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
  モジュール登録
--------------------------------------------------------------*/
$GLOBALS['gti_keni_tools']['gti_eyecatch_policy_migrator'] = [
    'title'    => 'アイキャッチポリシー移行（賢威 → SYN）',
    'menu'     => 'アイキャッチ移行',
    'callback' => 'gti_eyecatch_policy_migrator_page',
];

/*--------------------------------------------------------------
  管理画面ページ
--------------------------------------------------------------*/
function gti_eyecatch_policy_migrator_page()
{

    $results = null;

    // Dry-run
    if (isset($_POST['gti_dryrun_eyecatch_policy_migration'])) {
        $results = gti_run_eyecatch_policy_migration(true);
        gti_render_eyecatch_summary_notice($results, true);
    }

    // 実行
    if (isset($_POST['gti_run_eyecatch_policy_migration'])) {
        $results = gti_run_eyecatch_policy_migration(false);
        gti_render_eyecatch_summary_notice($results, false);
    }

?>

    <div class="wrap">
        <h1>アイキャッチポリシー移行（賢威 → SYN）</h1>

        <p>SYNに正式な `_synx_eyecatch` を設定します。<br>
            賢威の全体設定・個別設定を元に、以下のルールで自動変換します。</p>

        <form method="post" style="margin:30px 0;">
            <?php submit_button('Dry-run（書き込みなし）', 'secondary', 'gti_dryrun_eyecatch_policy_migration'); ?>
        </form>

        <form method="post">
            <?php submit_button('移行を実行する（書き込みあり）', 'primary', 'gti_run_eyecatch_policy_migration'); ?>
        </form>
    </div>

<?php

    if ($results && isset($results['list'])) {
        gti_render_eyecatch_migration_list($results);
    }
}

/*--------------------------------------------------------------
  上部メッセージ
--------------------------------------------------------------*/
function gti_render_eyecatch_summary_notice($results, $dryrun)
{
    $total = $results['updated'] + $results['skipped'];

    echo '<div class="updated"><p>';
    echo '<strong>' . ($dryrun ? 'Dry-run結果' : '移行結果') . '</strong><br>';
    echo '対象総数：' . intval($total) . '<br>';
    echo '更新件数：' . intval($results['updated']) . '<br>';
    echo 'スキップ件数：' . intval($results['skipped']);
    echo '</p></div>';
}

/*--------------------------------------------------------------
  一覧表示
--------------------------------------------------------------*/
function gti_render_eyecatch_migration_list($results)
{

    $list = $results['list'];
    $dryrun = $results['dryrun'];

    echo '<div class="wrap" style="margin-top:30px;">';
    echo '<h2>対象記事一覧（' . ($dryrun ? 'Dry-run' : '実行結果') . '）</h2>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th width="60">ID</th>';
    echo '<th>タイトル</th>';
    echo '<th width="120">処理</th>';
    echo '<th width="140">投稿日時</th>';
    echo '<th width="140">更新日時</th>';
    echo '<th width="100">状態</th>';
    echo '<th width="120">賢威（個別）</th>';
    echo '<th width="120">賢威（全体）</th>';
    echo '</tr></thead><tbody>';

    if (empty($list)) {
        echo '<tr><td colspan="8">移行対象はありませんでした。</td></tr>';
    } else {
        foreach ($list as $row) {

            echo '<tr>';
            echo '<td>' . intval($row['ID']) . '</td>';
            echo '<td><a href="' . get_edit_post_link($row['ID']) . '" target="_blank">'
                . esc_html($row['title']) . '</a></td>';
            echo '<td>' . esc_html($row['result']) . '</td>';
            echo '<td>' . esc_html($row['date']) . '</td>';
            echo '<td>' . esc_html($row['modified']) . '</td>';
            echo '<td>' . esc_html($row['status']) . '</td>';
            echo '<td>' . esc_html($row['keni_post']) . '</td>';
            echo '<td>' . esc_html($row['keni_global']) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    echo '</div>';
}

/*--------------------------------------------------------------
  移行本体（$dryrun = true → 書き込みなし）
--------------------------------------------------------------*/
function gti_run_eyecatch_policy_migration($dryrun = false)
{
    global $wpdb;

    $updated = 0;
    $skipped = 0;
    $list    = [];

    $posts = $wpdb->get_results("
        SELECT ID, post_title, post_date, post_modified, post_status
        FROM {$wpdb->posts}
        WHERE post_status IN ('publish','draft','future','private')
    ");

    $keni_global = get_option('keni_thumbnail_disp', 'true'); // true = 表示
    $keni_global_is_hide = in_array($keni_global, ['false', '0'], true);

    foreach ($posts as $p) {

        $post_id = $p->ID;

        // 賢威個別
        $keni_post = get_post_meta($post_id, 'keni_thumbnail_disp_post', true);
        $keni_post_label =
            ($keni_post === '') ? 'デフォルト' : ($keni_post === 'show' ? '表示' : '非表示');

        // SYNがすでに設定済み → 一覧に出すが変更しない
        $syn = get_post_meta($post_id, '_synx_eyecatch', true);
        if ($syn !== '') {
            $skipped++;
            $list[] = [
                'ID' => $post_id,
                'title' => $p->post_title,
                'date' => $p->post_date,
                'modified' => $p->post_modified,
                'status' => $p->post_status,
                'result' => 'スキップ（SYNに設定あり）',
                'keni_post' => $keni_post_label,
                'keni_global' => $keni_global_is_hide ? '非表示' : '表示',
            ];
            continue;
        }

        /*------------------------------------------------------
           ★ ルール判定（あなたがまとめた仕様どおり）
        ------------------------------------------------------*/

        // 2-A: 全体＝表示 ＆ 個別＝デフォ or 表示 → そのまま（対象外）
        if (!$keni_global_is_hide && ($keni_post === '' || $keni_post === 'show')) {
            continue;
        }

        // 2-B: 全体＝表示 ＆ 個別＝非表示 → 非表示にする
        if (!$keni_global_is_hide && $keni_post === 'hide') {
            $value = 1;
        }

        // 2-C: 全体＝非表示 ＆ 個別＝デフォ or 非表示 → 非表示にする
        if ($keni_global_is_hide && ($keni_post === '' || $keni_post === 'hide')) {
            $value = 1;
        }

        // 2-D: 全体＝非表示 ＆ 個別＝表示 → そのまま
        if ($keni_global_is_hide && $keni_post === 'show') {
            continue;
        }

        // 上記の条件で $value が未定義の場合は continue されているはず
        if (!isset($value)) continue;

        if (!$dryrun) {
            update_post_meta($post_id, '_synx_eyecatch', $value);
        }

        $updated++;

        $list[] = [
            'ID' => $post_id,
            'title' => $p->post_title,
            'date' => $p->post_date,
            'modified' => $p->post_modified,
            'status' => $p->post_status,
            'result' => $dryrun ? 'Dry-run' : '更新',
            'keni_post' => $keni_post_label,
            'keni_global' => $keni_global_is_hide ? '非表示' : '表示',
        ];

        unset($value); // 重要：次ループで残らないように
    }

    return [
        'updated' => $updated,
        'skipped' => $skipped,
        'list'    => $list,
        'dryrun'  => $dryrun,
    ];
}
