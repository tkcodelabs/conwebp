<?php
/*
Plugin Name: ConWebp
Plugin URI: 
Description: Converte automaticamente JPG, PNG e AVIF para WebP no upload. Inclui painel de configurações premium para redimensionamento inteligente e ajuste de qualidade visual.
Version: 1.0
Author: Bruno Maykon
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ----------------------------------------------------
// 1. REGISTRO DE CONFIGURAÇÕES E PAINEL
// ----------------------------------------------------

add_action( 'admin_init', 'conwebp_register_settings' );
function conwebp_register_settings() {
    // Registra sanitizando estritamente como inteiros absolutos (Impedindo XSS e SQL Injection no DB)
    register_setting( 'conwebp_options_group', 'conwebp_quality', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 80,
    ) );
    register_setting( 'conwebp_options_group', 'conwebp_max_size', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 1920,
    ) );
    
    // Configurações padrão se for a primeira vez
    if ( get_option( 'conwebp_quality' ) === false ) {
        update_option( 'conwebp_quality', 80 );
    }
    if ( get_option( 'conwebp_max_size' ) === false ) {
        update_option( 'conwebp_max_size', 1920 );
    }
}

add_action( 'admin_menu', 'conwebp_add_admin_menu' );
function conwebp_add_admin_menu() {
    add_menu_page(
        'ConWebp',             // Título da página
        'ConWebp',             // Título do menu
        'manage_options',      // Capacidade requerida (Apenas Admin)
        'conwebp-settings',    // Slug
        'conwebp_settings_page', // Função que chama o HTML
        'dashicons-images-alt-2', // Ícone no menu (nativo do WP)
        85                     // Posição no menu
    );
}

// ----------------------------------------------------
// 2. HTML E CSS DO PAINEL (DESIGN PREMIUM)
// ----------------------------------------------------

function conwebp_settings_page() {
    // Se o usuário não for administrador, barra totalmente o acesso a tela
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'conwebp' ) );
    }

    // Busca as configurações (o absint garante que mesmo se o BD for hackeado, só retorne números seguros no HTML)
    $quality  = absint( get_option( 'conwebp_quality', 80 ) );
    $max_size = absint( get_option( 'conwebp_max_size', 1920 ) );
    ?>
    <style>
        .conwebp-wrap {
            max-width: 900px;
            margin: 30px auto 30px 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #2d3748;
        }
        .conwebp-header {
            background: linear-gradient(135deg, #026dc6 0%, #15caf4 100%);
            color: #fff;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(2, 109, 198, 0.2);
        }
        .conwebp-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
        }
        .conwebp-nav-item {
            padding: 12px 25px;
            background: #fff;
            border-radius: 10px;
            text-decoration: none;
            color: #4a5568;
            font-weight: 700;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
            cursor: pointer;
        }
        .conwebp-nav-item.active {
            background: #026dc6;
            color: #fff;
            border-color: #026dc6;
        }
        .conwebp-card {
            background: #fff;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
            display: none;
        }
        .conwebp-card.active {
            display: block;
        }
        .conwebp-card h2 {
            margin-top: 0;
            border-bottom: 2px solid #edf2f7;
            padding-bottom: 15px;
            font-size: 22px;
        }
        .conwebp-form-group {
            margin-bottom: 30px;
        }
        .conwebp-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .conwebp-btn {
            background: linear-gradient(135deg, #026dc6 0%, #15caf4 100%);
            color: #fff;
            border: none;
            padding: 16px 40px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(2, 109, 198, 0.3);
            text-transform: uppercase;
        }
        .conwebp-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(21, 202, 244, 0.4);
        }
        /* Bulk Optimizer Styles */
        .bulk-status-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            text-align: center;
        }
        .progress-container {
            height: 12px;
            background: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
            position: relative;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #026dc6, #15caf4);
            width: 0%;
            transition: width 0.3s;
        }
        .bulk-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .stat-item {
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .stat-value {
            display: block;
            font-size: 24px;
            font-weight: 800;
            color: #026dc6;
        }
        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
        }
        #bulk-log {
            height: 200px;
            overflow-y: auto;
            background: #1a202c;
            color: #a0aec0;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 12px;
            text-align: left;
            margin-top: 20px;
        }
        .log-entry { margin-bottom: 5px; }
        .log-success { color: #48bb78; }
        .log-error { color: #f56565; }
        .log-warning { color: #ecc94b; }
    </style>

    <div class="conwebp-wrap">
        <div class="conwebp-header">
            <span class="conwebp-badge">Versão 1.1 Pro</span>
            <div style="margin-bottom: 20px;">
                <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/logo_conwebp.png'; ?>" alt="ConWebp Logo" style="max-width: 120px; border-radius: 12px;">
            </div>
            <h1>ConWebp — Central de Otimização</h1>
            <p>Gerencie a conversão automática e otimize todo o acervo de imagens do seu portal.</p>
        </div>

        <nav class="conwebp-nav">
            <a class="conwebp-nav-item active" data-tab="settings">Configurações</a>
            <a class="conwebp-nav-item" data-tab="bulk">🚀 Otimização em Massa</a>
            <a class="conwebp-nav-item" data-tab="hard">🧹 Smart Cleanup</a>
        </nav>

        <?php settings_errors(); ?>

        <!-- ABA CONFIGURAÇÕES -->
        <div id="tab-settings" class="conwebp-card active">
            <h2>Ajustes de Conversão</h2>
            <div class="notice-info-card" style="background:#f0f9ff; border-left: 4px solid #026dc6; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
                <p style="margin:0; font-size:14px;">Defina como o plugin deve se comportar durante novos uploads.</p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'conwebp_options_group' ); ?>
                <div class="conwebp-form-group">
                    <label for="conwebp_quality">Qualidade WebP (Recomendado: 80%)</label>
                    <div style="display:flex; align-items:center; gap:15px;">
                        <input type="range" id="conwebp_quality" name="conwebp_quality" min="10" max="100" value="<?php echo esc_attr( $quality ); ?>" oninput="document.getElementById('q_val').textContent = this.value + '%'" style="flex:1">
                        <span id="q_val" style="font-weight:800; color:#026dc6; font-size:20px; min-width:50px;"><?php echo esc_attr( $quality ); ?>%</span>
                    </div>
                </div>

                <div class="conwebp-form-group">
                    <label for="conwebp_max_size">Tamanho Máximo (Largura em px)</label>
                    <input type="number" id="conwebp_max_size" name="conwebp_max_size" value="<?php echo esc_attr( $max_size ); ?>" min="0" step="100" style="width:150px; padding:10px; border-radius:8px; border:1px solid #cbd5e0;">
                    <p class="description">Imagens maiores que isso serão reduzidas. (0 = desligado)</p>
                </div>

                <?php submit_button( 'Salvar Configurações', 'conwebp-btn' ); ?>
            </form>
        </div>

        <!-- ABA BULK OPTIMIZER -->
        <div id="tab-bulk" class="conwebp-card">
            <h2>Otimizador em Massa (WebP)</h2>
            <?php 
                $count_query = new WP_Query(array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => array('image/jpeg', 'image/png'),
                    'post_status'    => 'inherit',
                    'posts_per_page' => 1,
                    'fields'         => 'ids'
                ));
                $total_convertible = $count_query->found_posts;
            ?>
            <p>Esta ferramenta escaneia sua biblioteca (apenas imagens originais listadas: <strong><?php echo number_format($total_convertible, 0, ',', '.'); ?> arquivos JPEG/PNG</strong>) e recria versões ultra leves em WebP. Antigas JPEG/PNG serão substituídas preservando qualidade.</p>
            
            <div class="bulk-status-box">
                <div id="bulk-status-text">Pronto para iniciar a otimização.</div>
                <div class="progress-container">
                    <div id="bulk-progress-bar" class="progress-bar"></div>
                </div>
                <div id="bulk-percentage" style="font-weight:800; font-size:18px;">0%</div>
                
                <div class="bulk-stats">
                    <div class="stat-item">
                        <span id="stat-total" class="stat-value">0</span>
                        <span class="stat-label">Convertidas</span>
                    </div>
                    <div class="stat-item">
                        <span id="stat-saved" class="stat-value">0 MB</span>
                        <span class="stat-label">Economia Est.</span>
                    </div>
                    <div class="stat-item" style="border-left: 4px solid #f56565;">
                        <span id="stat-errors" class="stat-value" style="color: #f56565;">0</span>
                        <span class="stat-label">Falhas</span>
                    </div>
                </div>

                <div style="margin-top:30px; display:flex; gap:10px; justify-content:center;">
                    <button id="btn-start-bulk" class="conwebp-btn">Iniciar Conversão</button>
                    <button id="btn-stop-bulk" class="conwebp-btn" style="background:#f56565; display:none;">Pausar</button>
                </div>
            </div>

            <div id="bulk-log">
                <div class="log-entry">Aguardando comando...</div>
            </div>
        </div>

        <!-- ABA SMART CLEANUP (FAXINA HARD) -->
        <div id="tab-hard" class="conwebp-card">
            <h2>Remoção Inteligente de Órfãos</h2>
            <p>O seu WordPress acumula milhares de miniaturas antigas geradas por temas que você não usa mais. Esta ferramenta escaneia a pasta <code>/uploads/</code> e apaga fisicamente todas as imagens que não correspondem aos recortes ativos do seu tema atual do Portal.</p>
            
            <div class="notice-info-card" style="background:#fffaf0; border-left: 4px solid #dd6b20; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
                <p style="margin:0; font-size:14px; color:#9c4221;"><strong>Aviso:</strong> Este processo é irreversível e atua diretamente nos arquivos físicos da hospedagem. Recomenda-se tê-lo feito backup antes.</p>
            </div>

            <div style="margin-bottom: 25px; padding: 20px; background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                <strong style="color: #4a5568;">Tamanhos Ativos (Blindados contra exclusão):</strong><br>
                <code id="useful-sizes-list" style="display: block; margin-top: 10px; color: #3182ce; font-weight: bold;">Verificando recortes...</code>
            </div>

            <div class="bulk-status-box" style="border-color: #dd6b20;">
                <div id="hard-status-area" style="margin-bottom: 15px; font-size: 16px; font-weight: bold; color: #dd6b20; display: none;">
                    🎯 Progresso: <span id="hard-status-msg">Pronto para iniciar...</span>
                </div>
                <button type="button" id="btn-start-hard" class="conwebp-btn" style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);">Iniciar Varredura Profunda</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab Switcher
        $('.conwebp-nav-item').on('click', function() {
            const tab = $(this).data('tab');
            $('.conwebp-nav-item').removeClass('active');
            $(this).addClass('active');
            $('.conwebp-card').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // Bulk Logic
        let isRunning = false;
        let processed = 0;
        let errorsCount = 0;
        let savedBytes = 0;
        let totalAttachments = <?php echo isset($total_convertible) ? $total_convertible : 0; ?>;
        
        $('#btn-start-bulk').on('click', function() {
            if (isRunning) return;
            isRunning = true;
            $(this).hide();
            $('#btn-stop-bulk').show();
            $('#bulk-status-text').html('🔍 Escaneando biblioteca e convertendo...');
            processNextBatch();
        });

        $('#btn-stop-bulk').on('click', function() {
            isRunning = false;
            $(this).hide();
            $('#btn-start-bulk').show().text('Retomar Conversao');
            $('#bulk-status-text').text('Pausado pelo usuario.');
            addLog('Operacao pausada.', 'warning');
        });

        function processNextBatch() {
            if (!isRunning) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'conwebp_bulk_optimizer',
                    nonce: '<?php echo wp_create_nonce("conwebp_bulk_nonce"); ?>',
                    offset: processed
                },
                success: function(res) {
                    if (res.success) {
                        processed += res.data.count;
                        let progress = Math.round((processed / totalAttachments) * 100);
                        
                        $('#bulk-progress-bar').css('width', progress + '%');
                        $('#bulk-percentage').text(progress + '%');
                        $('#stat-total').text(processed);
                        
                        // Atualiza log e status
                        res.data.results.forEach(item => {
                            addLog(item.msg, item.status);
                            if (item.status === 'error') {
                                errorsCount++;
                            }
                            if (item.saved_bytes && item.saved_bytes > 0) {
                                savedBytes += item.saved_bytes;
                            }
                        });
                        
                        $('#stat-errors').text(errorsCount);
                        $('#stat-saved').text((savedBytes / 1048576).toFixed(2) + ' MB');

                        if (processed < totalAttachments && res.data.count > 0) {
                            processNextBatch();
                        } else {
                            completeProcess();
                        }
                    } else {
                        addLog('Erro: ' + res.data, 'error');
                        isRunning = false;
                        $('#btn-stop-bulk').hide();
                        $('#btn-start-bulk').show().text('Tentar Novamente');
                    }
                },
                error: function() {
                    addLog('Erro de conexao no servidor.', 'error');
                    isRunning = false;
                }
            });
        }

        function addLog(msg, type) {
            const log = $('#bulk-log');
            const entry = $('<div class="log-entry"></div>').text(msg).addClass('log-' + type);
            log.prepend(entry);
            if (log.children().length > 100) log.children().last().remove();
        }

        function completeProcess() {
            isRunning = false;
            $('#bulk-status-text').html('✅ <strong>Faxina Concluida!</strong> Todas as imagens foram processadas.');
            $('#btn-stop-bulk').hide();
            $('#btn-start-bulk').hide();
            addLog('Conversao total finalizada com sucesso.', 'success');
        }

        // Hard Cleanup Logic
        let isHardRunning = false;
        
        // Carrega tamanhos úteis na tela de forma segura
        const usefulSizes = <?php 
            $sizes = get_intermediate_image_sizes();
            $useful = array();
            global $_wp_additional_image_sizes;
            foreach ($sizes as $s) {
                if (in_array($s, ['thumbnail', 'medium', 'medium_large', 'large'])) {
                    $w = get_option($s . '_size_w');
                    $h = get_option($s . '_size_h');
                    $useful[] = "$s ({$w}x{$h})";
                } elseif (isset($_wp_additional_image_sizes[$s])) {
                    $useful[] = "$s ({$_wp_additional_image_sizes[$s]['width']}x{$_wp_additional_image_sizes[$s]['height']})";
                }
            }
            echo json_encode($useful); 
        ?> || [];

        if (usefulSizes.length > 0) {
            $('#useful-sizes-list').text(usefulSizes.join(', '));
        } else {
            $('#useful-sizes-list').text('Nenhum tamanho adicional detectado.');
        }

        // Action for Hard Cleanup
        $('#btn-start-hard').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (isHardRunning) return false;
            
            if (!confirm('ATENCAO: Deseja apagar miniaturas inuteis? Isso libera espaco mas remove tamanhos legados.')) return false;
            
            isHardRunning = true;
            $(this).prop('disabled', true).text('Processando Limpeza...');
            $('#hard-status-area').show();
            $('#hard-status-msg').text('Iniciando varredura...');
            
            addLog('🧹 Faxina Hard: Iniciando varredura de pastas...', 'warning');
            processHardBatch(0);
            return false;
        });

        function processHardBatch(monthOffset) {
            if (!isHardRunning) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'conwebp_hard_cleanup',
                    nonce: '<?php echo wp_create_nonce("conwebp_bulk_nonce"); ?>',
                    month_offset: monthOffset
                },
                success: function(res) {
                    if (res.success) {
                        if (res.data.results && res.data.results.length > 0) {
                            res.data.results.forEach(item => addLog('🧹 ' + item.msg, item.status));
                            $('#hard-status-msg').text(res.data.results[0].msg);
                        }
                        
                        if (!res.data.finished) {
                            processHardBatch(monthOffset + 1);
                        } else {
                            addLog('✅ FAXINA NIVEL HARD CONCLUIDA!', 'success');
                            $('#hard-status-msg').text('Concluido com sucesso!');
                            $('#btn-start-hard').text('Faxina Hard Finalizada');
                            isHardRunning = false;
                        }
                    } else {
                        addLog('Erro Hard: ' + (res.data || 'Erro desconhecido'), 'error');
                        isHardRunning = false;
                        $('#btn-start-hard').prop('disabled', false).text('Tentar Novamente');
                    }
                },
                error: function() {
                    addLog('Erro de conexao ao processar Faxina Hard.', 'error');
                    isHardRunning = false;
                    $('#btn-start-hard').prop('disabled', false).text('Tentar Novamente');
                }
            });
        }
    });
    </script>
    <?php
}

