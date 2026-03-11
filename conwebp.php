<?php
/*
Plugin Name: ConWebp
Plugin URI: 
Description: Converte automaticamente JPG, PNG e AVIF para WebP no upload. Inclui painel de configurações premium para redimensionamento inteligente e ajuste de qualidade visual.
Version: 1.0
Author: Tkode Labs - Bruno Maykon
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
    register_setting( 'conwebp_options_group', 'conwebp_remove_originals', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 1,
    ) );
    register_setting( 'conwebp_options_group', 'conwebp_update_links', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 1,
    ) );
    register_setting( 'conwebp_options_group', 'conwebp_update_postmeta', array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
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
    $remove_originals = absint( get_option( 'conwebp_remove_originals', 1 ) );
    $update_links     = absint( get_option( 'conwebp_update_links', 1 ) );
    $update_postmeta  = absint( get_option( 'conwebp_update_postmeta', 0 ) );

    // Verifica suporte real a WebP no servidor
    $webp_supported = function_exists( 'wp_image_editor_supports' )
        ? wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) )
        : false;
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

            <?php if ( ! $webp_supported ) : ?>
                <div class="notice-info-card" style="background:#fff5f5; border-left: 4px solid #e53e3e; padding: 15px; border-radius: 0 8px 8px 0; margin-bottom: 20px;">
                    <p style="margin:0; font-size:14px; color:#742a2a;">
                        <strong>Atenção:</strong> O servidor PHP atual não tem suporte total a WebP via GD/Imagick.
                        As conversões podem manter as imagens em JPG/PNG. Peça à hospedagem para habilitar WebP nas extensões de imagem do PHP.
                    </p>
                </div>
            <?php endif; ?>

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

                <div class="conwebp-form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="hidden" name="conwebp_remove_originals" value="0" />
                        <input type="checkbox" name="conwebp_remove_originals" value="1" <?php checked( $remove_originals, 1 ); ?> />
                        <span>Remover arquivos originais (JPG/PNG/AVIF) após gerar WebP</span>
                    </label>
                    <p class="description">Ative se você deseja manter apenas arquivos .webp na hospedagem. Recomendado fazer backup antes.</p>
                </div>

                <div class="conwebp-form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="hidden" name="conwebp_update_links" value="0" />
                        <input type="checkbox" name="conwebp_update_links" value="1" <?php checked( $update_links, 1 ); ?> />
                        <span>Atualizar links de imagens em posts e páginas para usar WebP</span>
                    </label>
                    <p class="description">Quando usado com o Otimizador em Massa, troca automaticamente as URLs antigas (.jpg/.png) pelos novos arquivos .webp no conteúdo dos posts.</p>
                </div>

                <div class="conwebp-form-group">
                    <label style="display:flex; align-items:center; gap:10px;">
                        <input type="hidden" name="conwebp_update_postmeta" value="0" />
                        <input type="checkbox" name="conwebp_update_postmeta" value="1" <?php checked( $update_postmeta, 1 ); ?> />
                        <span>Atualizar também campos avançados (postmeta, page builders, etc.)</span>
                    </label>
                    <p class="description">Inclui campos de construtores de página e metadados serializados. Pode levar mais tempo em bancos muito grandes.</p>
                </div>

                <?php submit_button( 'Salvar Configurações', 'conwebp-btn' ); ?>
            </form>
        </div>

        <!-- ABA BULK OPTIMIZER -->
        <div id="tab-bulk" class="conwebp-card">
            <h2>Otimizador em Massa (WebP)</h2>
            <?php 
                global $wpdb;
                $count_query = new WP_Query(array(
                    'post_type'      => 'attachment',
                    'post_mime_type' => array('image/jpeg', 'image/png'),
                    'post_status'    => 'inherit',
                    'posts_per_page' => 1,
                    'fields'         => 'ids'
                ));
                $total_convertible = $count_query->found_posts;

                // Lista de anos disponíveis para filtro (para evitar timeout em bibliotecas gigantes)
                $years = $wpdb->get_col(
                    "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts}
                     WHERE post_type = 'attachment'
                     AND post_mime_type IN ('image/jpeg','image/png')
                     ORDER BY YEAR(post_date) DESC"
                );
            ?>
            <p>Esta ferramenta escaneia sua biblioteca (apenas imagens originais listadas: <strong><?php echo number_format($total_convertible, 0, ',', '.'); ?> arquivos JPEG/PNG</strong>) e recria versões ultra leves em WebP. Antigas JPEG/PNG serão substituídas preservando qualidade.</p>

            <div class="conwebp-form-group" style="margin-top:15px; margin-bottom:10px;">
                <label style="font-weight:600;">Escopo da conversão (ajuda a evitar timeout):</label>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <select id="conwebp_year_filter" style="min-width:140px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e0;">
                        <option value="">Todos os anos</option>
                        <?php if ( ! empty( $years ) ) : ?>
                            <?php foreach ( $years as $year ) : ?>
                                <option value="<?php echo esc_attr( $year ); ?>"><?php echo esc_html( $year ); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <select id="conwebp_month_filter" style="min-width:160px; padding:6px 10px; border-radius:8px; border:1px solid #cbd5e0;">
                        <option value="">Todos os meses</option>
                        <option value="1">Janeiro</option>
                        <option value="2">Fevereiro</option>
                        <option value="3">Março</option>
                        <option value="4">Abril</option>
                        <option value="5">Maio</option>
                        <option value="6">Junho</option>
                        <option value="7">Julho</option>
                        <option value="8">Agosto</option>
                        <option value="9">Setembro</option>
                        <option value="10">Outubro</option>
                        <option value="11">Novembro</option>
                        <option value="12">Dezembro</option>
                    </select>
                    <span class="description">Você pode rodar mês a mês para reduzir o risco de timeout em hospedagens lentas.</span>
                </div>
            </div>
            
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
        let bulkYearFilter = '';
        let bulkMonthFilter = '';
        
        $('#btn-start-bulk').on('click', function() {
            if (isRunning) return;
            isRunning = true;

            // Lê filtros selecionados (ano/mês) para limitar o escopo
            bulkYearFilter = $('#conwebp_year_filter').val();
            bulkMonthFilter = $('#conwebp_month_filter').val();

            // Reseta contadores
            processed   = 0;
            errorsCount = 0;
            savedBytes  = 0;
            $('#bulk-progress-bar').css('width', '0%');
            $('#bulk-percentage').text('0%');
            $('#stat-total').text('0');
            $('#stat-errors').text('0');
            $('#stat-saved').text('0 MB');

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
                    offset: processed,
                    year: bulkYearFilter,
                    month: bulkMonthFilter
                },
                success: function(res) {
                    if (res.success) {
                        // Atualiza total com base no filtro quando servidor informar
                        if (res.data.total !== undefined && res.data.total !== null) {
                            totalAttachments = res.data.total;
                        }

                        processed += res.data.count;
                        let progress = totalAttachments > 0 ? Math.round((processed / totalAttachments) * 100) : 0;
                        
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
            $('#bulk-status-text').html('🔄 <strong>Otimização finalizada!</strong> Atualizando links permanentes...');
            
            // Chama o flush de regras via AJAX para evitar 404
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'conwebp_flush_rules',
                    nonce: '<?php echo wp_create_nonce("conwebp_bulk_nonce"); ?>'
                },
                success: function(res) {
                    $('#bulk-status-text').html('✅ <strong>Sucesso Total!</strong> Imagens convertidas e links atualizados.');
                    addLog('Links permanentes atualizados com sucesso.', 'success');
                },
                error: function() {
                    $('#bulk-status-text').html('⚠️ <strong>Atenção:</strong> Imagens convertidas, mas houve erro ao limpar links permanentes. Recomenda-se salvar manualmente em Configurações > Links Permanentes.');
                    addLog('Erro ao tentar resetar links permanentes.', 'error');
                }
            });

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

/**
 * Faz o WordPress priorizar o editor GD (que suporta WebP)
 * antes do Imagick em todas as operações de imagem.
 */
