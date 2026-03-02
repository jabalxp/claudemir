CREATE DATABASE IF NOT EXISTS gestao_escolar DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE gestao_escolar;

CREATE TABLE IF NOT EXISTS ambiente (
  id int(11) NOT NULL AUTO_INCREMENT,
  nome varchar(255) DEFAULT NULL,
  tipo varchar(100) DEFAULT NULL,
  area_vinculada varchar(255) DEFAULT NULL,
  cidade varchar(100) DEFAULT NULL,
  capacidade int(11) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS curso (
  id int(11) NOT NULL AUTO_INCREMENT,
  tipo varchar(50) DEFAULT NULL,
  nome varchar(255) DEFAULT NULL,
  area varchar(255) DEFAULT NULL,
  carga_horaria_total int(11) DEFAULT NULL,
  semestral tinyint(1) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS docente (
  id int(11) NOT NULL AUTO_INCREMENT,
  nome varchar(255) DEFAULT NULL,
  area_conhecimento varchar(255) DEFAULT NULL,
  cidade varchar(100) DEFAULT NULL,
  carga_horaria_contratual int(11) DEFAULT NULL,
  disponibilidade_semanal varchar(255) DEFAULT NULL,
  areas_atuacao text DEFAULT NULL,
  cor_agenda varchar(7) DEFAULT '#ed1c24',
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS Usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    role ENUM('admin', 'gestor', 'professor', 'cri') NOT NULL DEFAULT 'gestor',
    docente_id INT DEFAULT NULL,
    obrigar_troca_senha TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO Usuario (nome, email, senha, role, obrigar_troca_senha) 
VALUES ('Administrador', 'admin@senai.br', '$2y$10$d8zHMItalmR8WxmucXWdquWSHknxyWy.imiT3sNO6H3L36DUcLVly', 'admin', 1);

CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    dias_semana VARCHAR(20) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    status ENUM('ativo', 'concluido') NOT NULL DEFAULT 'ativo',
    notas TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docente(id),
    FOREIGN KEY (usuario_id) REFERENCES Usuario(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX IF NOT EXISTS idx_reservas_docente ON reservas(docente_id, status);
CREATE INDEX IF NOT EXISTS idx_reservas_datas ON reservas(data_inicio, data_fim, status);
CREATE INDEX IF NOT EXISTS idx_reservas_usuario ON reservas(usuario_id, status);

CREATE TABLE IF NOT EXISTS turma (
  id int(11) NOT NULL AUTO_INCREMENT,
  curso_id int(11) DEFAULT NULL,
  tipo varchar(50) DEFAULT NULL,
  sigla varchar(50) DEFAULT NULL,
  vagas int(11) DEFAULT NULL,
  periodo varchar(50) DEFAULT NULL,
  data_inicio date DEFAULT NULL,
  data_fim date DEFAULT NULL,
  dias_semana varchar(100) DEFAULT NULL,
  ambiente_id int(11) DEFAULT NULL,
  componentes text DEFAULT NULL,
  docente_id1 int(11) DEFAULT NULL,
  docente_id2 int(11) DEFAULT NULL,
  docente_id3 int(11) DEFAULT NULL,
  docente_id4 int(11) DEFAULT NULL,
  local varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (curso_id) REFERENCES curso(id),
  FOREIGN KEY (ambiente_id) REFERENCES ambiente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS agenda (
  id int(11) NOT NULL AUTO_INCREMENT,
  turma_id int(11) DEFAULT NULL,
  docente_id int(11) DEFAULT NULL,
  ambiente_id int(11) DEFAULT NULL,
  dia_semana varchar(50) DEFAULT NULL,
  periodo varchar(20) DEFAULT 'Manhã',
  horario_inicio time DEFAULT NULL,
  horario_fim time DEFAULT NULL,
  data date DEFAULT NULL,
  status ENUM('CONFIRMADO','RESERVADO') DEFAULT 'CONFIRMADO',
  PRIMARY KEY (id),
  FOREIGN KEY (turma_id) REFERENCES turma(id),
  FOREIGN KEY (docente_id) REFERENCES docente(id),
  FOREIGN KEY (ambiente_id) REFERENCES ambiente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Notificações
CREATE TABLE IF NOT EXISTS notificacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('reserva','registro_horario','nova_turma','edicao_turma','exclusao_turma','geral') NOT NULL DEFAULT 'geral',
  titulo VARCHAR(255) NOT NULL,
  mensagem TEXT NOT NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  referencia_id INT DEFAULT NULL,
  referencia_tipo VARCHAR(50) DEFAULT NULL,
  criado_por INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES Usuario(id) ON DELETE CASCADE,
  FOREIGN KEY (criado_por) REFERENCES Usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id, lida);
CREATE INDEX idx_notificacoes_tipo ON notificacoes(usuario_id, tipo);
CREATE INDEX idx_notificacoes_created ON notificacoes(created_at);
