<?php

namespace Coretik\Core\Models\Wp;

use Coretik\Core\Collection;
use Coretik\Core\Models\Interfaces\DictionnaryInterface;

class UserDictionnary extends Collection implements DictionnaryInterface
{
    public function __construct()
    {
        parent::__construct([
            'ID', //(int) User ID. If supplied, the user will be updated.
            'user_pass', //(string) The plain-text user password.
            'user_login', //(string) The user's login username.
            'user_nicename', //(string) The URL-friendly user name.
            'user_url', //(string) The user URL.
            'user_email', //(string) The user email address.
            'display_name', //(string) The user's display name. Default is the user's username.
            'nickname', //(string) The user's nickname. Default is the user's username.
            'first_name', //(string) The user's first name. For new users, will be used to build the first part of the user's display name if $display_name is not specified.
            'last_name', //(string) The user's last name. For new users, will be used to build the second part of the user's display name if $display_name is not specified.
            'description', //(string) The user's biographical description.
            'rich_editing', //(string) Whether to enable the rich-editor for the user. Accepts 'true' or 'false' as a string literal, not boolean. Default 'true'.
            'syntax_highlighting', //(string) Whether to enable the rich code editor for the user. Accepts 'true' or 'false' as a string literal, not boolean. Default 'true'.
            'comment_shortcuts', //(string) Whether to enable comment moderation keyboard shortcuts for the user. Accepts 'true' or 'false' as a string literal, not boolean. Default 'false'.
            'admin_color', //(string) Admin color scheme for the user. Default 'fresh'.
            'use_ssl', //(bool) Whether the user should always access the admin over https. Default false.
            'user_registered', //(string) Date the user registered. Format is 'Y-m-d H:i:s'.
            'user_activation_key', //(string) Password reset key. Default empty.
            'spam', //(bool) Multisite only. Whether the user is marked as spam. Default false.
            'show_admin_bar_front', //(string) Whether to display the Admin Bar for the user on the site's front end. Accepts 'true' or 'false' as a string literal, not boolean. Default 'true'.
            'role', //(string) User's role.
            'locale', //(string) User's locale. Default empty.
        ]);
    }
}