// ----------------------------------------------------
// 3. FILTRO DE HIGIENIZAÇÃO DE NOMES (ANTI-404)
// ----------------------------------------------------

add_filter( 'wp_handle_upload_prefilter', 'conwebp_sanitize_legacy_suffixes' );
function conwebp_sanitize_legacy_suffixes( $file ) {
    $info = pathinfo( $file['name'] );
    $ext  = empty( $info['extension'] ) ? '' : '.' . $info['extension'];
    $name = $info['filename'];

    // 1. Remove sufixos bizarros de redimensionamento legados (.950x0_q95_crop)
    $name = preg_replace( '/\.\d+x\d+(_q\d+)?(_crop)?/i', '', $name );
    
    // 2. Remove extensões duplas se houver (ex: imagem.jpg.jpg -> imagem.jpg)
    $name = preg_replace( '/\.(jpg|jpeg|png|gif|avif)$/i', '', $name );

    $file['name'] = $name . $ext;
    return $file;
}

// ----------------------------------------------------
// 4. LÓGICA PRINCIPAL (INTERCEPTAÇÃO DO UPLOAD)
// ----------------------------------------------------

add_filter( 'upload_mimes', 'conwebp_allow_avif_uploads' );
function conwebp_allow_avif_uploads( $mimes ) {
    if ( ! isset( $mimes['avif'] ) ) {
        $mimes['avif'] = 'image/avif';
    }
    return $mimes;
}

