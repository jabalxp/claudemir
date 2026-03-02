-- =====================================================
-- Migration: Role CRI + Sistema de Notificações
-- Banco: gestao_escolar (branch pos-juncao)
-- =====================================================

USE gestao_escolar;

-- 1. Adicionar role 'cri' ao ENUM da tabela Usuario
ALTER TABLE Usuario MODIFY COLUMN role ENUM('admin', 'gestor', 'professor', 'cri') NOT NULL DEFAULT 'gestor';

-- 2. Tabela de Notificações
CREATE TABLE IF NOT EXISTS Notificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM(
        'reserva',
        'registro_horario',
        'registro_turma',
        'exclusao_turma',
        'edicao_turma',
        'registro_docente',
        'edicao_docente',
        'exclusao_docente',
        'registro_ambiente',
        'edicao_ambiente',
        'exclusao_ambiente',
        'registro_curso',
        'edicao_curso',
        'exclusao_curso'
    ) NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN NOT NULL DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    autor_id INT NULL,
    entidade_id INT NULL,
    entidade_tipo VARCHAR(50) NULL,
    FOREIGN KEY (usuario_id) REFERENCES Usuario(id) ON DELETE CASCADE,
    FOREIGN KEY (autor_id) REFERENCES Usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_notificacao_usuario_lida ON Notificacao(usuario_id, lida, criado_em DESC);
