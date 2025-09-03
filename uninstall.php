<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('aviso_postagem_antiga_days_before_warning');
