<?php
/**
 * Files related functions
 *
 * This class defines all code necessary to upload files.
 *
 * @since      1.0.0
 * @author     GeoDirectory Team <info@wpgeodirectory.com>
 */
class Users_WP_Files {

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function __construct() {
        if($this->uwp_doing_upload()){
            add_filter( 'wp_handle_upload_prefilter', array($this, 'uwp_wp_media_restrict_file_types') );
        }
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function handle_file_upload($field, $files ) {

        if ( isset( $files[ $field->htmlvar_name ] ) && ! empty( $files[ $field->htmlvar_name ] ) && ! empty( $files[ $field->htmlvar_name ]['name'] ) ) {

            $extra_fields = unserialize($field->extra_fields);

            $allowed_mime_types = array();
            if (isset($extra_fields['uwp_file_types']) && !in_array("*", $extra_fields['uwp_file_types'])) {
                $allowed_mime_types = $extra_fields['uwp_file_types'];
            }

            $allowed_mime_types = apply_filters('uwp_allowed_mime_types', $allowed_mime_types, $field->htmlvar_name);

            $file_urls       = array();
            $files_to_upload = $this->uwp_prepare_files( $files[ $field->htmlvar_name ] );

            $max_upload_size = $this->uwp_get_max_upload_size($field->form_type, $field->htmlvar_name);

            if ( ! $max_upload_size ) {
                $max_upload_size = 0;
            }

            foreach ( $files_to_upload as $file_key => $file_to_upload ) {

                if (!empty($allowed_mime_types)) {
                    $ext = uwp_get_file_type($file_to_upload['type']);

                    $allowed_error_text = implode(', ', $allowed_mime_types);
                    if ( !in_array( $ext , $allowed_mime_types ) )
                        return new WP_Error( 'validation-error', sprintf( __( 'Allowed files types are: %s', 'userswp' ),  $allowed_error_text) );
                }


                if ( $file_to_upload['size'] >  $max_upload_size) {
                    return new WP_Error( 'file-too-big', __( 'The uploaded file is too big. Maximum size allowed:'. $this->uwp_formatSizeUnits($max_upload_size), 'userswp' ) );
                }


                $error_result = apply_filters('uwp_handle_file_upload_error_checks', true, $field, $file_key, $file_to_upload);
                if (is_wp_error($error_result)) {
                    return $error_result;
                }

                remove_filter( 'wp_handle_upload_prefilter', array($this, 'uwp_wp_media_restrict_file_types') );
                $uploaded_file = $this->uwp_upload_file( $file_to_upload, array( 'file_key' => $file_key ) );
                add_filter( 'wp_handle_upload_prefilter', array($this, 'uwp_wp_media_restrict_file_types') );

                if ( is_wp_error( $uploaded_file ) ) {

                    return new WP_Error( 'validation-error', $uploaded_file->get_error_message() );

                } else {

                    $file_urls[] = array(
                        'url'  => $uploaded_file->url,
                        'path' => $uploaded_file->path,
                        'size' => $uploaded_file->size,
                        'name' => $uploaded_file->name,
                        'type' => $uploaded_file->type,
                    );

                }

            }

            return current( $file_urls );

        }
        return true;

    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 2) . ' kB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_formatSizeinKb($bytes)
    {
        $kb = $bytes / 1024;
        return $kb;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_get_size_in_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= (1024 * 1024 * 1024); //1073741824
                break;
            case 'm':
                $val *= (1024 * 1024); //1048576
                break;
            case 'k':
                $val *= 1024;
                break;
        }

        return $val;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_upload_file( $file, $args = array() ) {

        include_once ABSPATH . 'wp-admin/includes/file.php';
        include_once ABSPATH . 'wp-admin/includes/media.php';

        $args = wp_parse_args( $args, array(
            'file_key'           => '',
            'file_label'         => '',
            'allowed_mime_types' => get_allowed_mime_types()
        ) );

        $uploaded_file              = new stdClass();

        if ( ! in_array( $file['type'], $args['allowed_mime_types'] ) ) {
            if ( $args['file_label'] ) {
                return new WP_Error( 'upload', sprintf( __( '"%s" (filetype %s) needs to be one of the following file types: %s', 'userswp' ), $args['file_label'], $file['type'], implode( ', ', array_keys( $args['allowed_mime_types'] ) ) ) );
            } else {
                return new WP_Error( 'upload', sprintf( __( 'Uploaded files need to be one of the following file types: %s', 'userswp' ), implode( ', ', array_keys( $args['allowed_mime_types'] ) ) ) );
            }
        } else {
            $upload = wp_handle_upload( $file, apply_filters( 'uwp_handle_upload_overrides', array( 'test_form' => false ) ) );
            if ( ! empty( $upload['error'] ) ) {
                return new WP_Error( 'upload', $upload['error'] );
            } else {
                $uploaded_file->url       = $upload['url'];
                $uploaded_file->name      = basename( $upload['file'] );
                $uploaded_file->path      = $upload['file'];
                $uploaded_file->type      = $upload['type'];
                $uploaded_file->size      = $file['size'];
                $uploaded_file->extension = substr( strrchr( $uploaded_file->name, '.' ), 1 );
            }
        }


        return $uploaded_file;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_prepare_files( $file_data ) {
        $files_to_upload = array();

        if ( is_array( $file_data['name'] ) ) {
            foreach ( $file_data['name'] as $file_data_key => $file_data_value ) {

                if ( $file_data['name'][ $file_data_key ] ) {
                    $files_to_upload[] = array(
                        'name'     => $file_data['name'][ $file_data_key ],
                        'type'     => $file_data['type'][ $file_data_key ],
                        'tmp_name' => $file_data['tmp_name'][ $file_data_key ],
                        'error'    => $file_data['error'][ $file_data_key ],
                        'size'     => $file_data['size'][ $file_data_key ]
                    );
                }
            }
        } else {
            $files_to_upload[] = $file_data;
        }

        return $files_to_upload;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_validate_uploads($files, $type, $url_only = true, $fields = false) {

        $validated_data = array();

        if (empty($files)) {
            return $validated_data;
        }

        if (!$fields) {
            global $wpdb;
            $table_name = uwp_get_table_prefix() . 'uwp_form_fields';

            if ($type == 'register') {
                $fields = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE form_type = %s AND field_type = 'file' AND is_active = '1' AND is_register_field = '1' ORDER BY sort_order ASC", array('account')));
            } elseif ($type == 'account') {
                $fields = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE form_type = %s AND field_type = 'file' AND is_active = '1' AND is_register_only_field = '0' ORDER BY sort_order ASC", array('account')));
            } else {
                $fields = $wpdb->get_results($wpdb->prepare("SELECT * FROM " . $table_name . " WHERE form_type = %s AND field_type = 'file' AND is_active = '1' ORDER BY sort_order ASC", array($type)));
            }
        }


        if (!empty($fields)) {
            foreach ($fields as $field) {
                if(isset($files[$field->htmlvar_name])) {

                    $file_urls = $this->handle_file_upload($field, $files);

                    if (is_wp_error($file_urls)) {
                        return $file_urls;
                    }

                    if ($url_only) {
                        $validated_data[$field->htmlvar_name] = $file_urls['url'];
                    } else {
                        $validated_data[$field->htmlvar_name] = $file_urls;
                    }
                }

            }
        }

        return $validated_data;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_file_upload_preview($field, $value, $removable = true) {
        $output = '';

        $value = esc_html($value);

        if ($field->htmlvar_name == "uwp_banner_file") {
            $htmlvar = "uwp_account_banner_thumb";
        } elseif ($field->htmlvar_name == "uwp_avatar_file") {
            $htmlvar = "uwp_account_avatar_thumb";
        } else {
            $htmlvar = $field->htmlvar_name;
        }

        // If is current user's profile (profile.php)
        if ( is_admin() && defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE ) {
            $user_id = get_current_user_id();
            // If is another user's profile page
        } elseif (is_admin() && ! empty($_GET['user_id']) && is_numeric($_GET['user_id']) ) {
            $user_id = $_GET['user_id'];
            $user_id = (int) sanitize_text_field($user_id);
            // Otherwise something is wrong.
        } else {
            $user_id = get_current_user_id();
        }

        if ($value) {

            $upload_dir = wp_upload_dir();
            $value = $upload_dir['baseurl'].$value;

            $file = basename( $value );
            $filetype = wp_check_filetype($file);
            $image_types = array('png', 'jpg', 'jpeg', 'gif');
            if (in_array($filetype['ext'], $image_types)) {
                $output .= '<div class="uwp_file_preview_wrap">';
                $output .= '<a href="'.$value.'" class="uwp_upload_file_preview"><img style="max-width:100px;" src="'.$value.'" /></a>';
                if ($removable) {
                    $output .= '<a onclick="return confirm(\'are you sure?\')" style="display: block;margin: 5px 0;" href="#" id="'.$htmlvar.'" data-htmlvar="'.$htmlvar.'" data-uid="'.$user_id.'" class="uwp_upload_file_remove">'. __( 'Remove Image' , 'userswp' ).'</a>';
                }
                $output .= '</div>';
                ?>
                <?php
            } else {
                $output .= '<div class="uwp_file_preview_wrap">';
                $output .= '<a href="'.$value.'" class="uwp_upload_file_preview">'.$file.'</a>';
                if ($removable) {
                    $output .= '<a onclick="return confirm(\'are you sure?\')" style="display: block;margin: 5px 0;" href="#" id="'.$htmlvar.'" data-htmlvar="'.$htmlvar.'" data-uid="'.$user_id.'" class="uwp_upload_file_remove">'. __( 'Remove File' , 'userswp' ).'</a>';
                }
                $output .= '</div>';
                ?>
                <?php
            }
        }
        return $output;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_wp_media_restrict_file_types($file) {
        // This bit is for the flash uploader
        if ($file['type']=='application/octet-stream' && isset($file['tmp_name'])) {
            $file_size = getimagesize($file['tmp_name']);
            if (isset($file_size['error']) && $file_size['error']!=0) {
                $file['error'] = "Unexpected Error: {$file_size['error']}";
                return $file;
            } else {
                $file['type'] = $file_size['mime'];
            }
        }
        list($category,$type) = explode('/',$file['type']);
        if ('image'!=$category || !in_array($type,array('jpg','jpeg','gif','png'))) {
            $file['error'] = "Sorry, you can only upload a .GIF, a .JPG, or a .PNG image file.";
        } else if ($post_id = (isset($_REQUEST['post_id']) ? $_REQUEST['post_id'] : false)) {
            if (count(get_posts("post_type=attachment&post_parent={$post_id}"))>0)
                $file['error'] = "Sorry, you cannot upload more than one (1) image.";
        }
        return $file;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_doing_upload(){
        return isset($_POST['uwp_profile_upload']) ? true : false;
    }

    /**
     *
     *
     * @since   1.0.0
     * @package UsersWP
     * @return void
     */
    public function uwp_get_max_upload_size($form_type = false, $field_htmlvar_name = false) {
        if (is_multisite()) {
            $network_setting_size = esc_attr( get_option( 'fileupload_maxk', 300 ) );
            $max_upload_size = $this->uwp_get_size_in_bytes($network_setting_size.'k');
            if ($max_upload_size > wp_max_upload_size()) {
                $max_upload_size = wp_max_upload_size();
            }
        } else {
            $max_upload_size = wp_max_upload_size();
        }
        $max_upload_size = apply_filters('uwp_get_max_upload_size', $max_upload_size, $form_type, $field_htmlvar_name);

        return $max_upload_size;
    }

}