add_filter( 'wp_image_editors', 'conwebp_prefer_gd_editor' );
function conwebp_prefer_gd_editor( $editors ) {
    if ( in_array( 'WP_Image_Editor_GD', $editors, true ) ) {
        $editors = array_merge(
            array( 'WP_Image_Editor_GD' ),
            array_diff( $editors, array( 'WP_Image_Editor_GD' ) )
        );
    }
    return $editors;
}

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

    // Usa o editor padrão (já ajustado para priorizar GD via filtro)
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

        // Garante que o servidor realmente gerou WebP
        if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ( isset( $saved['mime-type'] ) && 'image/webp' !== $saved['mime-type'] ) ) {
            return $upload; // Sem suporte real a WebP, não altera nada.
        }

        if ( ! is_wp_error( $saved ) ) {
            $remove_originals = (int) get_option( 'conwebp_remove_originals', 1 );
            if ( $remove_originals ) {
                @unlink( $file_path );
            }
            $upload['file'] = $new_file_path;
            $url_parts = pathinfo( $upload['url'] );
            $upload['url'] = str_replace( $url_parts['basename'], $unique_filename, $upload['url'] );
            $upload['type'] = 'image/webp';
        }
    }

    // Garante que o fluxo de upload do WordPress continue normalmente
    return $upload;
}

