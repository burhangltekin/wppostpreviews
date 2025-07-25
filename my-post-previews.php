<?php
/*
Plugin Name: Post Previews
Description: Custom admin dashboard to preview and change post statuses quickly.
Version: 1.0.0
Author: Burhan Gültekin
License: GPL2
Text Domain: postpreviews
*/

if (!defined('ABSPATH')) {
    exit;
}

// Add custom admin menu page
add_action('admin_menu', function () {
    add_menu_page(
        __('Post Previews', 'postpreviews'),
        __('Post Previews', 'postpreviews'),
        'edit_posts',
        'post-previews',
        'mpp_show_post_previews',
        'dashicons-visibility',
        20
    );
});

// Main page content
function mpp_show_post_previews()
{
    // Handle status change POST request
    if (
        isset($_POST['change_status'], $_POST['post_id'], $_POST['new_status'], $_POST['mpp_status_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mpp_status_nonce'])), 'mpp_change_status') &&
        current_user_can('edit_post', intval($_POST['post_id']))
    ) {
        $post_id = intval($_POST['post_id']);
        $new_status = sanitize_text_field(wp_unslash($_POST['new_status']));

        if ($new_status === 'trash') {
            wp_trash_post($post_id);
        } else {
            wp_update_post([
                'ID' => $post_id,
                'post_status' => $new_status,
            ]);
        }

        $redirect_url = admin_url('admin.php?page=post-previews');
        if (!empty($_GET['post_status'])) {
            $redirect_url = add_query_arg('post_status', sanitize_text_field(wp_unslash($_GET['post_status'])), $redirect_url);
        }
        wp_redirect($redirect_url);
        exit;
    }

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $status_filter = isset($_GET['post_status']) ? sanitize_text_field(wp_unslash($_GET['post_status'])) : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Post Previews', 'postpreviews') . '</h1>';

    // Filter form
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="post-previews">';
    echo '<label for="post_status">' . esc_html__('Filter by status:', 'postpreviews') . '</label> ';
    echo '<select name="post_status" id="post_status" onchange="this.form.submit()">';
    echo '<option value="">' . esc_html__('All', 'postpreviews') . '</option>';
    $statuses = [
        'publish' => esc_html__('Published', 'postpreviews'),
        'draft' => esc_html__('Draft', 'postpreviews'),
        'future' => esc_html__('Scheduled', 'postpreviews'),
        'pending' => esc_html__('Pending', 'postpreviews'),
        'private' => esc_html__('Private', 'postpreviews')
    ];
    foreach ($statuses as $key => $label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($key),
            selected($status_filter, $key, false),
            esc_html($label) // ✅ Escaping the label
        );
    }

    echo '</select>';
    echo '</form>';

    // Query posts
    $query = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => 50,
        'paged'          => $paged,
        'post_status'    => $status_filter ?: 'any',
    ]);

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $post_id = get_the_ID();
            $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
            $title = get_the_title($post_id);
            if (empty($title)) {
                $title = __('(No Title)', 'postpreviews');
            }

            $excerpt_raw = get_the_excerpt($post_id);
            $excerpt = $excerpt_raw ? wp_trim_words($excerpt_raw, 20) : __('(No excerpt available)', 'postpreviews');

            $status = get_post_status($post_id);
            $post_link = get_permalink($post_id);

            echo '<div style="border:1px solid #ccc;padding:10px;background:#fff;">';

            if ($thumbnail) {
                echo wp_get_attachment_image(get_post_thumbnail_id($post_id), 'medium', false, ['style' => 'width:100%;height:auto;']);
            } else {
                echo '<div style="width:100%;height:200px;background:#eee;display:flex;align-items:center;justify-content:center;color:#888;">No Image</div>';
            }

            echo '<h3>' . esc_html($title) . '</h3>';
            echo '<p><strong>' . esc_html__('Status:', 'postpreviews') . '</strong> ' . esc_html($status) . '</p>';
            echo '<p>' . esc_html($excerpt) . '</p>';

            if ($post_link && ($status === 'publish' || current_user_can('edit_post', $post_id))) {
                echo '<a href="' . esc_url($post_link) . '" target="_blank">' . esc_html__('Preview', 'postpreviews') . '</a>';
            } else {
                echo '<p><em>' . esc_html__('No preview available.', 'postpreviews') . '</em></p>';
            }

            // Status change form
            echo '<form method="post" style="margin-top:10px;">';
            echo '<input type="hidden" name="post_id" value="' . esc_attr($post_id) . '">';
            echo '<input type="hidden" name="mpp_status_nonce" value="' . esc_attr(wp_create_nonce('mpp_change_status')) . '">';
            echo '<select name="new_status">';
            echo '<option value="">' . esc_html__('-- Change Status --', 'postpreviews') . '</option>';
            echo '<option value="publish">' . esc_html__('Publish', 'postpreviews') . '</option>';
            echo '<option value="draft">' . esc_html__('Draft', 'postpreviews') . '</option>';
            echo '<option value="private">' . esc_html__('Private', 'postpreviews') . '</option>';
            echo '<option value="trash">' . esc_html__('Trash', 'postpreviews') . '</option>';
            echo '</select> ';
            echo '<input type="submit" name="change_status" value="' . esc_attr__('Apply', 'postpreviews') . '">';
            echo '</form>';

            echo '</div>';
        }
    } else {
        echo '<p>' . esc_html__('No posts found.', 'postpreviews') . '</p>';
    }

    echo '</div>';

    // Pagination
    $base_url = admin_url('admin.php?page=post-previews');
    if ($status_filter) {
        $base_url = add_query_arg('post_status', $status_filter, $base_url);
    }

    echo '<div style="margin-top:20px;">';
    echo wp_kses_post(paginate_links([
        'total'   => $query->max_num_pages,
        'current' => $paged,
        'base'    => $base_url . '&paged=%#%',
        'format'  => '',
    ]));
    echo '</div>';

    wp_reset_postdata();
    echo '</div>';
}