add_filter( 'wp_handle_upload', 'conwebp_process_image_upload' );
function conwebp_process_image_upload( $upload ) {
    // Ignora se for vídeo, pdf ou der erro
    if ( isset( $upload['error'] ) || empty( $upload['file'] ) ) {
        return $upload;
    }

    $file_path = $upload['file'];
    $file_type = $upload['type'];
    $supported_types = array( 'image/jpeg', 'image/jpg', 'image/png', 'image/avif' );

    if ( ! in_array( strtolower( $file_type ), $supported_types, true ) ) {
        return $upload;
    }

    $real_mime = wp_check_filetype_and_ext( $file_path, $upload['file'] );
    if ( empty( $real_mime['ext'] ) || empty( $real_mime['type'] ) || ! in_array( strtolower( $real_mime['type'] ), $supported_types, true ) ) {
        return $upload;
    }

    $editor = wp_get_image_editor( $file_path );
    if ( ! is_wp_error( $editor ) ) {
        $max_size = (int) get_option( 'conwebp_max_size', 1920 );
        $quality  = (int) get_option( 'conwebp_quality', 80 );
        
        if ( $max_size > 0 ) {
            $size = $editor->get_size();
            if ( ! is_wp_error( $size ) && ( $size['width'] > $max_size || $size['height'] > $max_size ) ) {
                $editor->resize( $max_size, $max_size, false );
            }
        }

        $path_parts = pathinfo( $file_path );
        $new_filename_base = $path_parts['filename'] . '.webp';
        $unique_filename = wp_unique_filename( $path_parts['dirname'], $new_filename_base );
        $new_file_path   = $path_parts['dirname'] . '/' . $unique_filename;

        $editor->set_quality( $quality );
        $saved = $editor->save( $new_file_path, 'image/webp' );

        if ( ! is_wp_error( $saved ) ) {
            @unlink( $file_path );
            $upload['file'] = $new_file_path;
            $url_parts = pathinfo( $upload['url'] );
            $upload['url'] = str_replace( $url_parts['basename'], $unique_filename, $upload['url'] );
            $upload['type'] = 'image/webp';
        }
    }

    return $upload;
}

