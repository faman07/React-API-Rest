<?php
/*
Plugin Name: Mi REST API Personalizada
Description: Plugin que implementa una REST API personalizada en WordPress.
Version: 1.0
Author: Tu Nombre
*/

// Registrar los endpoints de la API
add_action('rest_api_init', 'mi_rest_api_init');

function mi_rest_api_init() {
    // Endpoint para obtener la lista de publicaciones
    register_rest_route('react/v1', '/posts', array(
        'methods' => 'GET',
        'callback' => 'mi_get_posts',
    ));

    // Endpoint para crear una nueva publicación
    register_rest_route('react/v1', '/posts', array(
        'methods' => 'POST',
        'callback' => 'mi_create_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint para actualizar una publicación existente
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'mi_update_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint para eliminar una publicación
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'mi_delete_post',
        'permission_callback' => 'mi_api_permissions',
    ));

    // Endpoint para obtener la información de una publicación específica
    register_rest_route('react/v1', '/posts/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'mi_get_post',
    ));
}

// Función para obtener la lista de publicaciones
function mi_get_posts($request) {
    // Lógica para obtener las publicaciones desde la base de datos
    $posts = get_posts(array(
        'post_type' => 'post',
        'posts_per_page' => -1,
    ));

    // Formatear los datos de las publicaciones según la estructura requerida
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

    // Retornar los datos formateados en formato JSON
    return rest_ensure_response($formatted_posts);
}

// Función para crear una nueva publicación
function mi_create_post($request) {
    // Verificar si el usuario tiene los permisos necesarios
    if (!current_user_can('publish_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para crear publicaciones.'), array('status' => 403));
    }

    // Obtener los datos enviados en la solicitud
    $params = $request->get_params();

    // Validar y sanitizar los datos
    $title = sanitize_text_field($params['title']);
    $content = wp_kses_post($params['content']);
    $meta_fields = $params['meta_fields'];

    // Crear la nueva publicación
    $post_data = array(
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'post',
    );
    $new_post_id = wp_insert_post($post_data);

    // Guardar los metadatos personalizados
    foreach ($meta_fields as $meta_field) {
        $key = sanitize_text_field($meta_field['key']);
        $value = sanitize_text_field($meta_field['value']);
        update_post_meta($new_post_id, $key, $value);
    }

    // Retornar la respuesta de éxito
    return rest_ensure_response(array('message' => 'Publicación creada con éxito.'));
}

// Función para actualizar una publicación existente
function mi_update_post($request) {
    // Verificar si el usuario tiene los permisos necesarios
    if (!current_user_can('edit_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para editar publicaciones.'), array('status' => 403));
    }

    // Obtener los datos enviados en la solicitud
    $params = $request->get_params();
    $post_id = $params['id'];
    $title = sanitize_text_field($params['title']);
    $content = wp_kses_post($params['content']);
    $meta_fields = $params['meta_fields'];

    // Actualizar la publicación
    $post_data = array(
        'ID' => $post_id,
        'post_title' => $title,
        'post_content' => $content,
    );
    wp_update_post($post_data);

    // Actualizar los metadatos personalizados
    foreach ($meta_fields as $meta_field) {
        $key = sanitize_text_field($meta_field['key']);
        $value = sanitize_text_field($meta_field['value']);
        update_post_meta($post_id, $key, $value);
    }

    // Retornar la respuesta de éxito
    return rest_ensure_response(array('message' => 'Publicación actualizada con éxito.'));
}

// Función para eliminar una publicación
function mi_delete_post($request) {
    // Verificar si el usuario tiene los permisos necesarios
    if (!current_user_can('delete_posts')) {
        return new WP_Error('rest_forbidden', __('No tienes permiso para eliminar publicaciones.'), array('status' => 403));
    }

    // Obtener el ID de la publicación a eliminar
    $post_id = $request['id'];

    // Eliminar la publicación
    wp_delete_post($post_id, true);

    // Retornar la respuesta de éxito
    return rest_ensure_response(array('message' => 'Publicación eliminada con éxito.'));
}

// Función para obtener la información de una publicación específica
function mi_get_post($request) {
    // Obtener el ID de la publicación solicitada
    $post_id = $request['id'];

    // Verificar si la publicación existe
    if (!get_post($post_id)) {
        return new WP_Error('rest_not_found', __('No se encontró la publicación solicitada.'), array('status' => 404));
    }

    // Obtener los datos de la publicación
    $post = get_post($post_id);

    // Formatear los datos según la estructura requerida
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

    // Retornar los datos formateados en formato JSON
    return rest_ensure_response($formatted_post);
}

// Función para obtener las categorías de una publicación
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

// Función para obtener los metadatos personalizados de una publicación
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

// Función para verificar la autenticación en las solicitudes de API
function mi_api_permissions() {
    // Verificar si se proporcionó una clave de autenticación válida en la configuración del plugin
    $api_key = get_option('mi_rest_api_settings');
         if (empty($api_key)) {
               return false; 
            }
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
     if (!$auth_header) {
           return false; 
        }

    if ($api_key && $auth_header) {
        // Verificar la clave de autenticación
        $token = explode(' ', $auth_header)[1] ?? null;
             if (!$token) {
                return false; 
              }
    } else {
        // Si no se proporcionó una clave de autenticación, rechazar la solicitud
        return false;
    }

    return true;
}

// Agregar la página de configuración del plugin
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

// Registrar la opción de configuración
add_action('admin_init', 'mi_rest_api_register_settings');

function mi_rest_api_register_settings() {
    register_setting('mi_rest_api_settings', 'mi_api_key');
}
