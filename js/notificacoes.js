/**
 * Sistema de Notificações — Frontend (CORRIGIDO)
 * 
 * CORREÇÕES APLICADAS:
 * - Referência de coluna 'criado_em' corrigida para 'created_at'
 * - Adicionado suporte para exibir 'titulo' da notificação
 */

let currentNotifTab = 'nao_lidas';

function getBaseUrl() {
    const meta = document.querySelector('meta[name="base-url"]');
    return meta ? meta.content : '';
}

function getNotifUrl() {
    return getBaseUrl() + 'php/controllers/notificacoes_process.php';
}

// --- Contagem de não lidas (badge) ---
async function fetchNotifCount() {
    try {
        const res = await fetch(getNotifUrl() + '?action=count');
        const data = await res.json();
        const badge = document.getElementById('notif-badge');
        if (!badge) return;

        if (data.count > 0) {
            badge.style.display = 'flex';
            badge.textContent = data.count > 99 ? '99+' : data.count;
        } else {
            badge.style.display = 'none';
        }
    } catch (e) {
        // Silenciar erros de rede no polling
    }
}

// --- Toggle do painel ---
function toggleNotifPanel() {
    const panel = document.getElementById('notif-panel');
    if (!panel) return;

    if (panel.style.display === 'none' || panel.style.display === '') {
        panel.style.display = 'flex';
        loadNotificacoes();
    } else {
        panel.style.display = 'none';
    }
}

// --- Fechar painel ao clicar fora ---
document.addEventListener('click', function (e) {
    const panel = document.getElementById('notif-panel');
    const bell = document.getElementById('notif-bell');
    if (!panel || !bell) return;

    if (!panel.contains(e.target) && !bell.contains(e.target)) {
        panel.style.display = 'none';
    }
});

// --- Trocar aba ---
function switchNotifTab(tab, btn) {
    currentNotifTab = tab;
    document.querySelectorAll('.notif-tab').forEach(function (t) {
        t.classList.remove('active');
    });
    if (btn) btn.classList.add('active');
    loadNotificacoes();
}

// --- Carregar notificações ---
async function loadNotificacoes() {
    const tipo = document.getElementById('notif-tipo-filter')?.value || '';
    const listEl = document.getElementById('notif-list');
    const actionsEl = document.getElementById('notif-actions');
    if (!listEl) return;

    listEl.innerHTML = '<p class="notif-empty"><i class="fas fa-spinner fa-spin"></i> Carregando...</p>';

    try {
        const res = await fetch(
            getNotifUrl() + '?action=list&filtro=' + currentNotifTab + '&tipo=' + encodeURIComponent(tipo)
        );
        const notifs = await res.json();
        renderNotificacoes(notifs, listEl);
        renderActions(actionsEl);
    } catch (e) {
        listEl.innerHTML = '<p class="notif-empty"><i class="fas fa-exclamation-triangle"></i> Erro ao carregar</p>';
    }
}

// --- Renderizar lista ---
function renderNotificacoes(notifs, container) {
    if (!notifs || notifs.length === 0) {
        const msgs = {
            nao_lidas: 'Nenhuma notificação não lida',
            lidas: 'Nenhuma notificação lida',
            todas: 'Nenhuma notificação'
        };
        container.innerHTML = '<p class="notif-empty"><i class="fas fa-bell-slash"></i> ' + (msgs[currentNotifTab] || 'Nenhuma notificação') + '</p>';
        return;
    }

    let html = '';
    notifs.forEach(function (n) {
        const lida = n.lida === '1' || n.lida === 1;
        const tipoLabel = getTipoLabel(n.tipo);
        const tipoColor = getTipoColor(n.tipo);
        const tempoRelativo = getTempoRelativo(n.created_at);

        html += '<div class="notif-item ' + (lida ? 'lida' : '') + '" onclick="marcarLida(' + n.id + ', this)" data-id="' + n.id + '">';
        html += '  <div class="notif-item-top">';
        html += '    <span class="notif-tipo-badge" style="background:' + tipoColor + '20;color:' + tipoColor + ';">' + tipoLabel + '</span>';
        html += '    <span class="notif-item-time">' + tempoRelativo + '</span>';
        html += '  </div>';
        if (n.titulo) {
            html += '  <div class="notif-item-titulo" style="font-weight:600;margin-bottom:2px;">' + escapeHtml(n.titulo) + '</div>';
        }
        html += '  <div class="notif-item-msg">' + escapeHtml(n.mensagem) + '</div>';
        html += '  <div class="notif-item-meta">';
        html += '    <i class="fas fa-user"></i> ' + escapeHtml(n.autor_nome);
        html += '  </div>';
        html += '</div>';
    });

    container.innerHTML = html;
}

