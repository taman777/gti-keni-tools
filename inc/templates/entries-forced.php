<?php
/**
 * Custom entries loop (Forced Excerpt Version)
 *
 * Based on SYN Theme's template-parts/loop/entries.php
 * Modified by GTI Keni Tools to FORCE display of excerpts.
 */


use SYNX\Utils;

// 受け取り
$q = ( isset( $args['query'] ) && $args['query'] instanceof WP_Query ) ? $args['query'] : null;

// ループの投稿タイプ
$pt = 'post';
if ( ! is_search() ) {
	$pt = $q ? ( $q->posts[0]->post_type ?? 'post' ) : ( $GLOBALS['wp_query']->posts[0]->post_type ?? 'post' );
}
// 一覧タイプ（引数で上書き可能。フォールバックは 'card'）
$flag_to_type = array(
	0 => 'card',
	1 => 'list',
	2 => 'thumb',
	3 => 'blog',
	4 => 'text',
);
if ( isset( $args['list_type'] ) && is_string( $args['list_type'] ) ) {
	$list_type = $args['list_type'];
} else {
	$list_type_flag = (int) get_theme_mod( "synx_pt_{$pt}_list_type" );
	$list_type      = $flag_to_type[ $list_type_flag ] ?? 'card';
}
$list_type_class = 'is-type-' . $list_type;

// 表示フラグ
$show_modified = (int) get_theme_mod( "synx_pt_{$pt}_list_checks_modified" ) === 1;
$show_date     = (int) get_theme_mod( "synx_pt_{$pt}_list_checks_date" ) === 1;
$show_views    = (int) get_theme_mod( "synx_pt_{$pt}_list_checks_views" ) === 1;
$show_cat      = (int) get_theme_mod( "synx_pt_{$pt}_list_checks_category" ) === 1;
$show_author   = (int) get_theme_mod( "synx_pt_{$pt}_list_checks_author" ) === 1;
$hide_title    = ( 'thumb' === $list_type && (int) get_theme_mod( "synx_pt_{$pt}_list_checks_title" ) === 0 );

// ▼▼▼ GTI CUSTOM: Force Excerpt Display ▼▼▼
// Original: $show_excerpt  = ( 'blog' === $list_type && (int) get_theme_mod( "synx_pt_{$pt}_list_checks_excerpt" ) === 1 );
$show_excerpt = true; 
// ▲▲▲ GTI CUSTOM ▲▲▲

$title_count  = (int) get_theme_mod( "synx_pt_{$pt}_list_title_count" );
$aspect_ratio = (string) get_theme_mod( "synx_pt_{$pt}_list_aspectratio" );
$excerpt_pc   = (int) get_theme_mod( "synx_pt_{$pt}_list_excerpt_pc" );
$excerpt_sp   = (int) get_theme_mod( "synx_pt_{$pt}_list_excerpt_sp" );

