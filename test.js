const $ = require('jquery');
const ajaxurl = 'http://localhost/wp-admin/admin-ajax.php';
jQuery = $;

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
        let totalAttachments = 100;
        
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
            $('#btn-start-bulk').show().text('Retomar Conversão');
            $('#bulk-status-text').text('Pausado pelo usuário.');
            addLog('Operação pausada.', 'warning');
        });

        function processNextBatch() {
            if (!isRunning) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'conwebp_bulk_optimizer',
                    nonce: 'nonce123',
                    offset: processed
                },
                success: function(res) {
                    if (res.success) {
                        processed += res.data.count;
                        let progress = Math.round((processed / totalAttachments) * 100);
                        
                        $('#bulk-progress-bar').css('width', progress + '%');
                        $('#bulk-percentage').text(progress + '%');
                        $('#stat-total').text(processed);
                        
                        // Atualiza log
                        res.data.results.forEach(item => {
                            addLog(item.msg, item.status);
                        });

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
                    addLog('Erro de conexão no servidor.', 'error');
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
            $('#bulk-status-text').html('✅ <strong>Faxina Concluída!</strong> Todas as imagens foram processadas.');
            $('#btn-stop-bulk').hide();
            $('#btn-start-bulk').hide();
            addLog('Conversão total finalizada com sucesso.', 'success');
        }

        // Hard Cleanup Logic
        let isHardRunning = false;
        
        // Carrega tamanhos úteis na tela de forma segura
        const usefulSizes = ["size1", "size2"] || [];

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
            
            if (!confirm('ATENÇÃO: Deseja apagar miniaturas inúteis? Isso libera espaço mas remove tamanhos legados.')) return false;
            
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
                    nonce: 'nonce123',
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
                            addLog('✅ FAXINA NÍVEL HARD CONCLUÍDA!', 'success');
                            $('#hard-status-msg').text('Concluído com sucesso!');
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
                    addLog('Erro de conexão ao processar Faxina Hard.', 'error');
                    isHardRunning = false;
                    $('#btn-start-hard').prop('disabled', false).text('Tentar Novamente');
                }
            });
        }
    });
