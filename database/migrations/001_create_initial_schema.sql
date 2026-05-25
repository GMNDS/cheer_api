CREATE TABLE `instituicao` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `authentik_user` varchar(100) UNIQUE,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) UNIQUE NOT NULL,
  `telefone` varchar(20),
  `id_endereco` int NOT NULL,
  `cnpj` char(14) UNIQUE NOT NULL,
  `tipo` varchar(100),
  `ano_fundacao` int,
  `categoria` varchar(100),
  `internacional` boolean
);

CREATE TABLE `avaliacao` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instituicao` int NOT NULL,
  `id_voluntario` int NOT NULL,
  `id_evento` int,
  `tipo_avaliacao` ENUM ('VOLUNTARIO_AVALIA_INSTITUICAO', 'INSTITUICAO_AVALIA_VOLUNTARIO') NOT NULL,
  `data` date,
  `nota` int,
  `justificativa` text
);

CREATE TABLE `voluntario_competencia` (
  `id_voluntario` int NOT NULL,
  `id_competencia` int NOT NULL,
  PRIMARY KEY (`id_voluntario`, `id_competencia`)
);

CREATE TABLE `competencia` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `tipo` ENUM ('Habilidade', 'Afinidade', 'Fraqueza') NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` text
);

CREATE TABLE `voluntario` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `authentik_user` varchar(100) UNIQUE,
  `nome` varchar(255) NOT NULL,
  `email` varchar(255) UNIQUE NOT NULL,
  `telefone` varchar(20),
  `id_endereco` int NOT NULL,
  `cpf` char(11) UNIQUE,
  `rg` varchar(20),
  `genero` varchar(50),
  `data_nascimento` date,
  `otp_code` varchar(500)
);

CREATE TABLE `voluntario_evento` (
  `id_voluntario` int NOT NULL,
  `id_evento` int NOT NULL,
  `status` varchar(100),
  `data_inscricao` timestamp,
  PRIMARY KEY (`id_voluntario`, `id_evento`)
);

CREATE TABLE `contrato_instituicao` (
  `id_contrato` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_voluntario` int NOT NULL,
  `id_instituicao` int NOT NULL,
  `data_contrato` date,
  `atividade_exercida` varchar(255),
  `periodo_atividade` varchar(255),
  `status_contrato` varchar(100)
);

CREATE TABLE `evento` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `id_instituicao` int NOT NULL,
  `id_endereco` int NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `constancia` varchar(100),
  `data_hora_inicio` timestamp NOT NULL,
  `data_hora_termino` timestamp,
  `tipo_evento` varchar(100) NOT NULL,
  `num_max_voluntarios` int,
  `descricao` text,
  `created_at` timestamp,
  `updated_at` timestamp
);

CREATE TABLE `enderecos` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `rua` varchar(255) NOT NULL,
  `bairro` varchar(255) NOT NULL,
  `cidade` varchar(255) NOT NULL,
  `uf` char(2) NOT NULL,
  `codigo_postal` char(8) NOT NULL,
  `lat` decimal(10,8),
  `lng` decimal(11,8)
);

CREATE TABLE `logs_eventos` (
  `id` int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  `tipo_evento` varchar(100) NOT NULL,
  `descricao` text NOT NULL,
  `nivel` varchar(50) NOT NULL,
  `origem` varchar(100),
  `id_usuario` int,
  `tipo_usuario` varchar(50),
  `ip_origem` varchar(45),
  `user_agent` varchar(255),
  `data_hora` timestamp NOT NULL
);

ALTER TABLE `instituicao` ADD FOREIGN KEY (`id_endereco`) REFERENCES `enderecos` (`id`);

ALTER TABLE `avaliacao` ADD FOREIGN KEY (`id_instituicao`) REFERENCES `instituicao` (`id`);

ALTER TABLE `avaliacao` ADD FOREIGN KEY (`id_voluntario`) REFERENCES `voluntario` (`id`);

ALTER TABLE `avaliacao` ADD FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id`);

ALTER TABLE `voluntario_competencia` ADD FOREIGN KEY (`id_voluntario`) REFERENCES `voluntario` (`id`);

ALTER TABLE `voluntario_competencia` ADD FOREIGN KEY (`id_competencia`) REFERENCES `competencia` (`id`);

ALTER TABLE `voluntario` ADD FOREIGN KEY (`id_endereco`) REFERENCES `enderecos` (`id`);

ALTER TABLE `voluntario_evento` ADD FOREIGN KEY (`id_voluntario`) REFERENCES `voluntario` (`id`);

ALTER TABLE `voluntario_evento` ADD FOREIGN KEY (`id_evento`) REFERENCES `evento` (`id`);

ALTER TABLE `contrato_instituicao` ADD FOREIGN KEY (`id_voluntario`) REFERENCES `voluntario` (`id`);

ALTER TABLE `contrato_instituicao` ADD FOREIGN KEY (`id_instituicao`) REFERENCES `instituicao` (`id`);

ALTER TABLE `evento` ADD FOREIGN KEY (`id_instituicao`) REFERENCES `instituicao` (`id`);

ALTER TABLE `evento` ADD FOREIGN KEY (`id_endereco`) REFERENCES `enderecos` (`id`);
