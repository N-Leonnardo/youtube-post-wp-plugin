<?php
/*
Plugin Name: Youtube Connection by Leo Nascimento
Plugin URI: https://www.example.com/
Description: Adds a custom YouTube URL field to the post editor.
Version: 1.0
Author: Leo Nascimento
Author URI: https://www.leonascimento.dev/
*/

// Add the custom field to the post editor screen
function custom_youtube_field_add_meta_box() {
    add_meta_box(
        'custom_youtube_field',
        'YouTube URL',
        'custom_youtube_field_callback',
        'post',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'custom_youtube_field_add_meta_box');

// Display the custom field in the post editor
function custom_youtube_field_callback($post) {
    wp_nonce_field(basename(__FILE__), 'custom_youtube_field_nonce');
    $field_value = get_post_meta($post->ID, 'youtube_url', true);
    ?>
    <label for="youtube_url">YouTube URL:</label>
    <input type="text" name="youtube_url" id="youtube_url" value="<?php echo esc_attr($field_value); ?>" size="30" />
    <?php
}

// Save the custom field data and set the featured image when the post is saved
function custom_youtube_field_save_meta_box($post_id) {
    if (!isset($_POST['custom_youtube_field_nonce']) || !wp_verify_nonce($_POST['custom_youtube_field_nonce'], basename(__FILE__))) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['youtube_url'])) {
        $youtube_url = sanitize_text_field($_POST['youtube_url']);
        update_post_meta($post_id, 'youtube_url', $youtube_url);

        // Fetch the YouTube video thumbnail
        $video_id = custom_youtube_field_get_video_id($youtube_url);
        $thumbnail_url = "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";

        // Set the fetched thumbnail as the featured image
        custom_youtube_field_set_featured_image($thumbnail_url, $post_id);
    }
}
add_action('save_post', 'custom_youtube_field_save_meta_box');

// Extract the YouTube video ID from the URL
function custom_youtube_field_get_video_id($url) {
    $video_id = '';
    $url_parts = parse_url($url);
    if (isset($url_parts['query'])) {
        parse_str($url_parts['query'], $query_params);
        if (isset($query_params['v'])) {
            $video_id = $query_params['v'];
        }
    } else {
        $path_parts = explode('/', $url_parts['path']);
        $video_id = end($path_parts);
    }
    return $video_id;
}


// Set the fetched thumbnail as the featured image
function custom_youtube_field_set_featured_image($thumbnail_url, $post_id) {
    if (has_post_thumbnail($post_id)) {
        return;
    }
    
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($thumbnail_url);
    $filename = 'post_' . $post_id . '_' . time() . '.jpg'; // Unique filename based on post ID and current timestamp
    if (wp_mkdir_p($upload_dir['path'])) {
        $file = $upload_dir['path'] . '/' . $filename;
    } else {
        $file = $upload_dir['basedir'] . '/' . $filename;
    }
    file_put_contents($file, $image_data);
    $wp_filetype = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attachment_id = wp_insert_attachment($attachment, $file, $post_id);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $file);
    wp_update_attachment_metadata($attachment_id, $attachment_data);
    set_post_thumbnail($post_id, $attachment_id);
}
