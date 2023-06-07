<?php
/*
Plugin Name: Mi REST API Personalizada
Description: Plugin que implementa una REST API personalizada en WordPress.
Version: 1.0
Author: Tu Nombre
*/

// Register API endpoints
add_action('rest_api_init', 'mi_rest_api_init');

function mi_rest_api_init() {
    // Endpoint to get post list
    register_rest_route('react/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => 'mi_get_posts',
    ));

    // Endpoint to create a new post
    register_rest_route('react/v1', '/posts', array(
        'methods' => 'POST',
        'callback' => 'mi_create_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint to update an existing post
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'mi_update_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint to delete a post
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'mi_delete_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint to get the information of a specific post
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'mi_get_post',
    ));
}

// Function to get the list of publications
function mi_get_posts($request) {
    // Logic to get the publications from the database
    $posts = get_posts(array(
        'post_type' => 'post',
        'posts_per_page' => -1,
    ));

    // Format post data according to the required structure
    $formatted_posts = array();
    foreach ($posts as $post) {
        $formatted_posts[] = array(
            'id' => $post->ID,
            'slug' => $post->post_name,
            'link' => get_permalink($post->ID),
            'title' => $post->post_title,
            'featured_image' => get_the_post_thumbnail_url($post->ID),
            'categories' => mi_get_post_categories($post->ID),
            'content' => $post->post_content,
            'meta_fields' => mi_get_post_meta_fields($post->ID),
        );
    }

    // Return the data formatted in JSON format
    return rest_ensure_response($formatted_posts);
}

// Function to create a new publication
function mi_create_post($request) {
    // Check if the user has the necessary permissions
    if (!current_user_can('publish_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para crear publicaciones.'), array('status' => 403));
    }

    // Get the data sent in the request
    $params = $request->get_params();

    // Validate and sanitize data
    $title = sanitize_text_field($params['title']);
    $content = wp_kses_post($params['content']);
    $meta_fields = $params['meta_fields'];

    // Create the new post
    $post_data = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'post',
    );
    $new_post_id = wp_insert_post($post_data);

    // Save custom metadata
    foreach ($meta_fields as $meta_field) {
        $key = sanitize_text_field($meta_field['key']);
        $value = sanitize_text_field($meta_field['value']);
        update_post_meta($new_post_id, $key, $value);
    }

    // Return success response
    return rest_ensure_response(array('message' => 'Publicación creada con éxito.'));
}

// Function to update an existing post
function mi_update_post($request) {
    // Check if the user has the necessary permissions
    if (!current_user_can('edit_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para editar publicaciones.'), array('status' => 403));
    }

    // Get the data sent in the request
    $params = $request->get_params();
    $post_id = $params['id'];
    $title = sanitize_text_field($params['title']);
    $content = wp_kses_post($params['content']);
    $meta_fields = $params['meta_fields'];

    // update post
    $post_data = array(
        'ID' => $post_id,
        'post_title' => $title,
        'post_content' => $content,
    );
    wp_update_post($post_data);

    // Update custom metadata
    foreach ($meta_fields as $meta_field) {
        $key = sanitize_text_field($meta_field['key']);
        $value = sanitize_text_field($meta_field['value']);
        update_post_meta($post_id, $key, $value);
    }

    // Return success response
    return rest_ensure_response(array('message' => 'Publicación actualizada con éxito.'));
}

// Function to delete a post
function mi_delete_post($request) {
    // Check if the user has the necessary permissions
    if (!current_user_can('delete_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para eliminar publicaciones.'), array('status' => 403));
    }

    // Get the ID of the post to delete
    $post_id = $request['id'];

    // delete post
    wp_delete_post($post_id, true);

    // Return success response
    return rest_ensure_response(array('message' => 'Publicación eliminada con éxito.'));
}

// Function to get the information of a specific publication
function mi_get_post($request) {
    // Get the requested post ID
    $post_id = $request['id'];

    // Check if the post exists
    if (!get_post($post_id)) {
        return new WP_Error('rest_not_found', __('No se encontró la publicación solicitada.'), array('status' => 404));
    }

    // Get post data
    $post = get_post($post_id);

    // reset to data according to required structure
    $formatted_post = array(
        'id' => $post->ID,
        'slug' => $post->post_name,
        'link' => get_permalink($post->ID),
        'title' => $post->post_title,
        'featured_image' => get_the_post_thumbnail_url($post->ID),
        'categories' => mi_get_post_categories($post->ID),
        'content' => $post->post_content,
        'meta_fields' => mi_get_post_meta_fields($post->ID),
    );

    // Return the data formatted in JSON format
    return rest_ensure_response($formatted_post);
}

// Function to get the categories of a publication
function mi_get_post_categories($post_id) {
    $categories = wp_get_post_categories($post_id);

    $formatted_categories = array();
    foreach ($categories as $category_id) {
        $category = get_category($category_id);
        $formatted_categories[] = array(
            'id' => $category->term_id,
            'title' => $category->name,
            'description' => $category->description,
        );
    }

    return $formatted_categories;
}

// Function to get the custom metadata of a post
function mi_get_post_meta_fields($post_id) {
    $meta_fields = get_post_meta($post_id);

    $formatted_meta_fields = array();
    foreach ($meta_fields as $key => $values) {
        foreach ($values as $value) {
            $formatted_meta_fields[] = array(
                'key' => $key,
                'value' => $value,
            );
        }
    }

    return $formatted_meta_fields;
}

// Function to verify authentication on API requests
function mi_api_permissions() {
    // Check if a valid authentication key was provided in plugin settings
    $api_key = get_option('mi_rest_api_settings');
         if (empty($api_key)) {
               return false; 
            }
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
     if (!$auth_header) {
           return false; 
        }

    if ($api_key && $auth_header) {
        // Verify the authentication key
        $token = explode(' ', $auth_header)[1] ?? null;
             if (!$token) {
                return false; 
              }
    } else {
        // If an authentication key was not provided, reject the request
        return false;
    }

    return true;
}

// Add plugin settings page
add_action('admin_menu', 'mi_plugin_menu');

function mi_plugin_menu() {
    add_options_page('Configuración de mi REST API', 'mi REST API', 'manage_options', 'mi-rest-api-settings', 'mi_rest_api_settings_page');
}

function mi_rest_api_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de mi REST API</h1>
        <form method="post" action="options.php">
            <?php settings_fields('mi_rest_api_settings'); ?>
            <?php do_settings_sections('mi-rest-api-settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Clave de autenticación</th>
                    <td>
                        <input type="text" name="mi_api_key" value="<?php echo esc_attr(get_option('mi_api_key')); ?>" />
                        <p class="description">Ingrese una clave de autenticación para las solicitudes de API que así lo requieran.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register configuration option
add_action('admin_init', 'mi_rest_api_register_settings');

function mi_rest_api_register_settings() {
    register_setting('mi_rest_api_settings', 'mi_api_key');
}