add_action( 'wp_ajax_conwebp_bulk_optimizer', 'conwebp_handle_bulk_ajax' );
function conwebp_handle_bulk_ajax() {
    check_ajax_referer( 'conwebp_bulk_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada.' );

    $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
    $limit  = 5; // Processa 5 por vez pra não dar timeout em servidores lentos

    // Filtros opcionais de ano/mês para reduzir o volume (evitar timeouts)
    $year  = isset( $_POST['year'] ) ? intval( $_POST['year'] ) : 0;
    $month = isset( $_POST['month'] ) ? intval( $_POST['month'] ) : 0;
    
    $args = array(
        'post_type'      => 'attachment',
        // Inclui variações comuns de JPEG/PNG (alguns servidores gravam como jfif/pjpeg/x-png)
        'post_mime_type' => array( 'image/jpeg', 'image/jpg', 'image/png', 'image/jfif', 'image/pjpeg', 'image/x-png' ),
        'post_status'    => 'inherit',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'DESC'
    );

    if ( $year > 0 ) {
        $args['year'] = $year;
    }
    if ( $month > 0 ) {
        $args['monthnum'] = $month;
    }

    $query   = new WP_Query( $args );
    $results = array();

    if ( $query->have_posts() ) {
        $quality          = (int) get_option( 'conwebp_quality', 80 );
        $remove_originals = (int) get_option( 'conwebp_remove_originals', 1 );
        $update_links     = (int) get_option( 'conwebp_update_links', 1 );
        $update_postmeta  = (int) get_option( 'conwebp_update_postmeta', 0 );
        
        while ( $query->have_posts() ) {
            $query->the_post();
            $id = get_the_ID();
            $file = get_attached_file( $id );
            
            if ( ! $file || ! file_exists( $file ) ) {
                $results[] = array( 'status' => 'error', 'msg' => "ID $id: Arquivo não encontrado físico.", 'saved_bytes' => 0 );
                continue;
            }

            $info = pathinfo( $file );
            $ext  = isset( $info['extension'] ) ? strtolower( $info['extension'] ) : '';

            // Pula se já for WebP
            if ( $ext === 'webp' ) {
                $results[] = array( 'status' => 'warning', 'msg' => "ID $id: Já é WebP. Pulado.", 'saved_bytes' => 0 );
                continue;
            }

            // Pula formatos que não são JPEG/PNG (ex.: GIF, SVG, etc.), para evitar erro
            if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'jfif', 'jpe' ), true ) ) {
                $results[] = array( 'status' => 'warning', 'msg' => "ID $id: Formato {$ext} não convertido (apenas JPG/PNG).", 'saved_bytes' => 0 );
                continue;
            }

            // URL antiga do anexo, para atualizar links no banco
            $old_url = wp_get_attachment_url( $id );

            // Usa o editor padrão (já priorizando GD via filtro)
            $editor = wp_get_image_editor( $file );
            if ( is_wp_error( $editor ) ) {
                $results[] = array(
                    'status'      => 'error',
                    'msg'         => "ID $id: Erro ao abrir imagem (" . $editor->get_error_message() . ").",
                    'saved_bytes' => 0,
                );
                continue;
            }

            $original_size = filesize( $file );

            // Deixa o editor decidir o caminho/nome final e usa sempre o retorno
            $editor->set_quality( $quality );
            $saved = $editor->save( null, 'image/webp' );

            // Se o servidor não suportar WebP, o editor pode salvar como JPG/PNG.
            // Nesses casos, não vamos alterar o anexo nem remover original.
            if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ( isset( $saved['mime-type'] ) && 'image/webp' !== $saved['mime-type'] ) ) {
                $results[] = array(
                    'status'      => 'warning',
                    'msg'         => "ID $id: Servidor sem suporte completo a WebP. Arquivo mantido em formato original.",
                    'saved_bytes' => 0,
                );
                continue;
            }

            if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) ) {
                $new_file = $saved['path'];

                // Update Metadata com o caminho real salvo no disco
                update_attached_file( $id, $new_file );
                
                // Força o WP a regenerar as miniaturas no novo formato
                $metadata = wp_generate_attachment_metadata( $id, $new_file );
                wp_update_attachment_metadata( $id, $metadata );

                // Atualiza o mime type do anexo
                wp_update_post( array(
                    'ID'            => $id,
                    'post_mime_type'=> 'image/webp',
                ) );
                
                $new_size   = @filesize( $new_file );
                $saved_bytes = max( 0, $original_size - $new_size );

                // Remove original apenas se configurado
                if ( $remove_originals ) {
                    @unlink( $file );
                }

                // Atualiza URLs em posts e metadados, se desejado.
                // Usa sempre a URL oficial do anexo após atualizar o arquivo.
                $new_url = wp_get_attachment_url( $id );
                if ( $update_links || $update_postmeta ) {
                    // Versão com URL absoluta
                    conwebp_replace_urls_in_database( $old_url, $new_url, (bool) $update_links, (bool) $update_postmeta );

                    // Versão apenas com o caminho (caso o conteúdo use URLs relativas)
                    $old_path = parse_url( $old_url, PHP_URL_PATH );
                    $new_path = parse_url( $new_url, PHP_URL_PATH );
                    if ( $old_path && $new_path && $old_path !== $new_path ) {
                        conwebp_replace_urls_in_database( $old_path, $new_path, (bool) $update_links, (bool) $update_postmeta );
                    }
                }
                
                $results[] = array( 'status' => 'success', 'msg' => "ID $id: Otimizado com sucesso (" . $info['basename'] . ")", 'saved_bytes' => $saved_bytes );
            } else {
                $results[] = array( 'status' => 'error', 'msg' => "ID $id: Erro ao salvar WebP.", 'saved_bytes' => 0 );
            }
        }
        
        // total é o total de anexos que se enquadram no filtro (para barra de progresso)
        $total = isset( $query->found_posts ) ? intval( $query->found_posts ) : 0;

        wp_send_json_success( array(
            'count'   => $query->post_count,
            'total'   => $total,
            'results' => $results,
        ) );
    } else {
        wp_send_json_success( array(
            'count'   => 0,
            'total'   => 0,
            'results' => array(),
        ) );
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

// ----------------------------------------------------
// 7. ATUALIZAÇÃO SEGURA DE LINKS NO BANCO
// ----------------------------------------------------

/**
 * Substitui URLs antigas por novas em posts e postmeta,
 * preservando dados serializados (page builders, etc.).
 */
function conwebp_replace_urls_in_database( $old_url, $new_url, $do_posts = true, $do_meta = false ) {
    global $wpdb;

    if ( empty( $old_url ) || empty( $new_url ) || $old_url === $new_url ) {
        return;
    }

    // Atualiza posts.post_content
    if ( $do_posts ) {
        $like  = '%' . $wpdb->esc_like( $old_url ) . '%';
        $posts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
                $like
            )
        );

        if ( $posts ) {
            foreach ( $posts as $post ) {
                $new_content = conwebp_recursive_replace( $old_url, $new_url, $post->post_content );
                if ( $new_content !== $post->post_content ) {
                    $wpdb->update(
                        $wpdb->posts,
                        array( 'post_content' => $new_content ),
                        array( 'ID' => $post->ID ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
            }
        }
    }

    // Atualiza postmeta.meta_value (incluindo dados serializados de builders)
    if ( $do_meta ) {
        $like  = '%' . $wpdb->esc_like( $old_url ) . '%';
        $metas = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
                $like
            )
        );

        if ( $metas ) {
            foreach ( $metas as $meta ) {
                $new_value = conwebp_recursive_replace( $old_url, $new_url, $meta->meta_value );
                if ( $new_value !== $meta->meta_value ) {
                    $wpdb->update(
                        $wpdb->postmeta,
                        array( 'meta_value' => $new_value ),
                        array( 'meta_id' => $meta->meta_id ),
                        array( '%s' ),
                        array( '%d' )
                    );
                }
            }
        }
    }
}

