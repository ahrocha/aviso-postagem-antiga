<?php
/**
 * Plugin Name: Aviso de Postagem Antiga
 * Plugin URI: https://github.com/ahrocha/aviso-postagem-antiga
 * Description: Mostra um aviso no início de posts que não foram atualizados há mais de um ano.
 * Version: 0.2.9
 * Author: Andrey Rocha
 * Author URI: https://andreyrocha.com
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aviso-postagem-antiga
 * Domain Path: /languages
 * Update URI: https://updates.andreyrocha.com/aviso-postagem-antiga
 */

if (!defined('ABSPATH')) { exit; }

define('AVISO_POSTAGEM_ANTIGA_UPDATE_SERVER', 'https://updates.andreyrocha.com');
define('AVISO_POSTAGEM_ANTIGA_UPDATE_JSON', AVISO_POSTAGEM_ANTIGA_UPDATE_SERVER . '/aviso-postagem-antiga/update.json');

function aviso_postagem_antiga_add_notice($content) {
    global $post;
    if (!is_singular('post') || !$post) {
        return $content;
    }

    // Usa a data da última modificação
    $timestamp = get_post_modified_time('U', true, $post);
    $dias = floor( (time() - $timestamp) / DAY_IN_SECONDS );

    $limite = (int) get_option('aviso_postagem_antiga_days_before_warning', 365);

    if ($dias >= $limite) {
        $mensagem = '
        <div style="border:1px solid #e2e8f0; padding:10px; margin:10px 0; background:#fffbe6;">
            <strong>Atenção:</strong> esta postagem foi atualizada há mais de '.$dias.' dias desde sua última atualização.
            Verifique em outras fontes sobre os locais, valores, telefones e endereços listados nesta postagem.
        </div>';
        return $mensagem . $content;
    }

    return $content;
}
add_filter('the_content', 'aviso_postagem_antiga_add_notice');

// ====== Auto-update (server-hosted JSON) ======

add_filter('pre_set_site_transient_update_plugins', function ($transient) {
    // Evita chamadas fora do fluxo normal
    if (empty($transient) || !is_object($transient)) {
        $transient = new stdClass();
    }

    $plugin_file = plugin_basename(__FILE__); // "aviso-postagem-antiga/aviso-postagem-antiga.php"
    $current_version = get_file_data(__FILE__, ['Version' => 'Version'])['Version'] ?? '0.0.0';

    // Cache leve para não bater no servidor a cada load
    $cache_key = 'aviso_postagem_antiga_update_info';
    $update_info = get_transient($cache_key);

    if (!$update_info) {
        $resp = wp_remote_get(
            AVISO_POSTAGEM_ANTIGA_UPDATE_JSON,
            ['timeout' => 8, 'sslverify' => true]
        );
        if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
            $body = wp_remote_retrieve_body($resp);
            $update_info = json_decode($body);
            // Cache por 6 horas
            set_transient($cache_key, $update_info, 6 * HOUR_IN_SECONDS);
        }
    }

    if ($update_info && is_object($update_info)) {
        $remote_version = $update_info->version ?? null;
        if ($remote_version && version_compare($remote_version, $current_version, '>')) {
            $obj = new stdClass();
            $obj->slug = $update_info->slug ?? 'aviso-postagem-antiga';
            $obj->plugin = $plugin_file;
            $obj->new_version = $remote_version;
            $obj->url = $update_info->homepage ?? '';
            $obj->package = $update_info->download_url ?? ''; // URL do ZIP
            $obj->tested = $update_info->tested ?? '';
            $obj->requires = $update_info->requires ?? '';
            $transient->response[$plugin_file] = $obj;
        } else {
            // Sem update
            unset($transient->response[$plugin_file]);
            $transient->no_update[$plugin_file] = (object)[
                'slug'        => $update_info->slug ?? 'aviso-postagem-antiga',
                'plugin'      => $plugin_file,
                'new_version' => $remote_version ?: $current_version,
                'url'         => $update_info->homepage ?? '',
                'package'     => ''
            ];
        }
    }

    return $transient;
});

