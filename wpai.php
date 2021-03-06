<?php

/**
 * Plugin Name:     WP AutoInstaller
 * Plugin URI:      https://wpai.3ele.de
 * Description:     Install from local Setup
 * Author:          Sebastian Weiss
 * Author URI:      wpai.3ele.de
 * Text Domain:     WP Auto Installer
 * Domain Path:     /wpai
 * Version:         0.1.0
 *
 */

// Your code starts here.


function send_logs()
{
    $email = get_option('admin_email');
    $to = 'wpai@3ele.de';
    $message = debug_info_version_check();
    $subject = 'Installer Log from setup:';
    $headers = 'From: ' . $email . "\r\n" .
        'Reply-To: ' . $email . "\r\n";

    $attachments = plugin_dir_path(__FILE__) . '/local_setup.json';
    $sent =  wp_mail($to, $subject, $message, $headers, $attachments);
    if ($sent == True) { 
}
}


function is_json($string, $return_data = false)
{
    $data = json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE) ? ($return_data ? $data : TRUE) : FALSE;
}

function create_local_setup_json($setup) { 

   
    $local_plugins=[];
    $plugins = get_plugins();
    $keys = array_keys($plugins); 
    foreach ($keys as $plugin) {
        $local_plugin = [];
        $plugin_key = explode("/",$plugin);
        $local_plugin['name']= $plugins[$plugin]['Name'];
        $local_plugin['version']= $plugins[$plugin]['Version'];
        $local_plugin['path']= $plugin_key[0];
        $local_plugin['file']= $plugin_key[1];

        $local_plugins[]= $local_plugin; 
    }

/*     $local_themes=[];
    $themes = wp_get_themes();
     print_r($themes);
    $keys = array_keys($themes); 
    foreach ($themes as $theme) {
        $local_theme = [];
        print_r($theme);
    break;
        $theme_key = explode("/",$theme);
        $local_theme['name']= $theme['headers:WP_Theme:private']['Name'];
        $local_theme['version']= $theme['headers:WP_Theme:private']['Version'];


        $local_themes .= $local_theme; 
    } */
 
        $local_options=[];

    $wpai_options = $setup['options'];
    foreach ($wpai_options as $option) {
        $local_option = [];

        $local_option['key']= $option['key'];
        $local_option['value']= get_option($option['key']);
        $local_options[] = $local_option; 
       
    }
    $local_setup = [];

    $local_setup['setup']['options'] = $local_options;
    $local_setup['setup']['plugins'] = $local_plugins;
    file_put_contents ( plugin_dir_path(__FILE__) . '/local_setup.json' ,json_encode($local_setup, JSON_PRETTY_PRINT) | LOCK_EX );
}

function gimme_your_options($option_name)
{
    $blame = 'core';
    $debug_backtrace = debug_backtrace();
    foreach ($debug_backtrace as $call) {
        if (empty($call['file']))
            continue;

        if (!preg_match('#wp-content/((?:(?:mu-)?plugins|themes)/.+)#i', $call['file'], $matches))
            continue;

        $blame = $matches[1];
        break;
    }
    $options_log = $option_name.',';
    
    file_put_contents ( plugin_dir_path(__FILE__) . '/action.logs' ,$options_log, FILE_APPEND | LOCK_EX );
   
}