/**
 * Faz str_replace preservando serialização de arrays/objetos.
 */
function conwebp_recursive_replace( $search, $replace, $data ) {
    if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data[ $key ] = conwebp_recursive_replace( $search, $replace, $value );
        }
        return $data;
    }

    if ( is_object( $data ) ) {
        foreach ( $data as $key => $value ) {
            $data->{$key} = conwebp_recursive_replace( $search, $replace, $value );
        }
        return $data;
    }

    // Tenta desserializar; se der certo, substitui recursivamente e serializa de volta.
    $maybe_unserialized = maybe_unserialize( $data );
    if ( $maybe_unserialized !== $data ) {
        $replaced = conwebp_recursive_replace( $search, $replace, $maybe_unserialized );
        return maybe_serialize( $replaced );
    }

    if ( is_string( $data ) ) {
        return str_replace( $search, $replace, $data );
    }

    return $data;
}

// ----------------------------------------------------
// 8. CORREÇÃO AUTOMÁTICA DE 404 (FLUSH REWRITE)
// ----------------------------------------------------

add_action( 'wp_ajax_conwebp_flush_rules', 'conwebp_handle_flush_rules_ajax' );
function conwebp_handle_flush_rules_ajax() {
    check_ajax_referer( 'conwebp_bulk_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Permissão negada.' );

    // O WordPress as vezes se perde quando trocamos extensões JPEG/PNG -> WebP
    // diretamente no banco de dados. O flush_rewrite_rules reconstrói o índice.
    flush_rewrite_rules();

    wp_send_json_success( 'Links permanentes resetados.' );
}