/**
 * Fornece detalhes da “View details” modal do plugin (Plugins → View details)
 */
add_filter('plugins_api', function ($result, $action, $args) {
    if ($action !== 'plugin_information') { return $result; }
    if (empty($args->slug) || $args->slug !== 'aviso-postagem-antiga') { return $result; }

    $cache_key = 'aviso_postagem_antiga_update_info';
    $update_info = get_transient($cache_key);

    if (!$update_info) {
        $resp = wp_remote_get(
            AVISO_POSTAGEM_ANTIGA_UPDATE_JSON,
            ['timeout' => 8, 'sslverify' => true]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return $result;
        }
        $update_info = json_decode(wp_remote_retrieve_body($resp));
        set_transient($cache_key, $update_info, 6 * HOUR_IN_SECONDS);
    }

    if (!$update_info) { return $result; }

    $info = new stdClass();
    $info->name = 'Aviso de Postagem Antiga';
    $info->slug = 'aviso-postagem-antiga';
    $info->version = $update_info->version ?? '';
    $info->author = '<a href="https://andreyrocha.com">Andrey Rocha</a>';
    $info->homepage = $update_info->homepage ?? '';
    $info->download_link = $update_info->download_url ?? '';
    $info->requires = $update_info->requires ?? '6.0';
    $info->tested = $update_info->tested ?? '6.8';
    $info->requires_php = $update_info->requires_php ?? '7.4';
    $info->last_updated = $update_info->last_updated ?? '';
    $info->sections = (array)($update_info->sections ?? ['description' => '']);
    return $info;
}, 10, 3);

// 1) Define o valor padrão na ativação (melhor que fazer no 'init')
register_activation_hook(__FILE__, function () {
    add_option('aviso_postagem_antiga_days_before_warning', 365);
});

// 2) Adiciona a página em Configurações
add_action('admin_menu', function () {
    add_options_page(
        __('Aviso de Postagem Antiga', 'aviso-postagem-antiga'),
        __('Aviso de Postagem Antiga', 'aviso-postagem-antiga'),
        'manage_options',
        'aviso-postagem-antiga-settings',
        'aviso_postagem_antiga_render_settings_page'
    );
});

// 3) Registra a opção + campo usando a Settings API
add_action('admin_init', function () {
    // registra a opção com sanitização e default
    register_setting('aviso_postagem_antiga_settings_group', 'aviso_postagem_antiga_days_before_warning', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 365,
        'show_in_rest'      => false,
    ]);

    // campo
    add_settings_field(
        'aviso_postagem_antiga_days_before_warning',
        __('Dias para exibir o aviso', 'aviso-postagem-antiga'),
        'aviso_postagem_antiga_render_days_field',
        'aviso-postagem-antiga-settings',
        'default'
    );
});

// 4) Callback do campo
function aviso_postagem_antiga_render_days_field() {
    $value = get_option('aviso_postagem_antiga_days_before_warning', 365);
    ?>
    <input
        type="number"
        name="aviso_postagem_antiga_days_before_warning"
        id="aviso_postagem_antiga_days_before_warning"
        value="<?php echo esc_attr($value); ?>"
        min="1"
        step="1"
        class="small-text"
    />
    <p class="description">
        <?php esc_html_e(
            'Número de dias desde a última atualização do post para começar a mostrar o aviso.',
            'aviso-postagem-antiga');
        ?>
    </p>
    <?php
}

// 5) Render da página
function aviso_postagem_antiga_render_settings_page() { ?>
    <div class="wrap">
        <h1><?php esc_html_e('Aviso de Postagem Antiga', 'aviso-postagem-antiga'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('aviso_postagem_antiga_settings_group');   // nonce + option group
            echo '<table class="form-table" role="presentation">';
            do_settings_fields('aviso-postagem-antiga-settings', 'default');    // seção + campos
            echo '</table>';
            submit_button();
            ?>
        </form>
    </div>
<?php }

// (Opcional) Link "Configurações" na lista de plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = admin_url('options-general.php?page=aviso-postagem-antiga-settings');
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Configurações', 'aviso-postagem-antiga') . '</a>';
    return $links;
});