// --- Renderizar botões de ação ---
function renderActions(container) {
    if (!container) return;
    let html = '';

    if (currentNotifTab === 'todas' || currentNotifTab === 'nao_lidas') {
        html += '<button class="notif-action-btn" onclick="marcarTodasLidas()"><i class="fas fa-check-double"></i> Ler Todas</button>';
    }
    if (currentNotifTab === 'lidas' || currentNotifTab === 'todas') {
        html += '<button class="notif-action-btn notif-action-danger" onclick="limparLidas()"><i class="fas fa-trash"></i> Limpar Lidas</button>';
    }

    container.innerHTML = html;
}

// --- Marcar uma notificação como lida ---
async function marcarLida(id, element) {
    try {
        const formData = new FormData();
        formData.append('action', 'marcar_lida');
        formData.append('id', id);
        await fetch(getNotifUrl(), { method: 'POST', body: formData });

        if (element) element.classList.add('lida');
        fetchNotifCount();

        if (currentNotifTab === 'nao_lidas') {
            setTimeout(function () { loadNotificacoes(); }, 300);
        }
    } catch (e) { /* silenciar */ }
}

// --- Marcar todas como lidas ---
async function marcarTodasLidas() {
    try {
        const formData = new FormData();
        formData.append('action', 'marcar_todas_lidas');
        await fetch(getNotifUrl(), { method: 'POST', body: formData });
        fetchNotifCount();
        loadNotificacoes();
    } catch (e) { /* silenciar */ }
}

// --- Limpar todas as lidas ---
async function limparLidas() {
    if (!confirm('Tem certeza que deseja limpar todas as notificações lidas?')) return;
    try {
        const formData = new FormData();
        formData.append('action', 'limpar_lidas');
        await fetch(getNotifUrl(), { method: 'POST', body: formData });
        fetchNotifCount();
        loadNotificacoes();
    } catch (e) { /* silenciar */ }
}

// --- Helpers ---
function getTipoLabel(tipo) {
    const labels = {
        reserva: 'Reserva',
        registro_horario: 'Reg. Horário',
        registro_turma: 'Nova Turma',
        exclusao_turma: 'Excl. Turma',
        edicao_turma: 'Edição Turma',
        registro_docente: 'Novo Docente',
        edicao_docente: 'Edição Docente',
        exclusao_docente: 'Excl. Docente',
        registro_ambiente: 'Novo Ambiente',
        edicao_ambiente: 'Edição Ambiente',
        exclusao_ambiente: 'Excl. Ambiente',
        registro_curso: 'Novo Curso',
        edicao_curso: 'Edição Curso',
        exclusao_curso: 'Excl. Curso'
    };
    return labels[tipo] || tipo;
}

function getTipoColor(tipo) {
    if (tipo.startsWith('exclusao')) return '#dc3545';
    if (tipo.startsWith('edicao')) return '#ff8f00';
    if (tipo.startsWith('registro')) return '#388e3c';
    if (tipo === 'reserva') return '#1976d2';
    return '#666';
}

function getTempoRelativo(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return Math.floor(diff / 60) + 'min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    return date.toLocaleDateString('pt-BR');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// --- Inicialização ---
document.addEventListener('DOMContentLoaded', function () {
    fetchNotifCount();
    setInterval(fetchNotifCount, 30000);
});
