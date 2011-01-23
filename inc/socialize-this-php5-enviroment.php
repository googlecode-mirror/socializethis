<?php
# This is the PHP 5 enviroment for Socialize This. It's a little more secure than the PHP4 enviroment and is what I mostly focus on working on.

class socialize_this {

    private $st_url, $st_imgs, $bitly;
    public $t; // Access twitter functions outside the functions. You can also make this private without worry.

    // __construct() - starts adding the admin menus

    public function __construct() {
        if (get_option('st_current_version') != ST_VERSION) { // Reset it.
            //$this->st_uninstall(TRUE);
            //$this->st_install(TRUE);
        }

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('publish_post', array($this, 'admin_submit_post'), 10, 1);
        add_action('admin_head', array($this, 'st_admin_jquery_css'), 10, 1);
        add_action('wp_head', array($this, 'runSocialGraph'));
        if (get_option('st_include_in_posts') == 'yes') {
            add_filter('comments_template', array($this, 'show_social_widgets'));
        }
        if (get_option('st_css_sheet') == TRUE) {
            wp_register_style('SocializeThis', WP_PLUGIN_URL . '/socialize-this/st.css');
            wp_enqueue_style('SocializeThis');
        }
        add_filter('plugin_row_meta', array($this, 'extra_plugin_links'), 10, 2);
        if (get_option('st_extend_php_limits') == TRUE) {
            @ini_set('memory_limit', '256M');
            @ini_set('max_execution_time', '60');
        }
        add_shortcode('socializethis', array($this, 'show_social_widgets'));
        $this->st_url = WP_PLUGIN_URL . '/socialize-this';

        $this->st_imgs = unserialize(get_option('st_imgs'));

        // Set up the Twitter Class
        $consumer_key = '0r2lXhprQxR5tY1XBZhc8g';
        $consumer_secret = 'q0BLVNV2kEtrKYSM4OiRqcYe5xxjUYuStBOwXVjj1ko';

        if (get_option('st_consumer_key') != '' && get_option('st_consumer_secret') != '') {
            $consumer_key = get_option('st_consumer_key');
            $consumer_secret = get_option('st_consumer_secret');
        }
        $this->t = new Twitter($consumer_key, $consumer_secret, get_option('st_oauth_token'), get_option('st_oauth_token_secret'));

        if (get_option('st_url_shortening_service') == 'bitly') {
            $this->bitly = new BitLy(get_option('st_bitly_user'), get_option('st_bitly_apikey'));
        }

        if (wp_get_schedule('updateSocialized') == FALSE) {
            wp_schedule_event(time(), 'hourly', 'updateSocialized');
        }
    }

    public function stCSS() {
        echo '<link rel="stylesheet" type="text/css" href="' . WP_PLUGIN_URL . '/socialize-this/st.css' . '" />';
    }

    public function extra_plugin_links($links, $file) {
        if ($file == ST_FILE) {
            $links[] = '<a href="options-general.php?page=socialize-this">' . __('Overview', 'st_plugin') . '</a>';
            $links[] = '<a href="options-general.php?page=socialize-this&module=social_widgets">' . __('Social Widgets', 'st_plugin') . '</a>';
        }
        return $links;
    }

    // admin_menu() - add's the admin menus
    public function admin_menu() {
        if (get_option('st_menu_page') == TRUE) {
            add_menu_page('Socialize This', 'Socialize This', 'install_plugins', 'socialize-this', array($this, 'socialize_this_settings'));
        } elseif (isset($_POST['st_menu_page'])) {
            if ($_POST['st_menu_page'] == 'yes') {
                add_menu_page('Socialize This', 'Socialize This', 'install_plugins', 'socialize-this', array($this, 'socialize_this_settings'));
            } else {
                add_options_page('Socialize This', 'Socialize This', 'manage_options', 'socialize-this', array($this, 'socialize_this_settings'));
            }
        } else {
            add_options_page('Socialize This', 'Socialize This', 'manage_options', 'socialize-this', array($this, 'socialize_this_settings'));
        }
    }

    public function st_admin_jquery_css() {
        global $plugin_page;
        if ($plugin_page == 'socialize-this') {
            wp_enqueue_script("jquery");
            echo '<link rel="stylesheet" type="text/css" href="' . $this->st_url . '/wp-admin.css' . '" />';
        }
    }

    // st_adm_header() - echos the Header HTML
    private function st_adm_header() {
?>
        <div id="icon-options-general" class="icon32"><br />
        </div>
        <h2>
<?php _e('Socialize This', 'st_plugin'); ?>
        (<?php echo ST_VERSION; ?><!-- / <?php echo get_option('st_current_version'); ?>-->)
    </h2>
    <div id="st_top_navagation"><a href="?page=socialize-this"><?php _e('Overview', 'st_plugin'); ?></a> |
        <a href="?page=socialize-this&module=settings"><?php _e('Settings', 'st_plugin'); ?></a> |
        <a href="?page=socialize-this&module=social_widgets"><?php _e('Social Widgets', 'st_plugin'); ?></a> |
        <a href="?page=socialize-this&module=open_graph"><?php _e('Open Graph', 'st_plugin'); ?></a> |
        <a href="?page=socialize-this&module=advanced_functions"><?php _e('Advanced Functions/Settings', 'st_plugin'); ?></a> |
        <a href="http://www.fullondesign.co.uk/projects/socialize-this/faq" target="_blank"><?php _e('FAQ\'s', 'st_plugin'); ?></a>
        </div>
<?php
    }

    private function st_adm_footer() {
        echo '<p>&nbsp;</p>'; // Space is sexy.
    }

    private function st_adm_donate() {
        if (get_option('st_hide_donate') != TRUE) {
            echo '<form action="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=5GKMJU4645GVY" method="post"  target="_blank">
<p class="submit"><input type="submit" name="submit" value="' . __('Support Socialize This - Donate via PayPal') . '" class="button-primary"></p>
</form>
';
        }
    }

    // st_adm_notice() - If their is a notice...display it!
    private function st_adm_notice($note='') {
        update_option('st_cached_time', time());
        if ($note != '') {
            echo '<div class="message">' . $note . '</div>';
        }
    }

    private function st_adm_general() {
?>
        <table width="90%" border="0" class="widefat post fixed">
            <thead>
                <tr>
                    <th width="45%" class="manage-column"><?php _e('Most Socialized Posts/Pages', 'st_plugin'); ?></th>
            <th class="manage-column">Tweets<!-- http://api.tweetmeme.com/stories/popular.php?url=http://www.example.com/ --></th>
            <th class="manage-column">Reddit<!-- http://www.reddit.com/api/info.json?url=http://i.imgur.com/F54Bb.jpg --></th>
            <th class="manage-column">Overall Social Score <!-- ((Tweets * 2 ) * (Reddits * (Comments * 2)) * Comments)--></th>
<?php /* <th class="manage-column">Diggs</th><!-- http://services.digg.com/stories?link=http%3A%2F%2Fstamen.com%2Fclients%2Fcabspotting&appkey=http%3A%2F%2Fexample.com%2Fapplication -->
          <th class="manage-column">Stumbles</th><!-- http://www.facebook.com/widgets/like.php?href=http://example.com -->
          <th class="manage-column">Facebook</th><!-- http://www.stumbleupon.com/badge/embed/3/?url=http://www.example.com/ --> */ ?>
            </tr>
        </thead>
    <?php
        $socialPosts = get_posts('numberposts=15&meta_key=st_social_score&orderby=meta_value_num&order=DESC&post_type=any');

        if ($socialPosts[0] == '') {
            $socialPosts = get_posts('numberposts=15&post_type=any');
        }

        foreach ($socialPosts as $socialPost) {
            $tweetmeme = unserialize(get_post_meta($socialPost->ID, 'st_tweetmeme', true));
            $reddit = unserialize(get_post_meta($socialPost->ID, 'st_reddit', true));
    ?>
            <tr>
                <td><a href="<?php echo get_permalink($socialPost->ID); ?>"><?php echo $socialPost->post_title; ?></a></td>
                <td>
                    <a href="<?php echo $tweetmeme['tm_link']; ?>"><?php echo $tweetmeme['url_count']; ?> Tweets</td>
                <td>
                    <a href="http://www.reddit.com/<?php echo $reddit['permalink']; ?>">Score <?php echo $reddit['score']; ?> (<?php echo $reddit['num_comments']; ?> Comments)</a></td>
                <td><?php echo get_post_meta($socialPost->ID, 'st_social_score', true); ?></td>
                </tr>
<?php } ?>
        </table>
<?php
        $this->st_adm_donate();
        $this->st_adm_news();
        $this->st_adm_credits();
    }