// 1件分の描画
$render_item = function ( int $queried_id ) use (
	$list_type,
	$show_modified,
	$show_date,
	$show_views,
	$hide_title,
	$show_excerpt,
	$show_cat,
	$show_author,
	$title_count,
	$aspect_ratio,
	$excerpt_pc,
	$excerpt_sp
) {
	// クエリ内の投稿タイプ
	$queried_post_type = $queried_id ? get_post_type( $queried_id ) : '';
	// 属するタクソノミー
	$raw_tax_names = $queried_post_type ? (array) get_object_taxonomies( $queried_post_type, 'names' ) : array();
	$tax_names     = array_values( array_diff( $raw_tax_names, array( 'post_format' ) ) );
	?>

	<article class="js-fadein common-list__item">
		<a class="common-list__link" href="<?php echo esc_url( get_permalink( $queried_id ) ); ?>">
			<?php if ( 'text' !== $list_type ) : ?>
				<?php
				$img        = (array) Utils\get_thumbnail_image_data( $queried_id );
				$img_url    = $img['url'] ?? '';
				$img_width  = (int) ( $img['width'] ?? 0 );
				$img_height = (int) ( $img['height'] ?? 0 );
				if ( $img_url ) :
					?>
					<figure class="common-list__img" style="aspect-ratio: <?php echo esc_html( $aspect_ratio ); ?>;">
						<img
							loading="lazy"
							src="<?php echo esc_url( $img_url ); ?>"
							<?php if ( $img_width && $img_height ) : ?>
									width="<?php echo esc_attr( $img_width ); ?>" height="<?php echo esc_attr( $img_height ); ?>"
							<?php endif; ?>
							alt="<?php echo esc_attr( get_the_title( $queried_id ) ); ?>"
					/>
					</figure>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $show_modified || $show_date || $show_views || ! $hide_title || $show_excerpt || $show_cat || $show_author ) : ?>
				<div class="common-list__content">
					<div class="common-list__txtarea">

						<?php if ( $show_modified || $show_date || $show_views ) : ?>
							<div class="common-list__detail">
								<?php if ( $show_modified || $show_date ) : ?>
									<?php
									$date_publish  = get_the_date( 'Ymd', $queried_id );
									$date_modified = get_the_modified_date( 'Ymd', $queried_id );
									?>
									<div class="common-list__timestamp">
										<?php if ( $show_modified && $date_publish !== $date_modified ) : ?>
											<p class="common-list__timestamp-item">
												<i class="icon-change" aria-hidden="true"></i><?php echo esc_html( get_the_modified_date( '', $queried_id ) ); ?>
											</p>
										<?php endif; ?>
										<?php if ( $show_date ) : ?>
											<p class="common-list__timestamp-item">
												<i class="icon-time" aria-hidden="true"></i><?php echo esc_html( get_the_date( '', $queried_id ) ); ?>
											</p>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ( $show_views ) : ?>
									<p class="common-list__view"><?php echo esc_html( Utils\get_post_views( $queried_id ) ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! $hide_title ) : ?>
							<?php
							$title_text = ( $title_count > 0 ) ? Utils\cut_text( 'title', $title_count ) : get_the_title( $queried_id );
							?>
							<h3 class="common-list__ttl"><?php echo esc_html( $title_text ); ?></h3>
						<?php endif; ?>

						<?php 
                        // ▼▼▼ GTI CUSTOM: Force Excerpt Logic ▼▼▼
                        if ( $show_excerpt ) : ?>
							<?php
								$excerpt_count = wp_is_mobile() ? $excerpt_sp : $excerpt_pc;
								if (empty($excerpt_count)) {
									$excerpt_count = 120; // Default fallback
								}
								
								$excerpt_text  = Utils\cut_text(
									'content',
									$excerpt_count,
									array(
										'before_more' => true,
										'no_trim_when_more' => true,
									)
								);

								// Fallback: If cut_text returns empty (e.g. specialized content), try standard excerpt
								if ( empty( $excerpt_text ) ) {
                                    // Try WP standard excerpt
									$excerpt_text = get_the_excerpt( $queried_id );
								}
								
								// Final fallback: Raw DB content
								if ( empty( $excerpt_text ) ) {
                                    $raw_content = get_post_field( 'post_content', $queried_id );
                                    if ( $raw_content ) {
                                        $content = strip_shortcodes( $raw_content );
                                        $content = wp_strip_all_tags( $content );
                                        $excerpt_text = mb_substr( $content, 0, $excerpt_count ) . '...';
                                    }
								}

                                if ( empty( $excerpt_text ) ) {
                                    // $excerpt_text = '<!-- DEBUG: No content found for ID: ' . $queried_id . ' -->';
                                    // Uncomment next line to force visible debug
                                    $excerpt_text = '（本文が取得できませんでした: ID ' . $queried_id . '）';
                                }
							?>
                            <!-- GTI Forced Excerpt -->
							<p class="common-list__excerpt"><?php echo $excerpt_text; ?></p>
						<?php endif; 
                        // ▲▲▲ GTI CUSTOM ▲▲▲
                        ?>

						<?php if ( $show_cat ) : ?>
							<?php
							$categories  = get_the_category( $queried_id );
							$other_terms = array();
							foreach ( $tax_names as $tx ) {
								if ( in_array( $tx, array( 'category', 'post_tag' ), true ) ) {
									// カテゴリー、タグはスキップ
									continue;
								}
								$tax_to_terms = get_the_terms( $queried_id, $tx );
								if ( $tax_to_terms && ! is_wp_error( $tax_to_terms ) ) {
									foreach ( $tax_to_terms as $t ) {
										$other_terms[] = $t;
									}
								}
							}
							?>

							<?php if ( $categories ) : ?>
								<ul class="common-cat common-list__cat">
									<?php foreach ( $categories as $cat_data ) : ?>
										<li class="common-cat__item">
											<span class="common-cat__txt"><?php echo esc_html( $cat_data->name ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							
							<?php if ( $other_terms && 'post' !== $queried_post_type ) : ?>
								<ul class="common-tax common-list__cat">
									<?php foreach ( $other_terms as $term_data ) : ?>
										<li class="common-tax__item">
											<span class="common-tax__txt"><?php echo esc_html( $term_data->name ); ?></span>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( $show_author ) : ?>
							<?php
							$author_id   = (int) get_the_author_meta( 'ID' );
							$author_name = get_the_author();
							/* translators: %s: author display name. */
							$avatar_alt = sprintf( __( '%s のアイコン', 'synx' ), $author_name );
							?>
							<p class="common-list__author">
								<span class="common-list__author-img"><?php echo get_avatar( $author_id, 24, '', $avatar_alt ); ?></span>
								<span class="common-list__author-name"><?php echo esc_html( $author_name ); ?></span>
							</p>
						<?php endif; ?>

						<?php if ( 'blog' === $list_type ) : ?>
							<p class="common-list__more"><?php echo esc_html__( '続きを読む', 'synx' ); ?></p>
						<?php endif; ?>

					</div>
				</div>
			<?php endif; ?>
		</a>
	</article>
	<?php
};

// ここからループ
$has_posts = $q ? $q->have_posts() : have_posts();
// 投稿がない場合の表示は呼び出し側
if ( ! $has_posts ) {
	return;
}
?>
<div class="common-list <?php echo esc_attr( $list_type_class ); ?>">
	<?php if ( $q ) : // 渡されたクエリがある場合 ?>
		<?php
		while ( $q->have_posts() ) :
			$q->the_post();
			?>
			<?php $render_item( (int) get_the_ID() ); ?>
			<?php
		endwhile;
		wp_reset_postdata();
		?>
	<?php else : // クエリがなければメインループを消費 ?>
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<?php $render_item( (int) get_the_ID() ); ?>
		<?php endwhile; ?>
	<?php endif; ?>
</div>