function debug_info_version_check()
{
    //outputs basic information
    $notavailable = __('This information is not available.', 'debug-info');
    if (!function_exists('get_bloginfo')) {
        $wp = $notavailable;
    } else {
        $wp = get_bloginfo('version');
    }

    if (!function_exists('wp_get_theme')) {
        $theme = $notavailable;
    } else {
        $theme = wp_get_theme();
    }



    if (!function_exists('phpversion')) {
        $php = $notavailable;
    } else {
        $php = phpversion();
    }

    if (!function_exists('debug_info_get_mysql_version')) {
        $mysql = $notavailable;
    } else {
        $mysql = debug_info_get_mysql_version();
    }

    if (!function_exists('apache_get_version')) {
        $apache = $notavailable;
    } else {
        $apache = apache_get_version();
    }

    $themeversion    = $theme->get('Name') . __(' version ', 'debug-info') . $theme->get('Version') . $theme->get('Template');
    $themeauth        = $theme->get('Author') . ' - ' . $theme->get('AuthorURI');
    $uri            = $theme->get('ThemeURI');
    $debug_infos = '';
    $debug_infos .= '<tr><td>' . __('WordPress Version: ', 'debug-info') . '</td><td>' . $wp . '</td></tr>';
    $debug_infos .= '<tr><td>' . __('Current WordPress Theme: ', 'debug-info') . '</td><td>' . $themeversion . '</td></tr>';
    $debug_infos .= '<tr><td>' . __('Theme Author: ', 'debug-info') . '</td><td>' . $themeauth .  '</td></tr>';
    $debug_infos .= '<tr><td>' . __('Theme URI: ', 'debug-info') . '</td><td>' . $uri . '</td></tr>';
    $debug_infos .= '<tr><td>' . __('PHP Version: ', 'debug-info') . '</td><td>' . $php . '</td></tr>';
    $debug_infos .= '<tr><td>' . __('MySQL Version: ', 'debug-info') . '</td><td>' . $mysql . '</td></tr>';
    $debug_infos .= '<tr><td>' . __('Apache Version: ', 'debug-info') . '</td><td>' . $apache . '</td></tr>';
    return $debug_infos;
}



add_action('admin_menu', 'wpai_setup_menu');

function wpai_setup_menu()
{
    add_menu_page('WP Auto Installer', 'WP Auto Installer', 'manage_options', 'wpai', 'wpai_init');
}
function rrmdir($src)
{
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $full = $src . '/' . $file;
            if (is_dir($full)) {
                rrmdir($full);
            } else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
    return True;
}



function delete_mu_plugin()
{
    $path = WP_CONTENT_DIR . '/mu-plugins/';

    if (file_exists($path)) {
        if(rrmdir($path)){
            $wpai_options = get_option('wpai_options');
            $wpai_options['mu_plugin'] = 0;
            update_option('wpai_options', $wpai_options);
        }
    }
}

function wpai_init()
{
    // General check for user permissions.
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient pilchards to access this page.'));
    }
    // Check whether the button has been pressed AND also check the nonce
    if (isset($_POST['send_logs']) && check_admin_referer('send_logs_action')) {
        // the button has been pressed AND we've passed the security check
        send_logs();
    }
    // Check whether the button has been pressed AND also check the nonce
    if (isset($_POST['delete_mu-plugin']) && check_admin_referer('delete_mu-plugin')) {
        // the button has been pressed AND we've passed the security check
        delete_mu_plugin();
    }

   
    $configdata = json_decode(file_get_contents('http://json.testing.threeelements.de/19'), true);
    $setup =  $configdata['setup'];
    create_local_setup_json($setup);

 
