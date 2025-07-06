<?php
/*
Plugin Name: My Post Previews
//Plugin URI: https://yourwebsite.com/my-post-previews
Description: Custom admin dashboard to preview and change post statuses quickly.
Version: 1.0.0
Author: Your Name
//Author URI: https://yourwebsite.com
License: GPL2
Text Domain: my-post-previews
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', function() {
    add_menu_page(
        __('Post Previews', 'my-post-previews'),
        __('Post Previews', 'my-post-previews'),
        'edit_posts',
        'post-previews',
        'mpp_show_post_previews',
        'dashicons-visibility',
        20
    );
});

function mpp_show_post_previews() {
    // Handle status change POST request
    if (isset($_POST['change_status'], $_POST['post_id'], $_POST['new_status'])) {
        $post_id = intval($_POST['post_id']);
        $new_status = sanitize_text_field($_POST['new_status']);

        if (current_user_can('edit_post', $post_id)) {
            if ($new_status === 'trash') {
                wp_trash_post($post_id);
            } else {
                wp_update_post([
                    'ID' => $post_id,
                    'post_status' => $new_status,
                ]);
            }

            // Redirect to avoid resubmission
            $redirect_url = admin_url('admin.php?page=post-previews');
            if (!empty($_GET['post_status'])) {
                $redirect_url = add_query_arg('post_status', $_GET['post_status'], $redirect_url);
            }
            wp_redirect($redirect_url);
            exit;
        } else {
            echo '<div class="notice notice-error"><p>' . __('You do not have permission to edit this post.', 'my-post-previews') . '</p></div>';
        }
    }

    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    $status_filter = $_GET['post_status'] ?? '';

    echo '<div class="wrap">';
    echo '<h1>' . __('Post Previews', 'my-post-previews') . '</h1>';

    // Filter form
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="post-previews">';
    echo '<label for="post_status">' . __('Filter by status:', 'my-post-previews') . '</label> ';
    echo '<select name="post_status" id="post_status" onchange="this.form.submit()">';
    echo '<option value="">' . __('All', 'my-post-previews') . '</option>';
    $statuses = ['publish' => 'Published', 'draft' => 'Draft', 'future' => 'Scheduled', 'pending' => 'Pending', 'private' => 'Private'];
    foreach ($statuses as $key => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($status_filter, $key, false), esc_html__($label, 'my-post-previews'));
    }
    echo '</select>';
    echo '</form>';

    // Query posts
    $query = new WP_Query([
        'post_type' => 'post',
        'posts_per_page' => 50,
        'paged' => $paged,
        'post_status' => $status_filter ?: 'any',
    ]);

    echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px;">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $thumbnail = get_the_post_thumbnail_url(get_the_ID(), 'medium') ?: 'https://via.placeholder.com/300x200?text=No+Image';
            $title = get_the_title();
            $excerpt = wp_trim_words(get_the_excerpt(), 20);
            $status = get_post_status();

            echo '<div style="border:1px solid #ccc;padding:10px;background:#fff;">';
            echo '<img src="' . esc_url($thumbnail) . '" style="width:100%;height:auto;">';
            echo '<h3>' . esc_html($title) . '</h3>';
            echo '<p><strong>' . __('Status:', 'my-post-previews') . '</strong> ' . esc_html($status) . '</p>';
            echo '<p>' . esc_html($excerpt) . '</p>';
            echo '<a href="' . esc_url(get_permalink()) . '" target="_blank">' . __('Preview', 'my-post-previews') . '</a>';

            // Status change form
            echo '<form method="post" style="margin-top:10px;">';
            echo '<input type="hidden" name="post_id" value="' . get_the_ID() . '">';
            echo '<select name="new_status">';
            echo '<option value="">' . __('-- Change Status --', 'my-post-previews') . '</option>';
            echo '<option value="publish">' . __('Publish', 'my-post-previews') . '</option>';
            echo '<option value="draft">' . __('Draft', 'my-post-previews') . '</option>';
            echo '<option value="private">' . __('Private', 'my-post-previews') . '</option>';
            echo '<option value="trash">' . __('Trash', 'my-post-previews') . '</option>';
            echo '</select> ';
            echo '<input type="submit" name="change_status" value="' . __('Apply', 'my-post-previews') . '">';
            echo '</form>';

            echo '</div>';
        }
    } else {
        echo '<p>' . __('No posts found.', 'my-post-previews') . '</p>';
    }

    echo '</div>';

    // Pagination
    $base_url = admin_url('admin.php?page=post-previews');
    if ($status_filter) {
        $base_url = add_query_arg('post_status', $status_filter, $base_url);
    }

    echo '<div style="margin-top:20px;">';
    echo paginate_links([
        'total' => $query->max_num_pages,
        'current' => $paged,
        'base' => $base_url . '&paged=%#%',
        'format' => '',
    ]);
    echo '</div>';

    wp_reset_postdata();
    echo '</div>';
}
