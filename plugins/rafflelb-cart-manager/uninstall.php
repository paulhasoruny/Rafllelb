<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
delete_option('rlcm_activated_at');
delete_option('rlcm_custom_session_seen');