// ----------------------------------------------------
// 5. FAXINA TOTAL (AJAX BULK OPTIMIZER)
// ----------------------------------------------------

add_action( 'wp_ajax_conwebp_bulk_optimizer', 'conwebp_handle_bulk_ajax' );
function conwebp_handle_bulk_ajax() {
    check_ajax_referer( 'conwebp_bulk_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada.' );

    $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $limit  = 5; // Processa 5 por vez pra não dar timeout em servidores lentos
    
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => array( 'image/jpeg', 'image/png' ),
        'post_status'    => 'inherit',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'DESC'
    );

    $query = new WP_Query( $args );
    $results = array();

    if ( $query->have_posts() ) {
        $quality = (int) get_option( 'conwebp_quality', 80 );
        
        while ( $query->have_posts() ) {
            $query->the_post();
            $id = get_the_ID();
            $file = get_attached_file( $id );
            
            if ( ! $file || ! file_exists( $file ) ) {
                $results[] = array( 'status' => 'error', 'msg' => "ID $id: Arquivo não encontrado físico.", 'saved_bytes' => 0 );
                continue;
            }

            $info = pathinfo( $file );
            if ( strtolower( $info['extension'] ) === 'webp' ) {
                $results[] = array( 'status' => 'warning', 'msg' => "ID $id: Já é WebP. Pulado.", 'saved_bytes' => 0 );
                continue;
            }

            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) {
                $results[] = array( 'status' => 'error', 'msg' => "ID $id: Erro ao abrir imagem.", 'saved_bytes' => 0 );
                continue;
            }

            $original_size = filesize( $file );

            $new_file = $info['dirname'] . '/' . $info['filename'] . '.webp';
            $editor->set_quality( $quality );
            $saved = $editor->save( $new_file, 'image/webp' );

            if ( ! is_wp_error( $saved ) ) {
                // Update Metadata
                update_attached_file( $id, $new_file );
                
                // Força o WP a regenerar as miniaturas no novo formato
                $metadata = wp_generate_attachment_metadata( $id, $new_file );
                wp_update_attachment_metadata( $id, $metadata );
                
                $new_size = filesize( $new_file );
                $saved_bytes = max( 0, $original_size - $new_size );

                // Remove original
                @unlink( $file );
                
                $results[] = array( 'status' => 'success', 'msg' => "ID $id: Otimizado com sucesso (" . $info['basename'] . ")", 'saved_bytes' => $saved_bytes );
            } else {
                $results[] = array( 'status' => 'error', 'msg' => "ID $id: Erro ao salvar WebP.", 'saved_bytes' => 0 );
            }
        }
        
        wp_send_json_success( array( 'count' => $query->post_count, 'results' => $results ) );
    } else {
        wp_send_json_success( array( 'count' => 0, 'results' => array() ) );
    }
}

