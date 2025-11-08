<?php
if (! defined('ABSPATH')) exit;

/**
 * GTI賢威-SYNツールモジュール：PV統合
 */

$GLOBALS['gti_keni_tools']['gti-pv-merge'] = [
    'title'    => 'PV統合ツール（賢威→SYN）',
    'menu'     => 'PV統合ツール',
    'callback' => 'gti_keni_render_pv_merge_page',
];

function gti_keni_render_pv_merge_page()
{
    if (! current_user_can('manage_options')) return;
    $is_done = get_option('gti_keni_pv_merge_done');

    if (isset($_POST['gti_pv_merge_run']) && check_admin_referer('gti_pv_merge_action')) {
        $count = gti_keni_merge_pvc_into_post_views();
        update_option('gti_keni_pv_merge_done', 1);
        gti_keni_notice("PV統合を完了しました（対象：{$count}件）", 'success');
        gti_keni_bar_notice('PV統合が完了しました', 'yes');
    } elseif (isset($_POST['gti_pv_merge_revert']) && check_admin_referer('gti_pv_merge_action')) {
        $count = gti_keni_revert_pv_merge();
        delete_option('gti_keni_pv_merge_done');
        gti_keni_notice("ロールバックを完了しました（対象：{$count}件）", 'warning');
        gti_keni_bar_notice('ロールバックが完了しました', 'update');
    }

?>
    <div class="wrap">
        <h1>PV統合ツール（賢威→SYN）</h1>
        <p>旧「pvc_views」を新「post_views_count」に統合します。<br>
            ※ 一度実行すると元には戻せませんが、「ロールバック」で復元できます。</p>

        <form method="post" onsubmit="return confirm('<?php echo $is_done ? 'ロールバックを行います。よろしいですか？' : '処理を行います。戻せません。よろしいですか？'; ?>');">
            <?php wp_nonce_field('gti_pv_merge_action'); ?>
            <?php if (! $is_done) : ?>
                <?php submit_button('PV統合を実行する', 'primary', 'gti_pv_merge_run'); ?>
            <?php else : ?>
                <?php submit_button('ロールバック（元に戻す）', 'delete', 'gti_pv_merge_revert'); ?>
            <?php endif; ?>
        </form>
    </div>
<?php
}

function gti_keni_merge_pvc_into_post_views()
{
    global $wpdb;
    $meta_key = 'pvc_views';
    $posts = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    ));

    $merged = 0;
    foreach ($posts as $post_id) {
        $pvc  = (int) get_post_meta($post_id, 'pvc_views', true);
        $view = (int) get_post_meta($post_id, 'post_views_count', true);
        if (get_post_meta($post_id, '_views_merged', true)) continue;

        if ($pvc > 0) {
            $new_value = $view < $pvc ? $pvc + $view : $view;
            update_post_meta($post_id, 'post_views_count', $new_value);
            update_post_meta($post_id, '_views_merged', 1);
            $merged++;
        }
    }
    return $merged;
}

function gti_keni_revert_pv_merge()
{
    global $wpdb;
    $meta_key = 'pvc_views';
    $posts = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    ));

    $reverted = 0;
    foreach ($posts as $post_id) {
        $pvc  = (int) get_post_meta($post_id, 'pvc_views', true);
        $view = (int) get_post_meta($post_id, 'post_views_count', true);
        if (get_post_meta($post_id, '_views_merged', true) && $pvc > 0) {
            update_post_meta($post_id, 'post_views_count', max(0, $view - $pvc));
            delete_post_meta($post_id, '_views_merged');
            $reverted++;
        }
    }
    return $reverted;
}