?>
    <div class="wrap">
       
            <div id="dashboard-widgets" class="metabox-holder">
                <div class="welcome-panel-content">
                <h2><?php _e( "Willkommen bei WordPress, powered by WP Auto Installer!", 'wpai' ); ?></h2>
                    <div>
                        <div id="dashboard-widgets" class="metabox-holder">
                            <div class="postbox-container">
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                           <td><h3><?php _e( "Logs & Activity", 'wpai' ); ?></h3></td> 
                                        </tr>
                                    </thead>
 <tbody>
                                        <tr>
                                            <td><h3><?php _e( "Themes", 'wpai' ); ?></h3></td>
                                        </tr>
                                        <?php foreach ($setup['themes'] as $theme) : ?>
                                            <tr>
                                                <td>
                                                    <?php echo $theme['name']; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($theme['status'] == 'active') :
                                                        $theme = wp_get_theme(); // gets the current theme
                                                        if ($theme['name'] == $theme->name || $theme['name'] == $theme->parent_theme) :
                                                            echo '<span class="dashicons dashicons-yes"></span>';
                                                        else : echo '<span class="dashicons dashicons-no"></span>';
                                                        endif;
                                                    endif; ?>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td><h2><?php _e( "Plugins", 'wpai' ); ?></h2></td>
                                        </tr>
                                        <?php foreach ($setup['plugins'] as $plugin) : ?>
                                            <tr>
                                                <td>
                                                    <?php echo $plugin['name']; ?>
                                                </td>
                                                <td>
                                                    <?php if (is_plugin_active($plugin['path'] . '/' . $plugin['file'])) : echo '<span class="dashicons dashicons-yes"></span>';
                                                    else : echo '<span class="dashicons dashicons-no"></span>';
                                                    endif; ?>
                                                </td>

                                            </tr>
    
                                        <?php endforeach; ?>

                                        <tr>
                                            <td><h2><?php _e( "Options", 'wpai' ); ?></h2></td>
                                        </tr>
                                        <?php foreach ($setup['options'] as $option) : ?>
                                            <?php $local_option = get_option($option['key']); ?>

                                            <tr>
                                                <td>
                                                    <?php echo $option['key']; ?>


                                                </td>
                                                <td>
                                                    <?php if ($option['value'] == $local_option) : echo '<span class="dashicons dashicons-yes"></span>';
                                                    else : echo '<span class="dashicons dashicons-no"></span>';
                                                    endif; ?>
                                                </td>

                                            </tr>
                                            <?php if ((is_array($local_option)) or (is_array($option['value']))):?>
      <tr>
         <td style="word-break: break-all; ">        <?php  echo json_encode($local_option); ?></td>
         <td style="word-break: break-all; ">                <?php  echo json_encode($option['value']); ?></td>
     </tr>
                                            <?php endif;?>
                                        <?php endforeach; ?>
                                    </tbody>

                                    <tfoot>

                                    </tfoot>

                                </table>
                            </div>
                            <div class="postbox-container">
                                    <table class="widefat">
                                    <thead>
                                        <tr>
                                           <td><h3><?php _e( "Debug & System", 'wpai' ); ?> </h3></td> 
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php echo debug_info_version_check(); ?>
                                        </tbody>
                                                </table>
                            </div>
                   </div>

                           
                        </div>
                    </div>
                <?php
            }

            function wpai_check_mu_plugin(){
           
          
                    $path = WP_CONTENT_DIR . '/mu-plugins/';
                
                    if (file_exists($path)) {
                        return True;
                    }
                    else {
                        return False;
                    }
                }
            
            function wpai_activate()
            {
                
                if (!get_option('wpai_options')) {
                    $wpai_options = [];
                    if (wpai_check_mu_plugin()){
                        $wpai_options['mu_plugin'] = 1;
                    }
                    else {
                        $wpai_options['mu_plugin'] = 0; 
                    }                   
                                   
                    $wpai_options['wpai_score'] = '';
                    add_option('wpai_options', $wpai_options, 'yes');
                }
                else {
                    $wpai_options = get_option('wpai_options');
                    if (wpai_check_mu_plugin()){
                        $wpai_options['mu_plugin'] = 1;
                    }
                    else {
                        $wpai_options['mu_plugin'] = 0; 
                    }
                    update_option('wpai_options', $wpai_options, 'yes');  
                }
                add_action('add_option', 'gimme_your_options');
                add_action('update_option', 'gimme_your_options'); 

            } 
            function sample_admin_notice__success()
            {
                ?>
                  <div class="notice notice-success is-dismissible">
                  <h2><?php _e( "Hi, welcome to WordPress.", 'wpai' ); ?>  </h2> 
             <p><?php _e( "Don't forgot to delete the WP Installer", 'wpai')  ?> </p>
                 
                  <?php echo '<form action="options-general.php?page=wpai" method="post">';
                                wp_nonce_field('delete_mu-plugin');
                                echo '<input type="hidden" value="true" name="delete_mu-plugin" />';
                                submit_button('Delete Installer');
                                echo '</form>';
                                ?>
 <?php echo '<form action="options-general.php?page=wpai" method="post">';
wp_nonce_field('send_logs_action');
echo '<input type="hidden" value="true" name="send_logs" />';
submit_button('Send Logs');
echo '</form>';
?>
   

    </div>
                <?php
            }
                 add_action( 'admin_init', 'wpai_activate' );  
                 if (get_option('wpai_options')): 
            if (get_option('wpai_options')['mu_plugin'] == 1){
                add_action('admin_notices', 'sample_admin_notice__success');
            } 
        endif;        
                ?>