// ----------------------------------------------------
// 6. FAXINA NÍVEL HARD (EXCLUSÃO DE MINIATURAS INÚTEIS)
// ----------------------------------------------------

add_action( 'wp_ajax_conwebp_hard_cleanup', 'conwebp_handle_hard_cleanup_ajax' );
function conwebp_handle_hard_cleanup_ajax() {
    check_ajax_referer( 'conwebp_bulk_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada.' );

    $month_offset = isset( $_POST['month_offset'] ) ? intval( $_POST['month_offset'] ) : 0;
    $upload_dir   = wp_get_upload_dir();
    $base_path    = $upload_dir['basedir'];

    // 1. Pega os tamanhos "Vivos" (Miniaturas que o tema atual usa)
    $sizes = get_intermediate_image_sizes();
    $allowed_dims = array();
    global $_wp_additional_image_sizes;

    foreach ($sizes as $s) {
        if (in_array($s, ['thumbnail', 'medium', 'medium_large', 'large'])) {
            $w = get_option($s . '_size_w');
            $h = get_option($s . '_size_h');
            if ($w && $h) $allowed_dims[] = "{$w}x{$h}";
        } elseif (isset($_wp_additional_image_sizes[$s])) {
            $allowed_dims[] = "{$_wp_additional_image_sizes[$s]['width']}x{$_wp_additional_image_sizes[$s]['height']}";
        }
    }
    
    // 2. Localiza as pastas de meses disponíveis (mais robusto para Windows/Linux)
    $base_path = str_replace('\\', '/', $base_path);
    $years_dirs = glob($base_path . '/*', GLOB_ONLYDIR);
    $all_months = array();
    
    if ($years_dirs) {
        foreach ($years_dirs as $yd) {
            $months = glob($yd . '/*', GLOB_ONLYDIR);
            if ($months) {
                $all_months = array_merge($all_months, $months);
            }
        }
    }
    
    // Fallback caso não existam subpastas de ano/mês (raro mas possível)
    if (empty($all_months)) {
        $all_months = array($base_path);
    }

    rsort($all_months); 

    if (!isset($all_months[$month_offset])) {
        wp_send_json_success(array('finished' => true, 'results' => array()));
        return;
    }

    $current_folder = $all_months[$month_offset];
    $display_folder = str_replace($base_path, '', $current_folder);
    $files = glob($current_folder . '/*');
    $results = array();
    $deleted_count = 0;

    foreach ($files as $file) {
        $basename = basename($file);
        
        // Regex para capturar padrão de miniatura: nome-arquivo-123x123.jpg
        // Ignora se não tiver o padrão de dimensão no final
        if (preg_match('/-(\d+x\d+)\.(jpg|jpeg|png|webp)$/i', $basename, $matches)) {
            $dim = $matches[1];
            
            // Se essa dimensão NÃO estiver na lista de permitidas, vira lixo
            if (!in_array($dim, $allowed_dims)) {
                if (@unlink($file)) {
                    $deleted_count++;
                }
            }
        }
    }

    $results[] = array(
        'status' => 'success', 
        'msg' => "Pasta $display_folder: Limpeza concluída. Removed $deleted_count miniaturas inúteis."
    );

    wp_send_json_success(array(
        'finished' => false,
        'results' => $results
    ));
}
