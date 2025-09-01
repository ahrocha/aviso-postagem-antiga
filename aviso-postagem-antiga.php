<?php
/**
 * Plugin Name: Aviso de Postagem Antiga
 * Description: Mostra um aviso no início de posts com mais de 1 ano desde a última atualização.
 * Version: 0.2
 * Author: Andrey Rocha
 */

if (!defined('ABSPATH')) exit;

function apa_add_notice($content) {
    global $post;
    if (!is_singular('post') || !$post) {
        return $content;
    }

    // Usa a data da última modificação
    $timestamp = get_post_modified_time('U', true, $post);
    $dias = floor( (time() - $timestamp) / DAY_IN_SECONDS );

    if ($dias >= 365) {
        $mensagem = '<div style="border:1px solid #e2e8f0; padding:10px; margin:10px 0; background:#fffbe6;">
            <strong>Atenção:</strong> esta postagem possui mais de um ano desde a última atualização. 
            Portanto, verifique em outras fontes sobre os locais, valores, telefones e endereços listados aqui.
        </div>';
        return $mensagem . $content;
    }

    return $content;
}
add_filter('the_content', 'apa_add_notice');