    private function st_adm_settings() {
        $note = NULL;
        if ($_GET['oauth_token'] != '' && $_GET['oauth_verifier'] != '' && get_option('st_noti_twitter') != 'authenticated') {
            if ($this->t->accessToken($_GET['oauth_verifier']) != FALSE) {
                update_option('st_oauth_token', (string) $this->t->oauth_token);
                update_option('st_oauth_token_secret', (string) $this->t->oauth_token_secret);
                update_option('st_oauth_results', '');
                update_option('st_noti_twitter', 'authenticated');
                update_option('st_noti_twitter_user', (string) $this->t->screen_name);
                $note .= '<p>' . __('Twitter Authentication Suscessful.') . '</p>';
            } else {
                $note .= '<p>' . __('Twitter Authentication Failed. Try Again.') . '</p>';
            }
        }
        if (isset($_POST['Submit'])) {
            update_option('st_include_in_posts', 'no');
            if ($_POST['include_in_posts'] == 'yes') {
                update_option('st_include_in_posts', 'yes');
            }

            update_option('st_url_shortening_service', $_POST['st_url_shortening_service']);
            if ($_POST['st_url_shortening_service'] == 'bitly') {
                $this->bitly = new BitLy($_POST['st_bitly_user'], $_POST['st_bitly_apikey']);
                if ($this->bitly->verifyBitLy() == TRUE) {
                    update_option('st_bitly_user', $_POST['st_bitly_user']);
                    update_option('st_bitly_apikey', $_POST['st_bitly_apikey']);
                    update_option('st_url_shortening_service', 'bitly');
                } else {
                    update_option('st_url_shortening_service', 'isgd');
                    update_option('st_bitly_user', NULL);
                    update_option('st_bitly_apikey', NULL);
                    $note .= '<p>' . __('Bit.ly Authentication Failed. Check your credentials') . '</p>';
                }
            } else {
                update_option('st_bitly_user', NULL);
                update_option('st_bitly_apikey', NULL);
            }

            if ($_POST['st_noti_twitter'] == 'deactivate') {
                update_option('st_noti_twitter', 'deactivated');
            }

            update_option('st_template_twitter_noti', $_POST['st_template_twitter_noti']);

            $note .= '<p>' . __('Settings Updated') . '</p>';
        }
        $this->st_adm_notice($note);
?>
        <br />
<?php if (!function_exists('curl_init')) { ?>
            <div class="message error"><?php _e('Note: Your server configuration does not allow cURL, people enable it to enable twitter updates.', 'st_plugin'); ?></div>
<?php } ?>
        <form action="" method="post" enctype="application/x-www-form-urlencoded">
            <table width="90%" border="0" class="widefat post fixed">
                <thead>
                    <tr>
                        <th width="40%" class="manage-column"><?php _e('General Settings', 'st_plugin'); ?></th>
                        <th width="60%" class="manage-column">&nbsp;</th>
                    </tr>
                </thead>
                <tr>
                    <td width="40%"><?php _e('Include Soical widgets before the comments template (normally after posts)', 'st_plugin'); ?></td>
                    <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                    <input type="checkbox" name="include_in_posts" id="checkbox" value="yes" <?php if (get_option('st_include_in_posts') == 'yes') { ?> checked="checked" <?php } ?>><br />
                    <em>
<?php _e('You can manually include the social widgets using the following code:', 'st_plugin'); ?>
                    </em></label><br />
                <em>
                    &lt;?php show_social_widgets(); ?&gt;</em></td>
        </tr>
    </table>
    <br />
    <table width="90%" border="0" class="widefat post fixed">
        <thead>
            <tr>
                <th width="40%" class="manage-column"><?php _e('URL Shortening Settings', 'st_plugin'); ?></th>
                <th width="60%" class="manage-column">&nbsp;</th>
            </tr>
        </thead>
        <tr>
            <td width="40%"><?php _e('URL Shortening Service', 'st_plugin'); ?></td>
            <td width="60%"><label>
                    <select name="st_url_shortening_service" class="st_url_shortening_service">
                        <option value="isgd"<?php if (get_option('st_url_shortening_service') == 'isgd' || get_option('st_url_shortening_service') == '') {
                            echo ' selected="selected"';
                        } ?>>is.gd</option>
                        <option value="trim"<?php if (get_option('st_url_shortening_service') == 'trim') {
                            echo ' selected="selected"';
                        } ?>>tr.im</option>
                        <option value="bitly"<?php if (get_option('st_url_shortening_service') == 'bitly') {
                            echo ' selected="selected"';
                        } ?>>bit.ly</option>
                            <option value="googl"<?php if (get_option('st_url_shortening_service') == 'googl') {
                            echo ' selected="selected"';
                        } ?>>goo.gl</option>
                        </select>
                    </label></td>
            </tr>
            <tr <?php if (get_option('st_url_shortening_service') != 'bitly') {/* echo ' class="hidden"'; */
                        } ?> id="bitly_usrpss">
                <td width="40%"><?php _e('Bit.ly Account Information', 'st_plugin'); ?>
                    <br />
                    <em>
<?php _e('Your Bit.Ly Username and API key.', 'st_plugin'); ?> <?php _e('This requires cURL.', 'st_plugin'); ?>
                    </em></td>
                <td width="60%">
                    <label>
<?php _e('Username:', 'st_plugin'); ?><br />
                        <input type="text" name="st_bitly_user" value="<?php echo get_option('st_bitly_user'); ?>" />
                    </label>
                    <br />
                    <label>
<?php _e('API Key:', 'st_plugin'); ?><br />
                        <input type="password" name="st_bitly_apikey" value="<?php echo get_option('st_bitly_apikey'); ?>" />
                    </label>
                </td>
            </tr>
        </table>
        <br />
        <table width="90%" border="0" class="widefat post fixed">
            <thead>
                <tr>
                    <th width="40%" class="manage-column"><?php _e('Twitter Settings', 'st_plugin'); ?></th>
                        <th width="60%" class="manage-column">&nbsp;</th>
                    </tr>
                </thead>
                <tr>
                    <td><?php _e('Notify Twitter when post is Published.', 'st_plugin'); ?>
                    <br />
                    <em>
                <?php _e('To activate this service, click the "Sign in with Twitter" button, then click "Allow".', 'st_plugin'); ?>
                            <br />
                <?php _e('This requires cURL.', 'st_plugin'); ?></em></td>
                    <td>
                <?php if (get_option('st_noti_twitter') == 'authenticated') {
                ?>
                            <p><?php _e('Screen Name', 'st_plugin'); ?>: <?php echo get_option('st_noti_twitter_user'); ?></p>
                            <label>
                <?php _e('Disable Service', 'st_plugin'); ?>: <input type="checkbox" name="st_noti_twitter" id="checkbox" value="deactivate">
                            </label>

                <?php
                        } else {
                            //if(get_option('st_oauth_results') == '' || get_option('st_oauth_updated') <= mktime(date("H"),  date("i")-10, 0, date("m")  , date("d"), date("Y"))){
                            if ($this->t->requestToken(get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=socialize-this&module=settings') != FALSE) {
                                update_option('st_oauth_token', $this->t->oauth_token);
                                update_option('st_oauth_token_secret', $this->t->oauth_token_secret);
                                update_option('st_noti_twitter', 'pending');
                                update_option('st_oauth_results', (string) $this->t->results);
                                update_option('st_oauth_updated', time());
                            }
                            //}
                            if (get_option('st_oauth_results') != '') {
                ?>
                            <a href="http://api.twitter.com/oauth/authorize?<?php echo get_option('st_oauth_results'); ?>" target="_blank">
                                <img src="<?php echo $this->st_url; ?>/sign-in-with-twitter-l.png" alt="<?php _e('Sign in with Twitter:', 'st_plugin'); ?> " />
                            </a>
<?php
                            } else {
                                _e('Error talking via OAuth to twitter:', 'st_plugin');
                            }
                        }
?></td>
            </tr>
            <tr>
                <td><?php _e('Twitter\'s New Post Status Update Template', 'st_plugin'); ?>
                    <br />
                    <em>
<?php _e('For a full list of avialable tags see the ', 'st_plugin'); ?> <a href="?page=socialize-this&module=tagList"><?php _e('Tag List ', 'st_plugin'); ?></a>
                                        </em>
                                    </td>
                                    <td><label>
                                            <textarea name="st_template_twitter_noti" cols="70%" rows="2"><?php echo get_option('st_template_twitter_noti'); ?></textarea>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Settings', 'st_plugin'); ?>" />
                            </p>
                        </form>
<?php
                    }

                    private function st_get_widget_sets() {
                        # taken from http://www.laughing-buddha.net/jon/php/dirlist/
                        $handler = opendir(ST_FOLER . '/widgets/'); // Open the folder and look for widget sets.
                        while ($file = readdir($handler)) {
                            // if $file isn't this directory or its parent,
                            // add it to the results array
                            if ($file != '.' && $file != '..') {
                                if (preg_match('#.stset.xml#is', $file)) {
                                    $results[] = $file;
                                }
                            }
                        }

                        // tidy up: close the handler
                        closedir($handler);
                        unset($handler, $file);

                        return $results;
                    }

                    private function st_adm_edit_widget() {
?>
                <form action="?page=socialize-this&module=social_widgets" method="post" enctype="application/x-www-form-urlencoded">

                    <table width="90%" border="0" class="widefat post fixed">
                        <thead>
                            <tr>
                                <th width="25%" class="manage-column"><?php _e('Edit Widget', 'st_plugin'); ?></th>
                                <th class="manage-column">&nbsp;</th>
                            </tr>
                        </thead>
<?php foreach ($this->getWidgets(NULL, NULL, (int) $_GET['widget']) as $widget) { ?>
                            <tr>
                                <td><?php _e('Name', 'st_plugin'); ?>:</td>
                                <td><input type="text" name="name" value="<?php echo $widget['name']; ?>"  /></td>
                            </tr>
                            <tr>
                                <td><?php _e('Author', 'st_plugin'); ?>:</td>
                                <td><input type="text" name="author" value="<?php echo $widget['author']; ?>" /></td>
                            </tr>
                            <tr>
                                <td><?php _e('Author URL', 'st_plugin'); ?>:</td>
                                <td><input type="text" name="author_url" value="<?php echo $widget['author_url']; ?>"  /></td>
                            </tr>
                            <tr>
                                <td><?php _e('HTML', 'st_plugin'); ?> (<a href="?page=socialize-this&module=tagList"><?php _e('Tag List', 'st_plugin'); ?></a>):</td>
                                <td><textarea name="html"><?php echo $widget['html']; ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <td><?php _e('Enabled', 'st_plugin'); ?>:</td>
                                        <td><input name="enabled" type="checkbox" value="2" <?php if ($widget['enabled'] == '2') {
                                echo 'checked';
                            } ?> /></td>
                                    </tr>
                                    <input type="hidden" name="id" value="<?php echo $widget['ID']; ?>" /><input type="hidden" name="position" value="<?php echo $widget['position']; ?>" />
<?php } ?>
                            </table>

                            <p class="submit">
                                <input type="submit" name="edit_widget" class="button-primary" value="<?php _e('Edit Widget', 'st_plugin'); ?>" />
                            </p>

                        </form>
<?php
                        $this->st_adm_tagList();
                    }

                    private function st_adm_social_widgets() {
                        if (isset($_POST['add_widget'])) {
                            $this->addWidget($_POST['author'], $_POST['html'], $_POST['name'], $_POST['author_url']);
                            $note .= '<p>' . __('Widget Added') . '</p>';
                        } elseif (isset($_POST['add_set'])) {
                            $count = 0;
                            if (is_array($_POST['set_file'])) {
                                foreach ($_POST['set_file'] as $set_file) {
                                    $count = $count + ($this->addWidgetSet($set_file));
                                }
                            }
                            if ($count >= 1) {
                                $note .= '<p>Widget Set (' . $count . ' Widgets) Added</p>';
                            } else {
                                $note .= '<p>' . __('Widget Set Failed to add') . '.</p>';
                            }
                        } elseif (isset($_POST['edit_widget'])) {
                            if (is_numeric($_POST['id'])) {
                                $enabled = 2;
                                if ($_POST['enabled'] != 2) {
                                    $enabled = 1;
                                }
                                $this->updateWidget($_POST['id'], stripslashes($_POST['html']), $_POST['position'], $enabled);
                                $note .= '<p>' . __('Widget Updated') . '</p>';
                            }
                        } elseif (isset($_POST['save_widgets'])) {
                            foreach ($_POST['position'] as $id => $position) {
                                $this->updateWidget($id, NULL, $position, $_POST['enabled'][$id]);
                            }
                            $note .= '<p>' . __('Widgets Updated') . '</p>';
                        } elseif (isset($_GET['delete_widget'])) {
                            $this->deleteWidget((int) $_GET['delete_widget']);
                            $note .= '<p>' . __('Widgets Deleted') . '</p>';
                        }


                        $this->st_adm_notice($note);
?>
            <h4>
<?php _e('Social Widgets (Drag and Drop)', 'st_plugin'); ?>
            </h4>
            <form action="#" method="post" enctype="application/x-www-form-urlencoded">
                <table width="90%" border="0" class="widefat post fixed">
                    <thead>
                        <tr>
                            <th width="13%" class="manage-column"><?php _e('Position', 'st_plugin'); ?></th>
                            <th class="manage-column"><?php _e('Example', 'st_plugin'); ?></th>
                            <th width="25%" class="manage-column"><?php _e('Name (Author)', 'st_plugin'); ?></th>
                            <th width="10%" class="manage-column"><?php _e('Enabled', 'st_plugin'); ?></th>
                            <th width="10%" class="manage-column"><?php _e('Edit', 'st_plugin'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="widgets">
<?php
                        $vars = $this->getTags(NULL, 'http://www.example.com/', 'Example');
                        $vars = apply_filters('st-tags', $vars);
                        $widgets = $this->getWidgets();
                        foreach ($widgets as $widget) {
?>
                                <tr id="<?php echo $widget['ID']; ?>" <?php if ($widget['enabled'] == '2') {
                                echo 'class="enabled"';
                            } else {
                                echo 'class="disabled"';
                            } ?> >
                                    <td class="dragable"><input type="text" disabled class="position" name="position[<?php echo $widget['ID']; ?>]" value="<?php echo $widget['position']; ?>" /></td>
                                    <td class="dragable"><?php echo $this->sprintf4($widget['html'], $vars); ?></td>
                                    <td><?php echo $widget['name']; ?> (<a href="<?php echo $widget['author_url']; ?>"><?php echo $widget['author']; ?></a>)</td>
                                    <td><input class="updateEnabled" title="<?php echo $widget['ID']; ?>" name="enabled[<?php echo $widget['ID']; ?>]" type="checkbox" value="2" <?php if ($widget['enabled'] == '2') {
                                echo 'checked';
                            } ?> /></td>
                                    <td><a href="?page=socialize-this&module=edit_widget&widget=<?php echo $widget['ID']; ?>"><?php _e('Edit', 'st_plugin'); ?></a> / <a href="?page=socialize-this&module=social_widgets&delete_widget=<?php echo $widget['ID']; ?>"><?php _e('Delete', 'st_plugin'); ?></a></td>
                                </tr>
<?php } ?>
                        </tbody>
                    </table>
                </form>
                <form action="" method="post" enctype="application/x-www-form-urlencoded">
                    <div id="real_values"><?php foreach ($widgets as $widget) { ?>
                            <input type="hidden" class="position_<?php echo $widget['ID']; ?>" name="position[<?php echo $widget['ID']; ?>]" value="<?php echo $widget['position']; ?>" />
                            <input type="hidden" class="enabled_<?php echo $widget['ID']; ?>" name="enabled[<?php echo $widget['ID']; ?>]" value="<?php echo $widget['enabled']; ?>" />
<?php } ?></div>
                    <p class="submit">
                        <input type="submit" name="save_widgets" class="button-primary" value="<?php _e('Save Widget Settings', 'st_plugin'); ?>" />
                    </p>
                </form>
                <script type="text/javascript" src="<?php echo $this->st_url; ?>/inc/jquery-1.4.2.min.js"></script>
                <script type="text/javascript" src="<?php echo $this->st_url; ?>/inc/jquery.tablednd_0_5.js"></script>
                <script type="text/javascript">
                    //<![CDATA[

                    function updatePositions(){
                        var rows = $("#widgets tr");
                        for (var i=0; i<rows.length; i++) {
                            $('#widgets tr#'+rows[i].id+' input.position').val(i+1);
                            $('#real_values input.position_'+rows[i].id).val(i+1);

                        }
                    }

                    updatePositions();

                    $(".updateEnabled").click(function() {
                        if($(this).attr('checked') == true){
                            $('#real_values input.enabled_'+$(this).attr('title')).val(2);
                            $('#'+$(this).attr('title')).removeClass('disabled');
                        } else {
                            $('#real_values input.enabled_'+$(this).attr('title')).val(1);
                            $('#'+$(this).attr('title')).addClass('disabled');
                        }
                    });
                    $("#widgets").tableDnD({
                        onDragClass: 'onDrag',
                        onDrop: function(table, row) {
                            updatePositions();
                        }
                    });
                    //]]>
                </script>
                <table width="90%" border="0" class="widefat post fixed">
                    <thead>
                        <tr>
                            <th width="25%" class="manage-column"><?php _e('Add Widget', 'st_plugin'); ?></th>
                            <th class="manage-column">&nbsp;</th>
                        </tr>
                    </thead>
                    <form action="" method="post" enctype="application/x-www-form-urlencoded">
                        <tr>
                            <td><?php _e('Name', 'st_plugin'); ?>:</td>
                                <td><input type="text" name="name" /></td>
                            </tr>
                            <tr>
                                <td><?php _e('Author', 'st_plugin'); ?>:</td>
                                <td><input type="text" name="author" /></td>
                            </tr>
                            <tr>
                                <td><?php _e('Author URL', 'st_plugin'); ?>:</td>
                            <td><input type="text" name="author_url" /></td>
                        </tr>
                        <tr>
                            <td><?php _e('HTML', 'st_plugin'); ?> (<a href="?page=socialize-this&module=tagList"><?php _e('Tag List', 'st_plugin'); ?></a>):</td>
                            <td><textarea name="html"></textarea></td>
                        </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="add_widget" class="button-primary" value="<?php _e('Add Widget', 'st_plugin'); ?>" />
        </p>

        </form>

        <h4>
        <?php _e('Add Widget Set', 'st_plugin'); ?>
                </h4>
                <form action="" method="post" enctype="application/x-www-form-urlencoded">

                    <table width="90%" border="0" class="widefat post fixed">
                        <thead>
                            <tr>
                                <th class="manage-column"><?php _e('Widget Set', 'st_plugin'); ?></th>
                                        <th width="25%"class="manage-column"><?php _e('Author', 'st_plugin'); ?></th>
                                        <th width="20%" class="manage-column"><?php _e('Install', 'st_plugin'); ?></th>
                                    </tr>
                                </thead>
<?php
                        foreach ($this->st_get_widget_sets() as $set_file) {
                            $widget_set = simplexml_load_file(ST_FOLER . 'widgets/' . $set_file);
                            if (is_object($widget_set)) {
?>
                                        <tr>
                                            <td><?php
                                echo $widget_set->name;
                                if ($widget_set->description != '') {
                                    echo ' - ' . $widget_set->description;
                                }
                                if ($widget_set->preview != '') {
 ?><br />
                                                    <img src="<?php echo $this->st_url . '/widgets/' . $widget_set->preview; ?>" alt="<?php echo $widget_set->name; ?>" />
<?php } ?>
                                            </td>
                                            <td><a href="<?php echo $widget_set->author_url; ?>"><?php echo $widget_set->author; ?></a></td>
                                            <td><label><input name="set_file[]" type="checkbox" value="<?php echo $set_file; ?>" /> - <?php echo $set_file; ?></label></td>
                                        </tr>
<?php }
                        } ?>
                            </table>
                            <p class="submit"><input type="submit" name="add_set" class="button-primary" value="<?php _e('Add Widget Set', 'st_plugin'); ?>" /></p>
                        </form>

<?php
                    }

                    private function st_adm_open_graph() {
                        if (isset($_POST['save_open_graph'])) {
                            foreach (array('type', 'url', 'title') as $value) {
                                if ($_POST['use' . $value] == 'yes') {
                                    update_option('st_og_use' . $value, FALSE);
                                } else {
                                    update_option('st_og_use' . $value, TRUE);
                                }
                            }
                            foreach (array('title', 'type', 'image', 'url', 'site_name', 'admins', 'app_id', 'description', 'othermeta') as $value) {
                                update_option('st_og_' . $value, $_POST[$value]);
                                if ($_POST[$value . '_enabled'] == 'ticked') {
                                    update_option('st_og_' . $value . '_enabled', TRUE);
                                } else {
                                    update_option('st_og_' . $value . '_enabled', FALSE);
                                }
                            }

                            $this->st_adm_notice('Open Graph Settings Updated');
                        } // TODO
?>
    <h4><?php _e('Open Graph', 'st_plugin'); ?></h4>
    <p><?php _e('The Open Graph protocol defines how you page will show up on Facebook. It is useful to fill these out even if you don\'t use the "Like" button.', 'st_plugin'); ?>
        <a href="http://developers.facebook.com/docs/opengraph"><?php _e('For more information check out the Open Graph protocol developer page', 'st_plugin'); ?></a>.</p>
    <form action="#" method="post" enctype="application/x-www-form-urlencoded">
        <table width="90%" border="0" class="widefat post fixed">
            <thead>
                <tr>
                    <th width="30%" class="manage-column"><?php _e('Meta Value', 'st_plugin'); ?></th>
                    <th class="manage-column"><?php _e('Property', 'st_plugin'); ?> / <?php _e('Description', 'st_plugin'); ?></th>
                    <th width="10%" class="manage-column"><?php _e('Enabled', 'st_plugin'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="textbox" name="title" id="ogtitle" value="<?php echo get_option('st_og_title'); ?>" placeholder="Title" <?php if (get_option('st_og_usetitle') == FALSE) {
                            echo 'disabled="disabled" class="disabled"';
                        } ?> /><br />
<?php _e('or', 'st_plugin'); ?> <input type="checkbox" name="usetitle" id="useogtitle" twin="ogtitle" value="yes" <?php if (get_option('st_og_usetitle') == FALSE) {
                            echo 'checked="checked"';
                        } ?> /> <?php _e('Use page/post Title.', 'st_plugin'); ?></td>
                    <td><?php _e('Title', 'st_plugin'); ?><br /><em><?php _e('This is required.', 'st_plugin'); ?></em> <?php _e('This is the title you want to appear on facebook.', 'st_plugin'); ?></td>
                    <td><input type="checkbox" name="title_enabled" value="ticked" <?php if (get_option('st_og_title_enabled')) {
                            echo 'checked="checked"';
                        } ?> /></td>
                </tr>
                <tr>
                    <td><input type="textbox" name="type" value="<?php echo get_option('st_og_type'); ?>" id="ogtype" placeholder="Type" <?php if (get_option('st_og_usetype') == FALSE) {
                            echo 'disabled="disabled" class="disabled"';
                        } ?> /><br />
<?php _e('or', 'st_plugin'); ?> <input type="checkbox" name="usetype" id="useogtype" twin="ogtype" value="yes" <?php if (get_option('st_og_usetype') == FALSE) {
                            echo 'checked="checked"';
                        } ?>  /> <?php _e('Use "article" for blog posts and "blog" for pages?', 'st_plugin'); ?></td>
                    <td><?php _e('Type', 'st_plugin'); ?><br /><em><?php _e('This is required.', 'st_plugin'); ?></em> <?php _e('The type of object the user is "liking". It must be from the', 'st_plugin'); ?> <a href="http://developers.facebook.com/docs/opengraph#types"><?php _e('Object types list', 'st_plugin'); ?></a>.</td>
                    <td><input type="checkbox" name="type_enabled" value="ticked" <?php if (get_option('st_og_type_enabled')) {
                            echo 'checked="checked"';
                        } ?> /></td>
                </tr>
                <tr>
                    <td><input type="textbox" name="image" value="<?php echo get_option('st_og_image'); ?>" placeholder="http://www.site.com/image.png" /></td>
                    <td><?php _e('Image URL', 'st_plugin'); ?><br /><em><?php _e('This is required.', 'st_plugin'); ?></em> <?php _e('An image which represents you site, e.g. your logo', 'st_plugin'); ?>.</td>
                    <td><input type="checkbox" name="image_enabled" value="ticked" <?php if (get_option('st_og_image_enabled')) {
                            echo 'checked="checked"';
                        } ?> /></td>
                </tr>
                <tr>
                    <td><input type="textbox" name="url" value="<?php echo get_option('st_og_url'); ?>" id="ogurl" placeholder="http://www.site.com/" <?php if (get_option('st_og_useurl') == FALSE) {
                            echo 'disabled="disabled" class="disabled"';
                        } ?>  /><br />
<?php _e('or', 'st_plugin'); ?> <input type="checkbox" name="useurl"  id="useogurl" twin="ogurl"  value="yes" <?php if (get_option('st_og_useurl') == FALSE) {
                            echo 'checked="checked"';
                        } ?>   /> <?php _e('Use page/post URL.', 'st_plugin'); ?></td>
                    <td><?php _e('URL', 'st_plugin'); ?><br /><em><?php _e('This is required.', 'st_plugin'); ?></em> <?php _e('The URL the user will be "liking".', 'st_plugin'); ?></td>
                    <td><input type="checkbox" name="url_enabled" value="ticked" <?php if (get_option('st_og_url_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                    <tr>
                                        <td><input type="textbox" name="site_name" value="<?php echo get_option('st_og_site_name'); ?>" placeholder="Site Name" /></td>
                                        <td><?php _e('Site Name', 'st_plugin'); ?></td>
                                        <td><input type="checkbox" name="site_name_enabled" value="ticked" <?php if (get_option('st_og_site_name_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                    <tr>
                                        <td><input type="textbox" name="admins" value="<?php echo get_option('st_og_admins'); ?>" placeholder="10101010101,010101011" /></td>
                                        <td><?php _e('Admins', 'st_plugin'); ?></td>
                                        <td><input type="checkbox" name="admins_enabled" value="ticked" <?php if (get_option('st_og_admins_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                    <tr>
                                        <td><input type="textbox" name="app_id" value="<?php echo get_option('st_og_app_id'); ?>" placeholder="1010101000" /></td>
                                        <td><?php _e('App ID', 'st_plugin'); ?></td>
                                        <td><input type="checkbox" name="app_id_enabled" value="ticked" <?php if (get_option('st_og_app_id_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                    <tr>
                                        <td><input type="textbox" name="description" value="<?php echo get_option('st_og_description'); ?>" placeholder="Description" /></td>
                                        <td><?php _e('Description', 'st_plugin'); ?></td>
                                        <td><input type="checkbox" name="description_enabled" value="ticked" <?php if (get_option('st_og_description_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                    <tr>
                                        <td><textarea name="othermeta" placeholder='<meta property="og:region" content="CA"/>

                                                      <meta property="og:country-name" content="USA"/>' ><?php echo get_option('st_og_othermeta'); ?></textarea></td>
                                        <td><?php _e('Other Open Graph Meta Data', 'st_plugin'); ?></td>
                                        <td><input type="checkbox" name="othermeta_enabled" value="ticked" <?php if (get_option('st_og_othermeta_enabled')) {
                            echo 'checked="checked"';
                        } ?>  /></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p><?php _e('Once you are happy with your Open Graph settings, you may want to check them with the', 'st_plugin'); ?> <a href="http://developers.facebook.com/tools/lint"><?php _e('URL Linter', 'st_plugin'); ?></a> <?php _e('tool', 'st_plugin'); ?>.</p>
                            <p class="submit">
                                <input type="submit" name="save_open_graph" class="button-primary" value="<?php _e('Save Open Graph Settings', 'st_plugin'); ?>" />
                            </p>
                        </form>
                        <script type="text/javascript" src="<?php echo $this->st_url; ?>/inc/jquery-1.4.2.min.js"></script>
                        <script type="text/javascript" src="<?php echo $this->st_url; ?>/inc/jquery.tablednd_0_5.js"></script>
                        <script type="text/javascript">
                            //<![CDATA[
                            $('#useogurl,#useogtype,#useogtitle').click(function() {
                                //alert('Handler for '+($(this).attr('twin'))+' '+($(this).attr('checked'))+' called.');
                                if($(this).attr('checked') == false){
                                    $("#"+$(this).attr('twin')).attr('disabled', false);
                                    $("#"+$(this).attr('twin')).removeClass('disabled');
                                }else{
                                    $("#"+$(this).attr('twin')).attr('disabled', 'disabled');
                                    $("#"+$(this).attr('twin')).addClass('disabled');
                                }
                            });
                            //]]>
                        </script>
<?php
                    }

                    private function st_adm_news() {
                        if (get_option('st_enable_api') == TRUE) {
                            $news = $this->get_link('http://www.fullondesign.co.uk/projects/socialize-this/api/news');
                            if ($news != '') {
                                echo $news;
                            }
                        }
                    }

                    private function st_adm_credits() {
                        # @Rogem002 | Mike Rogers | http://www.fullondesign.co.uk/
                    }

                    private function st_adm_advanced_functions() {
                        if (isset($_POST['save_advanced_settings'])) {
                            if ($_POST['st_enable_api'] == 'yes') {
                                update_option('st_enable_api', TRUE);
                            } else {
                                update_option('st_enable_api', FALSE);
                            }
                            if ($_POST['st_hide_donate'] == 'yes') {
                                update_option('st_hide_donate', TRUE);
                            } else {
                                update_option('st_hide_donate', FALSE);
                            }
                            if ($_POST['st_extend_php_limits'] == 'yes') {
                                update_option('st_extend_php_limits', TRUE);
                            } else {
                                update_option('st_extend_php_limits', FALSE);
                            }
                            if ($_POST['st_css_sheet'] == 'yes') {
                                update_option('st_css_sheet', FALSE, TRUE);
                            } else {
                                update_option('st_css_sheet', TRUE, TRUE);
                            }
                            if ($_POST['st_cache'] == 'yes') {
                                update_option('st_cache', TRUE, TRUE);
                            } else {
                                update_option('st_cache', FALSE, TRUE);
                            }
                            if ($_POST['st_menu_page'] == 'yes') {
                                update_option('st_menu_page', TRUE, TRUE);
                            } else {
                                update_option('st_menu_page', FALSE, TRUE);
                            }
                            update_option('st_consumer_key', $_POST['st_consumer_key']);
                            update_option('st_consumer_secret', $_POST['st_consumer_secret']);
                            update_option('st_twitter_apikey', $_POST['st_twitter_apikey']);
                            $note .= __('Advanced Settings Updated');
                        }
                        if (isset($_POST['regenerate_urls'])) {
                            global $wpdb;
                            $posts = get_posts('numberposts=-1&post_type=any');
                            if (is_array($posts)) {
                                foreach ($posts as $post) {
                                    $permalink = get_permalink($post->ID);
                                    update_post_meta($post->ID, 'st_tiny_url', $this->shorten_url($permalink));
                                    //sleep(1); // Sleep for a few seconds so we don't overload the url shortening system.
                                }
                            }
                            $note .= __('URL\'s ReGenerated');
                        }
                        if (isset($_POST['update_socialized'])) {
                            $this->updateSocialized(4);
                            $note .= __('Updated Socialized Data');
                        }
                        if (isset($_POST['update_all_socialized'])) {
                            $this->updateSocialized(FALSE);
                            $note .= __('Updated Socialized Data');
                        }
                        if (isset($_POST['reset'])) {
                            $this->st_uninstall();
                            $this->st_install();
                            $note .= __('Setting\'s Reset');
                        }
                        if (isset($_POST['test_tweet'])) {
                            $this->t->updateStatus('This is a test tweet.');
                        }
                        $this->st_adm_notice($note);
?>
                    <form action="" method="post" enctype="application/x-www-form-urlencoded">
                        <table width="90%" border="0" class="widefat post fixed">
                            <thead>
                                <tr>
                                    <th width="40%" class="manage-column"><?php _e('Advanced Settings', 'st_plugin'); ?></th>
                                    <th width="60%" class="manage-column">&nbsp;</th>
                                </tr>
                            </thead>
                            <tr>
                                <td width="40%"><?php _e('Enable Full On Design API', 'st_plugin'); ?></td>
                                <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                        <input type="checkbox" name="st_enable_api" id="checkbox" value="yes" <?php if (get_option('st_enable_api') == TRUE) { ?> checked="checked" <?php } ?>>
                                    </label></td>
                            </tr>
                            <tr>
                                <td width="40%"><?php _e('Hide Donation Button', 'st_plugin'); ?></td>
                                    <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                            <input type="checkbox" name="st_hide_donate" id="checkbox" value="yes" <?php if (get_option('st_hide_donate') == TRUE) {
 ?> checked="checked" <?php } ?>>
                                        </label></td>
                                </tr>
                                <tr>
                                    <td width="40%"><?php _e('Attempt to extend PHP max_execution_time and memory_limit', 'st_plugin'); ?><!-- This was added because I sometimes have problems upgrading plugins. This should help. --></td>
                                <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                        <input type="checkbox" name="st_extend_php_limits" id="checkbox" value="yes" <?php if (get_option('st_extend_php_limits') == TRUE) { ?> checked="checked" <?php } ?>>
                                    </label></td>
                            </tr>
                            <tr>
                                <td width="40%"><?php _e('Disable Socialize This CSS Sheet', 'st_plugin'); ?></td>
                                <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                        <input type="checkbox" name="st_css_sheet" id="checkbox" value="yes" <?php if (get_option('st_css_sheet') == FALSE) { ?> checked="checked" <?php } ?>>
                                    </label></td>
                            </tr>
                            <tr>
                                <td width="40%"><?php _e('Disable Socialize This Cache', 'st_plugin'); ?></td>
                                <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                        <input type="checkbox" name="st_cache" id="checkbox" value="yes" <?php if (get_option('st_cache') == TRUE) { ?> checked="checked" <?php } ?>>
                                    </label></td>
                            </tr>
                            <tr>
                                <td width="40%"><?php _e('Give Socialize This a big admin link', 'st_plugin'); ?></td>
                                <td width="60%"><label><?php _e('Enable:', 'st_plugin'); ?>
                                        <input type="checkbox" name="st_menu_page" id="checkbox" value="yes" <?php if (get_option('st_menu_page') == TRUE) { ?> checked="checked" <?php } ?>>
                                    </label></td>
                            </tr>
                        </table>
                        <br />
                        <table width="90%" border="0" class="widefat post fixed">
                            <thead>
                                <tr>
                                    <th width="40%" class="manage-column"><?php _e('Advanced Twitter Settings', 'st_plugin'); ?></th>
                                    <th width="60%" class="manage-column">&nbsp;</th>
                                </tr>
                            </thead>
                            <tr>
                                <td width="40%"><?php _e('OAuth Consumer Key', 'st_plugin'); ?></td>
                                <td width="60%"><label>
                                        <input type="text" name="st_consumer_key" value="<?php echo get_option('st_consumer_key'); ?>">
                                    </label></td>
                            </tr>
                            <tr>
                                <td width="40%"><?php _e('OAuth Consumer Secret', 'st_plugin'); ?></td>
                                <td width="60%"><label>
                                        <input type="text" name="st_consumer_secret" value="<?php echo get_option('st_consumer_secret'); ?>">
                                    </label></td>
                            </tr>
                            <tr>
                                <td><?php _e('@Anywhere API Key', 'st_plugin'); ?>
                                    <br />
                                    <em>
<?php _e('This will enable Widgets to use your @Anywhere key (%anywhere%)', 'st_plugin'); ?>
                                    </em></td>
                                <td><label>
                                        <input type="text" name="st_twitter_apikey" value="<?php echo get_option('st_twitter_apikey'); ?>" />
                                    </label>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" name="save_advanced_settings" class="button-primary" value="Save Advanced Settings" />
                        </p>
                    </form>
                    <h3>
<?php _e('Advanced Functions', 'st_plugin'); ?>
                    </h3>
                    <form action="" method="post" enctype="application/x-www-form-urlencoded">
                        <p><input type="submit" name="regenerate_urls" class="button-primary" value="ReGenerate Shortened URL's" /> - <?php _e('May take a while or timeout.', 'st_plugin'); ?></p>
                    </form>
                    <form action="" method="post" enctype="application/x-www-form-urlencoded">
                        <p><input type="submit" name="reset" class="button-primary" value="Reset Socialize This" /> - <?php _e('Resets Socialize This\'s Settings.', 'st_plugin'); ?></p>
                    </form>
                    <form action="" method="post" enctype="application/x-www-form-urlencoded">
                        <p><input type="submit" name="update_socialized" class="button-primary" value="Run Socialized Cron" /> - <?php _e('Runs the Cron which updates the Socialized Data.', 'st_plugin'); ?></p>
                        </form>
                        <form action="" method="post" enctype="application/x-www-form-urlencoded">
                            <p><input type="submit" name="update_all_socialized" class="button-primary" value="Run Socialized Cron on all posts" /> - <?php _e('Runs the Cron which updates the Socialized Data for all posts (May Crash/timeout).', 'st_plugin'); ?></p>
                        </form>
                        <form action="" method="post" enctype="application/x-www-form-urlencoded">
                            <p><input type="submit" name="test_tweet" class="button-primary" value="Send Test Tweet" /> - <?php _e('Check that twitter is recieving tweets.', 'st_plugin'); ?></p>
                        </form>

<?php
                    }

                    private function st_adm_tagList() {
?>
                        <h4>
<?php _e('Tag List', 'st_plugin'); ?>
                        </h4>
                        <table border="0"  class="widefat post fixed">
                            <thead>
                                <tr>
                                    <th width="30%" class="manage-column"><?php _e('Tag', 'st_plugin'); ?></th>
                                    <th width="70%" class="manage-column"><?php _e('Description', 'st_plugin'); ?></th>
                                </tr>
                            </thead>
                            <tr>
                                <td>%permalink%</td>
                                <td><?php _e('The permalink to the post (URL Encoded)', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%raw_permalink%</td>
                                <td><?php _e('The permalink to the post', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%short_url%</td>
                                <td><?php _e('The shortened URL of your permalink. Generated on Publish, if short url is not present permalink will be used. (URL Encoded)', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%raw_short_url%</td>
                                <td><?php _e('Same as %short_url% but not URL Encoded.', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%title%</td>
                                <td><?php _e('The title of the post (URL Encoded)', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%raw_title%</td>
                                <td><?php _e('The title of the post', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%descirption%</td>
                                <td><?php _e('The Excerpt of the post. If the Except does not exist, the title will double up as the descirption. (URL Encoded)', 'st_plugin'); ?></td>
                            </tr>

                            <tr>
                                <td>%rss_url%</td>
                                <td><?php _e('Your RSS URL', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%anywhere%</td>
                                <td><?php _e('@Anywhere API Key', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%twitter_name%</td>
                                <td><?php _e('Twitter Username', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%raw_tags%</td>
                                <td><?php _e('A list of the tags with # added before them.', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%tags%</td>
                                <td><?php _e('URL encoded version of %raw_tags%', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%raw_date_posted%</td>
                                <td><?php _e('The date the post was published.', 'st_plugin'); ?></td>
                            </tr>
                            <tr>
                                <td>%date_posted%</td>
                                <td><?php _e('URL encoded version of %raw_date_posted%', 'st_plugin'); ?></td>
                            </tr>

                            <tr>
                                <td>%widgets_url%</td>
                                <td><?php _e('The URL of the /socialize-this/widgets folder.', 'st_plugin'); ?></td>
                            </tr>
                        </table>
<?php
                    }

                    // socialize_this_settings() - The admin settings page
                    public function socialize_this_settings() {
                        echo '<div class="wrap">';
                        $this->st_adm_header();
                        if (isset($_GET['module'])) {
                            switch ($_GET['module']) {
                                case 'social_widgets': $this->st_adm_social_widgets();
                                    break;
                                case 'edit_widget': $this->st_adm_edit_widget();
                                    break;
                                case 'templates': $this->st_adm_templates();
                                    break;
                                case 'credits': $this->st_adm_credits();
                                    break;
                                case 'open_graph': $this->st_adm_open_graph();
                                    break;
                                case 'advanced_functions': $this->st_adm_advanced_functions();
                                    break;
                                case 'settings': $this->st_adm_settings();
                                    break;
                                case 'tagList': $this->st_adm_tagList();
                                    break;
                                default: $this->st_adm_general();
                            }
                        } else {
                            $this->st_adm_general();
                        }
                        $this->st_adm_footer();
                        echo '</div>';
                    }

                    private function getTags($post=NULL, $permalink=NULL, $title=NULL) {
                        // Set up blank data.
                        $vars = array('%raw_short_url%' => '', '%short_url%' => '', '%descirption%' => '', '%descirption%' => '',
                            '%raw_short_url%' => '', '%short_url%' => '', '%raw_tags%' => '', '%tags%' => '', '%raw_date_posted%' => '', '%date_posted%' => '');

                        // Set up some of the custom data
                        if ($permalink != NULL) {
                            $vars['%raw_short_url%'] = $permalink;
                            $vars['%short_url%'] = urlencode($permalink);
                        }

                        if (is_numeric($post)) {
                            $post = get_post($post);
                        }
                        if (is_object($post)) {
                            $permalink = get_permalink($post->ID);
                            $title = $post->post_title;
                            if ($descirption != '') {
                                $vars['%descirption%'] = $post->post_excerpt;
                            } else {
                                $vars['%descirption%'] = $post->post_title;
                            }

                            $vars['%raw_short_url%'] = get_post_meta($post->ID, 'st_tiny_url', TRUE);
                            $vars['%short_url%'] = urlencode($vars['%raw_short_url%']);
                            if ($vars['%short_url%'] == '') {
                                $vars['%raw_short_url%'] = $permalink;
                                $vars['%short_url%'] = urlencode($permalink);
                            }

                            $posttags = get_the_tags($post->ID);
                            if (is_array($posttags)) {
                                foreach ($posttags as $posttag) {
                                    $tags[] = '#' . str_replace(' ', '', $posttag->name);
                                }
                                $vars['%raw_tags%'] = implode(' ', $tags);
                                $vars['%tags%'] = urlencode($vars['%raw_tags%']);
                            }

                            $vars['%raw_date_posted%'] = mysql2date(get_option('date_format'), $post->post_date);
                            $vars['%date_posted%'] = urlencode($vars['%raw_date_posted%']);
                        }

                        $vars['%widgets_url%'] = $this->st_url . '/widgets';

                        $vars['%raw_permalink%'] = $permalink;
                        $vars['%permalink%'] = urlencode($permalink);

                        $vars['%raw_title%'] = $title;
                        $vars['%title%'] = urlencode($title);

                        $vars['%anywhere%'] = get_option('st_twitter_apikey');
                        $vars['%twitter_name%'] = get_option('st_noti_twitter_user');

                        $vars['%rss_url%'] = get_bloginfo('rss2_url');

                        return $vars;
                    }

                    // admin_submit_post()) - Sets up bits of the post which are useful.
                    public function admin_submit_post($postID) {
                        $post = get_post($postID);
                        if (is_object($post) && get_post_meta($post->ID, 'st_tiny_url', TRUE) == '') { // If the tiny url already exists, we will assume it's already been twittered.
                            $permalink = get_permalink($post->ID);
                            // Add the shortened URL.
                            add_post_meta($post->ID, 'st_tiny_url', $this->shorten_url($permalink), true);
                            // Add the Socialize MetaInfo
                            add_post_meta($post->ID, 'st_tweetmeme', 0, true);
                            add_post_meta($post->ID, 'st_reddit', 0, true);
                            add_post_meta($post->ID, 'st_social_score', 0, true);
                            add_post_meta($post->ID, 'st_last_socialized', 0, true);
                            if (get_option('st_noti_twitter') == 'authenticated') {
                                $vars = $this->getTags($post);
                                $vars = apply_filters('st-tweet-tags', $vars);
                                $this->twitter_update_status($this->sprintf4(get_option('st_template_twitter_noti'), $vars));
                            }
                        }
                    }

                    private function shorten_url($url) {
                        if (get_option('st_url_shortening_service') == 'tinyurl') {
                            return $this->get_link('http://tinyurl.com/api-create.php?url=' . urlencode($url));
                        } elseif (get_option('st_url_shortening_service') == 'urly') {
                            return $this->get_link('http://ur.ly/new.txt?href=' . urlencode($url));
                        } elseif (get_option('st_url_shortening_service') == 'trim') {
                            return $this->get_link('http://api.tr.im/v1/trim_simple?url=' . urlencode($url));
                        } elseif (get_option('st_url_shortening_service') == 'klam') {
                            return $this->get_link('http://kl.am/api/shorten/?format=text&url=' . urlencode($url));
                        } elseif (get_option('st_url_shortening_service') == 'unu') {
                            return $this->get_link('http://u.nu/unu-api-simple?url=' . urlencode($url));
                        } elseif (get_option('st_url_shortening_service') == 'bitly') {
                            return $this->bitly->shorten($url);
                        } elseif (get_option('st_url_shortening_service') == 'googl') {
                            return $this->googlLink($url);
                        } else {
                            return $this->get_link('http://is.gd/api.php?longurl=' . urlencode($url));
                        }
                        return $url;
                    }

                    public function get_link($url) {
                        if (function_exists('curl_init')) {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_VERBOSE, 1);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            //curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
                            //curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_URL, $url);
                            $result = curl_exec($ch);
                            $resultArray = curl_getinfo($ch);
                            curl_close($ch);

                            if ($resultArray['http_code'] == 200) {
                                return $result;
                            } else {
                                return FALSE;
                            }
                        } elseif (ini_get('allow_url_fopen') == 1) {
                            return fopen($url, 'r');
                        }
                        return FALSE;
                    }

                    public function googlLink($url) {
                        if (function_exists('curl_init')) {
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            curl_setopt($ch, CURLOPT_URL, 'http://goo.gl/api/shorten');
                            curl_setopt($ch, CURLOPT_POST, TRUE);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, 'security_token=null&url=' . urlencode($url));

                            $results = curl_exec($ch);
                            $headerInfo = curl_getinfo($ch);
                            curl_close($ch);

                            if ($headerInfo['http_code'] === 201) { // HTTP Code 201 = Created
                                $results = json_decode($results);
                                if (isset($results->short_url)) {
                                    return $results->short_url;
                                }
                                return $url;
                            }
                            return $url;
                        }
                        return $url;
                    }

                    public function show_social_widgets($names=NULL, $post_id=NULL, $permalink=NULL, $title=NULL) {
                        if (is_numeric($post_id)) {
                            $post = get_post($postID);
                            $vars = $this->getTags($post, $permalink, $title);
                        } else {
                            global $post;
                            $vars = $this->getTags($post, $permalink, $title);
                        }
                        $vars = apply_filters('st-tags', $vars);
                        if (is_object($post)) {
                            if (get_post_meta($post->ID, 'st_hide_icons', TRUE) == TRUE) {
                                return FALSE;
                            }
                            if (get_option('st_cache') != TRUE && get_post_meta($post->ID, 'st_cached_time', TRUE) > get_option('st_cached_time')) { // Check if the cache is up to date.
                                echo get_post_meta($post->ID, 'st_cached', TRUE);
                                return TRUE;
                            } else {
                                if (is_array($names) || is_numeric($names)) {
                                    $widgets = $this->getWidgets(NULL, $names, NULL, TRUE); // If the users are picking, let them show anything.
                                } else {
                                    $widgets = $this->getWidgets(2, NULL, NULL, TRUE);
                                }

                                $output = $this->sprintf4('<ul class="socialize-this"><li>' . implode('</li><li>', $widgets) . '</li></ul>', $vars);
                                echo $output;

                                // Now update the cache
                                update_post_meta($post->ID, 'st_cached', $output);
                                update_post_meta($post->ID, 'st_cached_time', time());
                                return TRUE;
                            }
                        }
                        return FALSE;
                    }

                    private function showSocialGraphMeta($property, $content) {
                        if ($property == 'admins' || $property == 'app_id') { // Check for Page Administration properties.
                            echo '<meta property="fb:' . $property . '" content="' . addslashes($content) . '"/>' . "\n";
                            return TRUE;
                        }
                        echo '<meta property="og:' . $property . '" content="' . addslashes($content) . '"/>' . "\n";
                        return TRUE;
                    }

                    public function runSocialGraph() {
                        // Check that we can show all the required meta datas.
                        foreach (array('title', 'type', 'image', 'url') as $value) {
                            if (get_option('st_og_' . $value . '_enabled') == FALSE) {
                                return FALSE;
                            }
                        }
                        // TODO
                        // Run though the metas which are page/user dependent.
                        if (get_option('st_og_use' . 'title') == FALSE) {
                            $this->showSocialGraphMeta('title', get_the_title());
                        } else {
                            $this->showSocialGraphMeta('title', get_option('st_og_' . 'title'));
                        }
                        if (get_option('st_og_use' . 'url') == FALSE) {
							if(is_home()){
								$this->showSocialGraphMeta('url', site_url());
							} else{
								$this->showSocialGraphMeta('url', get_permalink());
							}
                        } else {
                            $this->showSocialGraphMeta('url', get_option('st_og_' . 'url'));
                        }
                        if (get_option('st_og_use' . 'type') == FALSE) {
                            if (is_single() || is_page()) {
                                $this->showSocialGraphMeta('type', 'article');	
                            } else {
                                $this->showSocialGraphMeta('type', 'blog');
                            }
                        } else {
                            $this->showSocialGraphMeta('type', get_option('st_og_' . 'type'));
                        }

                        // Cycle though other meta options.
                        foreach (array('image', 'site_name', 'admins', 'app_id', 'description') as $value) {
                            if (get_option('st_og_' . $value . '_enabled') == TRUE) {
                                $this->showSocialGraphMeta($value, get_option('st_og_' . $value));
                            }
                        }

                        // Do the other Meta stuff.
                        if (get_option('st_og_' . 'othermeta' . '_enabled') == TRUE) {
                            echo get_option('st_og_' . 'othermeta');
                        }
                    }

                    // function taken from PHP.net's  str_replace documentation.
                    private function sprintf4($str, $vars) {
                        return str_replace(array_keys($vars), array_values($vars), $str);
                    }

                    public function updateSocialized($limit=1, $debug=FALSE) {
                        include ('socializeapis.class.php');

                        $sAPI = new SocializeAPIs;

                        if ($limit == FALSE) {
                            $socialPosts = get_posts('numberposts=-1&post_type=any');
                        } else {
                            $socialPosts = get_posts('numberposts=' . $limit . '&meta_key=st_last_socialized&orderby=meta_value_num&order=ASC&post_type=any');
                            if ($socialPosts[0] == '') {
                                $socialPosts = get_posts('numberposts=' . $limit . '&post_type=any');
                            }
                        }

                        foreach ($socialPosts as $socialPost) {
                            unset($tweetmeme, $reddit);
                            $tweetmeme = $sAPI->TweetMeme(get_permalink($socialPost->ID));
                            update_post_meta($socialPost->ID, 'st_tweetmeme', serialize($tweetmeme));
                            $reddit = $sAPI->Reddit(get_permalink($socialPost->ID));
                            update_post_meta($socialPost->ID, 'st_reddit', serialize($reddit));
                            update_post_meta($socialPost->ID, 'st_social_score', (($tweetmeme['url_count'] * 2) + ($reddit['score'] * ($reddit['num_comments'] * 2 )) + count(get_comments(array('post_id' => $socialPost->ID)))), true);
                            update_post_meta($socialPost->ID, 'st_last_socialized', time());
                            if ($debug == TRUE) {
                                echo 'Updated: ' . $socialPost->ID . '<br />';
                            }
                        }
                        return TRUE;
                    }

                    private function twitter_update_status($status) {
                        return $this->t->updateStatus($status);
                    }

                    private function deleteWidget($id=NULL) {
                        global $wpdb;
                        $table_name = $wpdb->prefix . "st_social_widgets";

                        $SQL = 'DELETE FROM ' . $table_name . ' WHERE `ID` = \'' . (int) $id . '\'';

                        return $wpdb->query($SQL, ARRAY_A);
                    }

                    private function getWidgets($enabled=NULL, $name=NULL, $id=NULL, $getHTML=null) {
                        global $wpdb;
                        $table_name = $wpdb->prefix . "st_social_widgets";

                        $SQL = 'SELECT * FROM ' . $table_name . ' ';
                        if ($getHTML == TRUE) {
                            $SQL = 'SELECT `html` FROM ' . $table_name . ' ';
                        }

                        $where[] = 'WHERE 1=1';
                        if ($id != NULL) {
                            if (is_array($id)) {
                                $where_id[] = 'WHERE 1=2';
                                foreach ($id as $var) {
                                    $where_id[] = "`ID` = '" . $var . "'";
                                }
                                $where[] = '(' . implode(' OR ', $where_name) . ')';
                            } elseif (is_numeric($id)) {
                                $where[] = "`ID` = '" . $id . "'";
                            }
                        }
                        if ($enabled == 2 || $enabled == 1) {
                            $where[] = "`enabled` = " . $enabled . "";
                        }
                        if ($name != NULL) {
                            if (is_array($name)) {
                                $where_name[] = 'WHERE 1=2';
                                foreach ($name as $var) {
                                    $where_name[] = "`name` = '" . $var . "'";
                                }
                                $where[] = '(' . implode(' OR ', $where_name) . ')';
                            } else {
                                $where[] = "`name` = '" . $name . "'";
                            }
                        }
                        if (is_array($where)) {
                            $SQL = $SQL . implode(' AND ', $where);
                        }

                        if ($getHTML == TRUE) {
                            return $wpdb->get_col($SQL . ' ORDER BY `position` ASC');
                        }
                        return $wpdb->get_results($SQL . ' ORDER BY `position` ASC', ARRAY_A);
                    }

                    private function addWidgetSet($set_file) {
                        $widget_set = simplexml_load_file(ST_FOLER . 'widgets/' . $set_file);
                        if (is_object($widget_set)) {
                            $author = (string) $widget_set->author;
                            $author_url = (string) $widget_set->author_url;
                            $count = 0;
                            foreach ($widget_set->widget as $widget) {
                                $count++;
                                $enabled = 2;
                                if (isset($widget->enabled)) {
                                    $enabled = $widget->enabled;
                                }
                                if (isset($widget->author) && isset($widget->author_url)) {
                                    $this->addWidget((string) $widget->author, (string) $widget->html, (string) $widget->name, (string) $widget->author_url, $enabled);
                                } else {
                                    $this->addWidget($author, (string) $widget->html, (string) $widget->name, $author_url, $enabled);
                                }
                            }
                            return $count;
                        }
                        return FALSE;
                    }

                    private function addWidget($author, $html, $name, $author_url='', $enabled=2) {
                        global $wpdb;

                        $table_name = $wpdb->prefix . "st_social_widgets";

                        return $wpdb->insert($table_name, array(
                            'author' => $author,
                            'html' => stripslashes($html),
                            'author_url' => $author_url,
                            'name' => $name,
                            'enabled' => (int) $enabled));
                    }

                    private function updateWidget($id, $html=NULL, $position=NULL, $enabled=2) {
                        global $wpdb;

                        $table_name = $wpdb->prefix . "st_social_widgets";

                        if ($html != NULL) {
                            $update['html'] = $html;
                        }
                        if ($position != NULL) {
                            $update['position'] = (int) $position;
                        }
                        $update['enabled'] = (int) $enabled;

                        return $wpdb->update($table_name, $update, array('ID' => $id));
                    }

                    public function st_install($upgrade=NULL) {
                        global $wpdb;

                        $table_name = $wpdb->prefix . "st_social_widgets";
                        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
							$sql = "CREATE TABLE " . $table_name . " (
							  ID mediumint(9) NOT NULL AUTO_INCREMENT,
							  position mediumint(9) NOT NULL,
							  enabled int(2) NOT NULL,
							  author VARCHAR(255) NOT NULL,
							  author_url VARCHAR(255) NOT NULL,
							  name VARCHAR(255) NOT NULL,
							  html text NOT NULL,
							  UNIQUE KEY ID (ID)
							);";

                            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                            dbDelta($sql);
                            $this->addWidgetSet('alteredicons.stset.xml');
							
							if ($upgrade == NULL) { // If it's a fresh install.
								update_option('st_template_twitter_noti', 'New Post: %raw_title% %raw_short_url%');
								update_option('st_include_in_posts', 'yes'); // activated by default.
								// Twitter
								update_option('st_noti_twitter', 'no');
								update_option('st_noti_twitter_user', '');
								update_option('st_twitter_apikey', '');

								// bit.ly
								update_option('st_bitly_user', '');
								update_option('st_bitly_apikey', '');

								update_option('st_url_shortening_service', 'isgd');
								update_option('st_enable_api', TRUE);
								update_option('st_extend_php_limits', NULL);
								update_option('st_css_sheet', TRUE);
								update_option('st_hide_donate', FALSE);
							}
                        }

                        

                        update_option('st_current_version', ST_VERSION);

                        //$this->updateSocialized(2); // Do a quick Socialization.
                    }

                    public function st_uninstall($upgrade=NULL) {
                        global $wpdb;

                        if ($upgrade == NULL) {
                            $table_name = $wpdb->prefix . "st_social_widgets";
                            $wpdb->query('DROP TABLE `' . $table_name . '`');

                            delete_option('st_template_twitter_noti');

                            // General Settings
                            delete_option('st_include_in_posts');
                            delete_option('st_noti_twitter');
                            delete_option('st_noti_twitter_user');
                            delete_option('st_twitter_apikey');

                            // bit.ly
                            delete_option('st_bitly_user');
                            delete_option('st_bitly_apikey');
                            delete_option('st_url_shortening_service');

                            // Advanced options
                            delete_option('st_enable_api');
                            delete_option('st_hide_donate');
                            delete_option('st_extend_php_limits');
                            delete_option('st_css_sheet');
                            delete_option('st_consumer_key');
                            delete_option('st_consumer_secret');

                            // House Keeping
                            delete_option('st_current_version');
                        }

                        // Can get messy.
                        _set_cron_array(NULL);
                    }

                }
?>
